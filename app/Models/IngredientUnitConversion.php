<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IngredientUnitConversion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'factor_to_base' => 'decimal:8',
        'purchase_allowed' => 'boolean',
        'recipe_allowed' => 'boolean',
        'effective_from' => 'date',
        'is_active' => 'boolean',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
