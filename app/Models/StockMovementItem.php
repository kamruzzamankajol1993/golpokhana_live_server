<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockMovementItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity_base' => 'decimal:8',
        'source_before' => 'decimal:8',
        'source_after' => 'decimal:8',
        'destination_before' => 'decimal:8',
        'destination_after' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        $guardImmutable = function (StockMovementItem $item) {
            $movement = $item->relationLoaded('movement') ? $item->movement : $item->movement()->first();
            if ($movement?->status === StockMovement::STATUS_POSTED) {
                throw new LogicException('Items of a posted stock movement are immutable.');
            }
        };

        static::creating($guardImmutable);
        static::updating($guardImmutable);
        static::deleting($guardImmutable);
    }

    public function movement()
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
