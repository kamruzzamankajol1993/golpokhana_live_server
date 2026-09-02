<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryBalance extends Model
{
    use HasFactory, BelongsToBranch;

    protected $guarded = [];

    protected $casts = [
        'quantity_base' => 'decimal:8',
    ];

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
