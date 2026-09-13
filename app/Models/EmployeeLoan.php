<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLoan extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'loan_date' => 'date', 'repayment_start_month' => 'date',
        'principal_amount' => 'decimal:2', 'interest_value' => 'decimal:4',
        'total_interest' => 'decimal:2', 'total_payable' => 'decimal:2', 'installment_amount' => 'decimal:2',
        'number_of_installments' => 'integer',
    ];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function repayments() { return $this->hasMany(LoanRepayment::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function getRepaidAmountAttribute(): float { return round((float) ($this->repayments_sum_amount ?? $this->repayments()->sum('amount')), 2); }
    public function getOutstandingAmountAttribute(): float { return max(0, round((float) $this->total_payable - $this->repaid_amount, 2)); }
}
