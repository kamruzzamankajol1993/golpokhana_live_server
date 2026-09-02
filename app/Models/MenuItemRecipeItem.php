<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class MenuItemRecipeItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'input_quantity' => 'decimal:8',
        'base_quantity' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Recipe item rows are immutable. Create a new recipe version instead.');
        });
        static::deleting(function () {
            throw new LogicException('Recipe item rows cannot be deleted from a saved version.');
        });
    }

    public function recipe()
    {
        return $this->belongsTo(MenuItemRecipe::class, 'menu_item_recipe_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function packageConversion()
    {
        return $this->belongsTo(IngredientUnitConversion::class, 'package_conversion_id');
    }

    public function inputUnit()
    {
        return $this->belongsTo(Unit::class, 'input_unit_id');
    }
}
