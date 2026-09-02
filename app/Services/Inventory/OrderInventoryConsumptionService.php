<?php

namespace App\Services\Inventory;

use App\Models\FoodItem;
use App\Models\InventoryException;
use App\Models\MenuItemRecipe;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\Scopes\BranchScope;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderInventoryConsumptionService
{
    public function __construct(
        private StockMovementService $movements,
        private StockLocationService $locations,
        private DecimalQuantity $decimal
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
                ->withoutGlobalScope(BranchScope::class)
                ->where('order_id', $lockedOrder->id)
                ->with($this->relations())
                ->first();

            if ($existing) {
                return $existing;
            }

            $branchId = (int) $lockedOrder->branch_id;
            if ($branchId < 1) {
                throw ValidationException::withMessages(['branch_id' => 'Order inventory consumption requires a valid order branch.']);
            }

            $kitchen = $this->locations->forBranchAndType($branchId, StockLocation::TYPE_KITCHEN);
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
                if (!$food || (int) $food->branch_id !== $branchId || !$food->inventory_tracking) {
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
                    $branchId,
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
                ->withoutGlobalScope(BranchScope::class)
                ->create([
                    'branch_id' => $branchId,
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
                InventoryException::query()->withoutGlobalScope(BranchScope::class)->create([
                    'branch_id' => $branchId,
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
                        InventoryException::query()->withoutGlobalScope(BranchScope::class)->create([
                            'branch_id' => $branchId,
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
