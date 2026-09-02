<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class InventoryAdjustmentItem extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'system_qty_base' => 'decimal:8',
        'physical_qty_base' => 'decimal:8',
        'difference_base' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::updating(function (InventoryAdjustmentItem $item) {
            if ($item->adjustment?->status === InventoryAdjustment::STATUS_POSTED) {
                throw new LogicException('Items of a posted adjustment are immutable.');
            }
        });
        static::deleting(function (InventoryAdjustmentItem $item) {
            if ($item->adjustment?->status === InventoryAdjustment::STATUS_POSTED) {
                throw new LogicException('Items of a posted adjustment cannot be deleted.');
            }
        });
    }

    public function adjustment() { return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id'); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
}
