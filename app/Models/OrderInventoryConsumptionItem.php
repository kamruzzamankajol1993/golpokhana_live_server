<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class OrderInventoryConsumptionItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity_base' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Consumption item history is immutable.'));
        static::deleting(fn () => throw new LogicException('Consumption item history cannot be deleted.'));
    }

    public function consumption()
    {
        return $this->belongsTo(OrderInventoryConsumption::class, 'order_inventory_consumption_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderDetail::class, 'order_item_id');
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class, 'menu_item_id');
    }

    public function recipe()
    {
        return $this->belongsTo(MenuItemRecipe::class, 'recipe_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
