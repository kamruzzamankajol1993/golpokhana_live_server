<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:8',
        'conversion_factor_snapshot' => 'decimal:8',
        'base_quantity' => 'decimal:8',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        $guard = function (PurchaseItem $item) {
            $status = Purchase::query()->withoutGlobalScopes()->whereKey($item->purchase_id)->value('status');
            if ($status === Purchase::STATUS_RECEIVED) {
                throw new LogicException('Items on a received purchase are immutable.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function packageConversion()
    {
        return $this->belongsTo(IngredientUnitConversion::class, 'package_conversion_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
