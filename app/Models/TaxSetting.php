<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    // ডাটাবেসের 0 বা 1 কে true বা false হিসেবে পাওয়ার জন্য
    protected $casts = [
        'is_tax_included' => 'boolean',
        'vat_rate'        => 'decimal:2',
        'service_charge'  => 'decimal:2',
    ];
}
