<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class InventoryWastageItem extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'quantity' => 'decimal:8',
        'conversion_factor_snapshot' => 'decimal:8',
        'base_quantity' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::updating(function (InventoryWastageItem $item) {
            if ($item->wastage?->status === InventoryWastage::STATUS_POSTED) {
                throw new LogicException('Items of posted wastage are immutable.');
            }
        });
        static::deleting(function (InventoryWastageItem $item) {
            if ($item->wastage?->status === InventoryWastage::STATUS_POSTED) {
                throw new LogicException('Items of posted wastage cannot be deleted.');
            }
        });
    }

    public function wastage() { return $this->belongsTo(InventoryWastage::class, 'inventory_wastage_id'); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
    public function packageConversion() { return $this->belongsTo(IngredientUnitConversion::class, 'package_conversion_id'); }
    public function unit() { return $this->belongsTo(Unit::class); }
}
