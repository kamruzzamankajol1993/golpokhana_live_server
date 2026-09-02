<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorIngredient extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'last_price' => 'decimal:4',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
