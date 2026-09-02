<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockTransferItem extends Model
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
        static::updating(function (StockTransferItem $item) {
            if ($item->transfer?->status === StockTransfer::STATUS_POSTED) {
                throw new LogicException('Items of a posted stock transfer are immutable.');
            }
        });
        static::deleting(function (StockTransferItem $item) {
            if ($item->transfer?->status === StockTransfer::STATUS_POSTED) {
                throw new LogicException('Items of a posted stock transfer cannot be deleted.');
            }
        });
    }

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
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
