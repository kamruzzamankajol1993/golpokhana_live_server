<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
        'low_stock_level_base' => 'decimal:8',
    ];

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function unitConversions()
    {
        return $this->hasMany(IngredientUnitConversion::class);
    }

    public function balances()
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function movementItems()
    {
        return $this->hasMany(StockMovementItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function recipeItems()
    {
        return $this->hasMany(MenuItemRecipeItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
