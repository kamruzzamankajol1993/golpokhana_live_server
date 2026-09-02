<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockTransfer extends Model
{
    use HasFactory, BelongsToBranch;

    public const DIRECTION_MAIN_TO_KITCHEN = 'MAIN_TO_KITCHEN';
    public const DIRECTION_KITCHEN_TO_MAIN = 'KITCHEN_TO_MAIN';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';

    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (StockTransfer $transfer) {
            if ($transfer->getOriginal('status') === self::STATUS_POSTED) {
                throw new LogicException('Posted stock transfers are immutable. Use the return/reversal workflow for corrections.');
            }
        });

        static::deleting(function (StockTransfer $transfer) {
            if ($transfer->status === self::STATUS_POSTED) {
                throw new LogicException('Posted stock transfers cannot be deleted.');
            }
        });
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function kitchenRequest()
    {
        return $this->belongsTo(KitchenRequest::class);
    }

    public function originalTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'original_transfer_id');
    }

    public function returns()
    {
        return $this->hasMany(StockTransfer::class, 'original_transfer_id');
    }

    public function sourceLocation()
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    public function postedMovement()
    {
        return $this->belongsTo(StockMovement::class, 'posted_movement_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
