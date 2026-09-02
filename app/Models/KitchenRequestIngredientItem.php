<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenRequestIngredientItem extends Model
{
    use HasFactory;

    public const SOURCE_FOOD = 'FOOD';
    public const SOURCE_DIRECT = 'DIRECT';
    public const SOURCE_MIXED = 'MIXED';

    protected $guarded = [];

    protected $casts = [
        'input_quantity' => 'decimal:8',
        'conversion_factor_snapshot' => 'decimal:8',
        'required_base_qty' => 'decimal:8',
        'approved_base_qty' => 'decimal:8',
        'issued_base_qty' => 'decimal:8',
    ];

    public function request()
    {
        return $this->belongsTo(KitchenRequest::class, 'kitchen_request_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function packageConversion()
    {
        return $this->belongsTo(IngredientUnitConversion::class, 'package_conversion_id');
    }

    public function displayUnit()
    {
        return $this->belongsTo(Unit::class, 'display_unit_id');
    }
}
