<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRecoveryAllocation extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'posted_at' => 'datetime'];
    public function payrollItem() { return $this->belongsTo(PayrollItem::class); }
}
