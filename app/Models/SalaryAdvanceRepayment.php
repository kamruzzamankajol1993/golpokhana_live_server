<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryAdvanceRepayment extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];
    public function salaryAdvance() { return $this->belongsTo(SalaryAdvance::class); }
    public function payrollItem() { return $this->belongsTo(PayrollItem::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
