<?php

namespace App\Services\Inventory;

use App\Models\Purchase;
use App\Models\Scopes\BranchScope;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReceivingService
{
    public function __construct(
        private StockLocationService $locations,
        private StockMovementService $movements
    ) {
    }

    public function receive(Purchase|int $purchase, ?int $userId = null): Purchase
    {
        $purchaseId = $purchase instanceof Purchase ? (int) $purchase->id : (int) $purchase;

        return DB::transaction(function () use ($purchaseId, $userId) {
            $purchase = Purchase::query()
                ->withoutGlobalScope(BranchScope::class)
                ->with(['items.ingredient', 'vendor', 'branch', 'receivedMovement'])
                ->whereKey($purchaseId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchase->status === Purchase::STATUS_RECEIVED) {
                return $purchase;
            }

            if (!$purchase->isEditable()) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only a draft purchase can be received.',
                ]);
            }

            if ($purchase->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'A purchase must contain at least one item before receiving.',
                ]);
            }

            $main = $this->locations->forBranchAndType((int) $purchase->branch_id, StockLocation::TYPE_MAIN);
            $movement = $this->movements->post(
                (int) $purchase->branch_id,
                StockMovement::PURCHASE_RECEIVE,
                $purchase->items->map(fn ($item) => [
                    'ingredient_id' => (int) $item->ingredient_id,
                    'quantity_base' => (string) $item->base_quantity,
                ])->all(),
                null,
                (int) $main->id,
                [
                    'reference_type' => Purchase::class,
                    'reference_id' => (int) $purchase->id,
                    'occurred_at' => now(),
                    'performed_by' => $userId,
                    'reason' => 'Purchase receive/post: ' . $purchase->purchase_no,
                ]
            );

            $purchase->forceFill([
                'status' => Purchase::STATUS_RECEIVED,
                'received_at' => now(),
                'received_by' => $userId,
                'received_stock_movement_id' => $movement->id,
            ])->save();

            return $purchase->fresh(['items.ingredient.baseUnit', 'vendor', 'branch', 'receivedMovement.items']);
        }, 5);
    }
}
