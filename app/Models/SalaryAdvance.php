<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryAdvance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'advance_date' => 'date',
        'recovery_start_month' => 'date',
        'amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function repayments() { return $this->hasMany(SalaryAdvanceRepayment::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    public function getRecoveredAmountAttribute(): float
    {
        return round((float) ($this->repayments_sum_amount ?? $this->repayments()->sum('amount')), 2);
    }

    public function getOutstandingAmountAttribute(): float
    {
        return max(0, round((float) $this->amount - $this->recovered_amount, 2));
    }
}
