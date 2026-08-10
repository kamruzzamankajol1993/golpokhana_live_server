<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollItemComponent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'rate' => 'decimal:4',
        'quantity' => 'decimal:4',
        'calculated_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_manual' => 'boolean',
        'is_overridden' => 'boolean',
    ];

    public function payrollItem()
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function salaryComponent()
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
