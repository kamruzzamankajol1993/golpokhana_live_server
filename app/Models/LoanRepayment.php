<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];
    public function employeeLoan() { return $this->belongsTo(EmployeeLoan::class); }
    public function payrollItem() { return $this->belongsTo(PayrollItem::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
