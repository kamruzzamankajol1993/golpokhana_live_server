<?php

namespace App\Services\Inventory;

use App\Models\FoodItem;
use App\Models\InventoryException;
use App\Models\MenuItemRecipe;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderInventoryConsumptionService
{
    public function __construct(
        private StockMovementService $movements,
        private StockLocationService $locations,
        private DecimalQuantity $decimal,
        private InventorySiteContext $site
    ) {
    }

    public function consumeOrderInventory(Order|int $order, string $triggerSource, ?int $userId = null): OrderInventoryConsumption
    {
        if (!in_array($triggerSource, [
            OrderInventoryConsumption::TRIGGER_KITCHEN_COMPLETE,
            OrderInventoryConsumption::TRIGGER_PAYMENT_COMPLETE,
        ], true)) {
            throw ValidationException::withMessages(['trigger_source' => 'Unsupported inventory consumption trigger.']);
        }

        $orderId = $order instanceof Order ? (int) $order->id : (int) $order;

        return DB::transaction(function () use ($orderId, $triggerSource, $userId) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->withoutGlobalScopes()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = OrderInventoryConsumption::query()
                
                ->where('order_id', $lockedOrder->id)
                ->with($this->relations())
                ->first();

            if ($existing) {
                return $existing;
            }

            $this->site->ensureDefaultLocations();
            $kitchen = $this->locations->forType(StockLocation::TYPE_KITCHEN);
            $orderItems = $lockedOrder->orderDetails()
                ->with(['foodItem' => fn ($q) => $q->withoutGlobalScopes()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $detailRows = [];
            $movementTotals = [];
            $missingRecipeMenuIds = [];

            foreach ($orderItems as $orderItem) {
                if ((int) ($orderItem->is_unavailable ?? 0) === 1) {
                    continue;
                }

                $orderQty = $this->decimal->normalize((string) ($orderItem->quantity ?? '0'));
                if (!$this->decimal->isPositive($orderQty)) {
                    continue;
                }

                $food = $orderItem->foodItem;
                if (!$food || !$food->inventory_tracking) {
                    continue;
                }

                $recipe = $this->recipeForOrderItem($food, $orderItem->created_at);
                if (!$recipe || $recipe->items->isEmpty() || !$this->decimal->isPositive((string) $recipe->yield_quantity)) {
                    $missingRecipeMenuIds[(int) $food->id] = (string) $food->name;
                    continue;
                }

                foreach ($recipe->items as $recipeItem) {
                    $scaled = $this->decimal->divide(
                        $this->decimal->multiply((string) $recipeItem->base_quantity, $orderQty),
                        (string) $recipe->yield_quantity
                    );

                    if (!$this->decimal->isPositive($scaled)) {
                        continue;
                    }

                    $detailRows[] = [
                        'order_item_id' => (int) $orderItem->id,
                        'menu_item_id' => (int) $food->id,
                        'recipe_id' => (int) $recipe->id,
                        'recipe_version_no' => (int) $recipe->version_no,
                        'ingredient_id' => (int) $recipeItem->ingredient_id,
                        'quantity_base' => $scaled,
                    ];

                    $ingredientId = (int) $recipeItem->ingredient_id;
                    $movementTotals[$ingredientId] = isset($movementTotals[$ingredientId])
                        ? $this->decimal->add($movementTotals[$ingredientId], $scaled)
                        : $scaled;
                }
            }

            $movement = null;
            if ($movementTotals !== []) {
                ksort($movementTotals, SORT_NUMERIC);
                $movement = $this->movements->post(
                    StockMovement::ORDER_CONSUMPTION,
                    array_map(
                        fn ($ingredientId, $quantity) => [
                            'ingredient_id' => (int) $ingredientId,
                            'quantity_base' => $quantity,
                        ],
                        array_keys($movementTotals),
                        array_values($movementTotals)
                    ),
                    (int) $kitchen->id,
                    null,
                    [
                        'reference_type' => Order::class,
                        'reference_id' => $lockedOrder->id,
                        'performed_by' => $userId,
                        'reason' => "Order {$lockedOrder->order_number} ingredient consumption ({$triggerSource})",
                    ]
                );
            }

            $consumption = OrderInventoryConsumption::query()
                
                ->create([
                    'order_id' => $lockedOrder->id,
                    'trigger_source' => $triggerSource,
                    'consumed_at' => now(),
                    'stock_movement_id' => $movement?->id,
                    'created_by' => $userId,
                ]);

            foreach ($detailRows as $row) {
                $consumption->items()->create($row);
            }

            foreach ($missingRecipeMenuIds as $menuId => $menuName) {
                InventoryException::query()->create([
                    'exception_type' => InventoryException::MISSING_RECIPE,
                    'reference_type' => OrderInventoryConsumption::class,
                    'reference_id' => $consumption->id,
                    'ingredient_id' => null,
                    'location_id' => $kitchen->id,
                    'shortage_base' => '0.00000000',
                    'status' => InventoryException::STATUS_OPEN,
                    'detected_at' => now(),
                    'resolution_note' => "Tracked menu item {$menuName} (#{$menuId}) had no usable recipe version for this order item.",
                ]);
            }

            if ($movement) {
                foreach ($movement->items as $movementItem) {
                    $after = (string) ($movementItem->source_after ?? '0');
                    if ($this->decimal->compare($after, '0') < 0) {
                        InventoryException::query()->create([
                                    'exception_type' => InventoryException::NEGATIVE_KITCHEN_STOCK,
                            'reference_type' => OrderInventoryConsumption::class,
                            'reference_id' => $consumption->id,
                            'ingredient_id' => (int) $movementItem->ingredient_id,
                            'location_id' => (int) $kitchen->id,
                            'shortage_base' => $this->decimal->subtract('0', $after),
                            'status' => InventoryException::STATUS_OPEN,
                            'detected_at' => now(),
                        ]);
                    }
                }
            }

            return $consumption->fresh($this->relations());
        }, 5);
    }

    /**
     * Temporarily reconcile an already-consumed order before its item lines are edited.
     *
     * The original ORDER_CONSUMPTION stock movement is never mutated or deleted. Instead,
     * a REVERSAL movement restores the previously consumed Kitchen Stock, then the current
     * consumption snapshot is removed with direct DB queries so OrderDetail model guards
     * allow the edit inside the caller's transaction. finishOrderEditReconciliation()
     * creates a fresh snapshot for the edited order.
     *
     * If the order has not consumed inventory yet, no reconciliation is needed and null is
     * returned.
     */
    public function beginOrderEditReconciliation(Order|int $order, ?int $userId = null): ?array
    {
        $orderId = $order instanceof Order ? (int) $order->id : (int) $order;

        // Keep lock ordering consistent with consumeOrderInventory(): order first,
        // then its consumption snapshot. This avoids a potential cross-transaction deadlock.
        /** @var Order $lockedOrder */
        $lockedOrder = Order::query()
            ->withoutGlobalScopes()
            ->whereKey($orderId)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var OrderInventoryConsumption|null $consumption */
        $consumption = OrderInventoryConsumption::query()
            
            ->where('order_id', $orderId)
            ->lockForUpdate()
            ->first();

        if (!$consumption) {
            return null;
        }

        $this->site->ensureDefaultLocations();

        $triggerSource = (string) $consumption->trigger_source;
        if (!in_array($triggerSource, [
            OrderInventoryConsumption::TRIGGER_KITCHEN_COMPLETE,
            OrderInventoryConsumption::TRIGGER_PAYMENT_COMPLETE,
        ], true)) {
            throw ValidationException::withMessages([
                'trigger_source' => 'The existing inventory consumption has an unsupported trigger source.',
            ]);
        }

        $reversalMovementId = null;
        $originalMovementId = $consumption->stock_movement_id ? (int) $consumption->stock_movement_id : null;

        if ($originalMovementId) {
            /** @var StockMovement|null $originalMovement */
            $originalMovement = StockMovement::query()
                
                ->whereKey($originalMovementId)
                ->with('items')
                ->lockForUpdate()
                ->first();

            if (!$originalMovement
                || $originalMovement->movement_type !== StockMovement::ORDER_CONSUMPTION
                || !$originalMovement->source_location_id) {
                throw ValidationException::withMessages([
                    'inventory' => 'The existing order inventory movement is invalid and cannot be reconciled safely.',
                ]);
            }

            $restoreTotals = [];
            foreach ($originalMovement->items as $movementItem) {
                $ingredientId = (int) $movementItem->ingredient_id;
                $quantity = $this->decimal->normalize((string) $movementItem->quantity_base);
                if ($ingredientId < 1 || !$this->decimal->isPositive($quantity)) {
                    continue;
                }

                $restoreTotals[$ingredientId] = isset($restoreTotals[$ingredientId])
                    ? $this->decimal->add($restoreTotals[$ingredientId], $quantity)
                    : $quantity;
            }

            if ($restoreTotals !== []) {
                ksort($restoreTotals, SORT_NUMERIC);
                $reversal = $this->movements->post(
                    StockMovement::REVERSAL,
                    array_map(
                        fn ($ingredientId, $quantity) => [
                            'ingredient_id' => (int) $ingredientId,
                            'quantity_base' => $quantity,
                        ],
                        array_keys($restoreTotals),
                        array_values($restoreTotals)
                    ),
                    null,
                    (int) $originalMovement->source_location_id,
                    [
                        'reference_type' => StockMovement::class,
                        'reference_id' => $originalMovement->id,
                        'performed_by' => $userId,
                        'reason' => "Order {$lockedOrder->order_number} inventory edit reconciliation reversal",
                    ]
                );
                $reversalMovementId = (int) $reversal->id;
            }
        }

        // The consumption model is intentionally immutable. This controlled workflow uses
        // direct DB deletes only for the superseded snapshot; the posted stock ledger stays
        // auditable through the original ORDER_CONSUMPTION + REVERSAL movements.
        DB::table('inventory_exceptions')
            ->where('reference_type', OrderInventoryConsumption::class)
            ->where('reference_id', $consumption->id)
            ->delete();

        DB::table('order_inventory_consumption_items')
            ->where('order_inventory_consumption_id', $consumption->id)
            ->delete();

        DB::table('order_inventory_consumptions')
            ->where('id', $consumption->id)
            ->delete();

        return [
            'order_id' => $orderId,
            'trigger_source' => $triggerSource,
            'original_consumption_id' => (int) $consumption->id,
            'original_stock_movement_id' => $originalMovementId,
            'reversal_stock_movement_id' => $reversalMovementId,
        ];
    }

    /**
     * Rebuild the inventory-consumption snapshot after an order edit reconciliation.
     */
    public function finishOrderEditReconciliation(
        Order|int $order,
        array $reconciliation,
        ?int $userId = null
    ): OrderInventoryConsumption {
        $orderId = $order instanceof Order ? (int) $order->id : (int) $order;

        if ((int) ($reconciliation['order_id'] ?? 0) !== $orderId) {
            throw ValidationException::withMessages([
                'order_id' => 'Inventory reconciliation does not belong to this order.',
            ]);
        }

        $triggerSource = (string) ($reconciliation['trigger_source'] ?? '');
        if (!in_array($triggerSource, [
            OrderInventoryConsumption::TRIGGER_KITCHEN_COMPLETE,
            OrderInventoryConsumption::TRIGGER_PAYMENT_COMPLETE,
        ], true)) {
            throw ValidationException::withMessages([
                'trigger_source' => 'Inventory reconciliation has an invalid trigger source.',
            ]);
        }

        return $this->consumeOrderInventory($orderId, $triggerSource, $userId);
    }

    private function recipeForOrderItem(FoodItem $food, $orderedAt): ?MenuItemRecipe
    {
        $query = MenuItemRecipe::query()
            ->where('menu_item_id', $food->id)
            ->with(['items.ingredient.baseUnit']);

        if ($orderedAt) {
            $historical = (clone $query)
                ->where(function ($q) use ($orderedAt) {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', $orderedAt);
                })
                ->orderByDesc('version_no')
                ->first();
            if ($historical) {
                return $historical;
            }
        }

        return $query->where('is_active', true)->orderByDesc('version_no')->first();
    }

    private function relations(): array
    {
        return [
            'order',
            'stockMovement.items.ingredient.baseUnit',
            'items.ingredient.baseUnit',
            'items.foodItem',
            'items.recipe',
            'creator',
        ];
    }
}
