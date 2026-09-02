<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenRequestFoodItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'requested_food_qty' => 'decimal:8',
    ];

    public function request()
    {
        return $this->belongsTo(KitchenRequest::class, 'kitchen_request_id');
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class, 'menu_item_id');
    }

    public function recipe()
    {
        return $this->belongsTo(MenuItemRecipe::class, 'recipe_id');
    }
}
