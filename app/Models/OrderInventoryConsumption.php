<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class OrderInventoryConsumption extends Model
{
    use HasFactory, BelongsToBranch;

    public const TRIGGER_KITCHEN_COMPLETE = 'KITCHEN_COMPLETE';
    public const TRIGGER_PAYMENT_COMPLETE = 'PAYMENT_COMPLETE';

    protected $guarded = [];

    protected $casts = [
        'consumed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Order inventory consumption is immutable once created. Use a controlled reversal/restock movement for corrections.');
        });
        static::deleting(function () {
            throw new LogicException('Order inventory consumption history cannot be deleted.');
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(OrderInventoryConsumptionItem::class);
    }

    public function stockMovement()
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
