<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseVoucherItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:8',
        'conversion_factor_snapshot' => 'decimal:8',
        'base_quantity' => 'decimal:8',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    public function voucher()
    {
        return $this->belongsTo(PurchaseVoucher::class, 'purchase_voucher_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function packageConversion()
    {
        return $this->belongsTo(IngredientUnitConversion::class, 'package_conversion_id');
    }
}
