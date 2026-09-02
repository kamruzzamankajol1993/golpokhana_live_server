<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class Purchase extends Model
{
    use HasFactory, BelongsToBranch;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $guarded = [];

    protected $casts = [
        'purchase_date' => 'date',
        'received_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::updating(function (Purchase $purchase) {
            if ($purchase->getOriginal('status') === self::STATUS_RECEIVED) {
                throw new LogicException('Received purchases are immutable. Use purchase return/reversal or an authorized adjustment for corrections.');
            }
        });

        static::deleting(function (Purchase $purchase) {
            if ($purchase->status === self::STATUS_RECEIVED) {
                throw new LogicException('Received purchases cannot be deleted. Use purchase return/reversal instead.');
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receivedMovement()
    {
        return $this->belongsTo(StockMovement::class, 'received_stock_movement_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->received_stock_movement_id === null;
    }
}
