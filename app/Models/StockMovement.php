<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockMovement extends Model
{
    use HasFactory, BelongsToBranch;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';

    public const OPENING_STOCK = 'OPENING_STOCK';
    public const PURCHASE_RECEIVE = 'PURCHASE_RECEIVE';
    public const MAIN_TO_KITCHEN = 'MAIN_TO_KITCHEN';
    public const KITCHEN_TO_MAIN = 'KITCHEN_TO_MAIN';
    public const ORDER_CONSUMPTION = 'ORDER_CONSUMPTION';
    public const WASTAGE = 'WASTAGE';
    public const POSITIVE_ADJUSTMENT = 'POSITIVE_ADJUSTMENT';
    public const NEGATIVE_ADJUSTMENT = 'NEGATIVE_ADJUSTMENT';
    public const PURCHASE_RETURN = 'PURCHASE_RETURN';
    public const REVERSAL = 'REVERSAL';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            if (($movement->status ?: self::STATUS_DRAFT) === self::STATUS_POSTED) {
                throw new LogicException('Stock movements must be assembled as DRAFT and posted through the stock movement service.');
            }
        });

        static::updating(function (StockMovement $movement) {
            if ($movement->getOriginal('status') === self::STATUS_POSTED) {
                throw new LogicException('Posted stock movements are immutable. Create a reversal or adjustment movement instead.');
            }
        });

        static::deleting(function (StockMovement $movement) {
            if ($movement->status === self::STATUS_POSTED) {
                throw new LogicException('Posted stock movements cannot be deleted. Create a reversal or adjustment movement instead.');
            }
        });
    }

    public static function types(): array
    {
        return [
            self::OPENING_STOCK,
            self::PURCHASE_RECEIVE,
            self::MAIN_TO_KITCHEN,
            self::KITCHEN_TO_MAIN,
            self::ORDER_CONSUMPTION,
            self::WASTAGE,
            self::POSITIVE_ADJUSTMENT,
            self::NEGATIVE_ADJUSTMENT,
            self::PURCHASE_RETURN,
            self::REVERSAL,
        ];
    }

    public function items()
    {
        return $this->hasMany(StockMovementItem::class);
    }

    public function sourceLocation()
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
