<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'days_per_year' => 'decimal:2',
        'is_paid' => 'boolean',
        'allow_carry_forward' => 'boolean',
        'max_carry_forward_days' => 'decimal:2',
        'requires_document' => 'boolean',
        'status' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function employeeBalances()
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }
}
