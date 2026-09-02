<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\Scopes\BranchScope;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\VendorIngredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private UnitConversionService $conversion,
        private DecimalQuantity $decimal
    ) {
    }

    public function saveDraft(
        int $branchId,
        Vendor $vendor,
        array $header,
        array $itemRows,
        ?Purchase $purchase = null,
        ?int $userId = null
    ): Purchase {
        if (!$vendor->is_active) {
            throw ValidationException::withMessages(['vendor_id' => 'Select an active vendor.']);
        }

        $items = $this->normalizeItems($itemRows);
        $subtotal = '0.0000';
        foreach ($items as $item) {
            $subtotal = $this->decimal->add($subtotal, $item['line_total'], 4);
        }

        $discount = $this->decimal->normalize((string) ($header['discount'] ?? '0'), 4);
        $tax = $this->decimal->normalize((string) ($header['tax'] ?? '0'), 4);
        if ($this->decimal->compare($discount, '0', 4) < 0 || $this->decimal->compare($tax, '0', 4) < 0) {
            throw ValidationException::withMessages(['discount' => 'Discount and tax cannot be negative.']);
        }
        if ($this->decimal->compare($discount, $subtotal, 4) > 0) {
            throw ValidationException::withMessages(['discount' => 'Purchase discount cannot exceed the subtotal.']);
        }
        $total = $this->decimal->add($this->decimal->subtract($subtotal, $discount, 4), $tax, 4);

        return DB::transaction(function () use ($branchId, $vendor, $header, $items, $subtotal, $discount, $tax, $total, $purchase, $userId) {
            if ($purchase) {
                $purchase = Purchase::query()
                    ->withoutGlobalScope(BranchScope::class)
                    ->whereKey($purchase->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $purchase->branch_id !== $branchId) {
                    throw ValidationException::withMessages(['branch_id' => 'A purchase cannot be moved to another branch after creation.']);
                }
                if (!$purchase->isEditable()) {
                    throw ValidationException::withMessages(['purchase' => 'Received or closed purchases cannot be edited.']);
                }
            } else {
                $purchase = new Purchase();
                $purchase->branch_id = $branchId;
                $purchase->purchase_no = $this->nextPurchaseNumber($branchId);
                $purchase->created_by = $userId;
            }

            $purchase->fill([
                'vendor_id' => $vendor->id,
                'purchase_date' => $header['purchase_date'],
                'invoice_no' => $header['invoice_no'] ?? null,
                'reference_no' => $header['reference_no'] ?? null,
                'notes' => $header['notes'] ?? null,
                'status' => Purchase::STATUS_DRAFT,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
            ]);
            $purchase->save();

            $purchase->items()->delete();
            foreach ($items as $item) {
                $purchase->items()->create($item);
                VendorIngredient::query()->updateOrCreate(
                    ['vendor_id' => $vendor->id, 'ingredient_id' => $item['ingredient_id']],
                    ['last_price' => $item['unit_price']]
                );
            }

            return $purchase->fresh(['items.ingredient.baseUnit', 'items.unit', 'vendor', 'branch']);
        }, 5);
    }

    private function normalizeItems(array $rows): array
    {
        $normalized = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $unitChoice = trim((string) ($row['unit_choice'] ?? ''));
            $quantity = trim((string) ($row['quantity'] ?? ''));
            $unitPrice = trim((string) ($row['unit_price'] ?? ''));

            if ($ingredientId < 1 || $unitChoice === '' || $quantity === '' || $unitPrice === '') {
                throw ValidationException::withMessages(["items.{$index}" => 'Each purchase row requires ingredient, quantity, unit/package variant and purchase price.']);
            }
            if (isset($seen[$ingredientId])) {
                throw ValidationException::withMessages(['items' => 'The same ingredient cannot appear twice in one purchase.']);
            }

            $ingredient = Ingredient::query()->with('unitConversions')->whereKey($ingredientId)->firstOrFail();
            if (!$ingredient->is_active || !$ingredient->track_inventory) {
                throw ValidationException::withMessages(["items.{$index}.ingredient_id" => 'Only active inventory-tracked ingredients can be purchased.']);
            }

            $quantityNormalized = $this->decimal->normalize($quantity);
            if (!$this->decimal->isPositive($quantityNormalized)) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'Purchase quantity must be greater than zero.']);
            }
            $priceNormalized = $this->decimal->normalize($unitPrice, 4);
            if ($this->decimal->compare($priceNormalized, '0', 4) < 0) {
                throw ValidationException::withMessages(["items.{$index}.unit_price" => 'Purchase price cannot be negative.']);
            }

            $selection = $this->conversion->resolveChoice($ingredient, $unitChoice, 'purchase');
            $unit = $selection['unit'];
            $packageConversion = $selection['conversion'];
            $factor = $selection['factor'];
            $baseQuantity = $this->decimal->multiply($quantityNormalized, $factor);

            // Client-friendly purchase pricing:
            // - Standard measurement units (g, kg, ml, L, pcs, etc.): the entered price is
            //   the TOTAL amount paid for the entered quantity. Example: 500 g = Tk 20.
            // - Ingredient-specific package variants (Packet/Box/Bag/Carton, etc.): the
            //   entered price is PER PACKAGE, so quantity x price gives the line total.
            $lineTotal = $packageConversion
                ? $this->decimal->multiply($quantityNormalized, $priceNormalized, 4)
                : $priceNormalized;

            $seen[$ingredientId] = true;
            $normalized[] = [
                'ingredient_id' => $ingredientId,
                'quantity' => $quantityNormalized,
                'unit_id' => $unit->id,
                'package_conversion_id' => $packageConversion?->id,
                'conversion_factor_snapshot' => $factor,
                'base_quantity' => $baseQuantity,
                'unit_price' => $priceNormalized,
                'line_total' => $lineTotal,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one purchase item.']);
        }

        return $normalized;
    }

    private function nextPurchaseNumber(int $branchId): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = 'PUR-' . $branchId . '-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(5));
            $exists = Purchase::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)
                ->where('purchase_no', $number)
                ->exists();
            if (!$exists) {
                return $number;
            }
        }

        throw ValidationException::withMessages(['purchase_no' => 'Could not allocate a unique purchase number. Please try again.']);
    }
}
