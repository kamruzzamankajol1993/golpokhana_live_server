<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    public const DIMENSION_WEIGHT = 'WEIGHT';
    public const DIMENSION_VOLUME = 'VOLUME';
    public const DIMENSION_COUNT = 'COUNT';
    public const DIMENSION_PACKAGE = 'PACKAGE';

    protected $guarded = [];

    protected $casts = [
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'standard_to_base_factor' => 'decimal:8',
    ];

    public static function dimensions(): array
    {
        return [
            self::DIMENSION_WEIGHT,
            self::DIMENSION_VOLUME,
            self::DIMENSION_COUNT,
            self::DIMENSION_PACKAGE,
        ];
    }

    public function ingredientsAsBase()
    {
        return $this->hasMany(Ingredient::class, 'base_unit_id');
    }

    public function ingredientConversions()
    {
        return $this->hasMany(IngredientUnitConversion::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
