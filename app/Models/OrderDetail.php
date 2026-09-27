<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        $ensureInventoryIsEditable = function (OrderDetail $detail): void {
            if (!Schema::hasTable('order_inventory_consumptions')) {
                return;
            }

            if (OrderInventoryConsumption::query()->where('order_id', $detail->order_id)->exists()) {
                throw ValidationException::withMessages([
                    'order_id' => 'This order has already consumed inventory. Use the controlled order-edit reconciliation workflow before changing item lines.',
                ]);
            }
        };

        static::creating($ensureInventoryIsEditable);
        static::updating($ensureInventoryIsEditable);
        static::deleting($ensureInventoryIsEditable);
    }

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function kot()
    {
        return $this->belongsTo(OrderKot::class, 'order_kot_id');
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class, 'product_id');
    }

    public function product()
    {
        return $this->belongsTo(FoodItem::class, 'product_id');
    }
}
