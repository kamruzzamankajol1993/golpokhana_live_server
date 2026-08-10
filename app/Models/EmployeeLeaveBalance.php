<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'entitled_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'adjusted_days' => 'decimal:2',
        'year' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function getAvailableDaysAttribute(): float
    {
        return (float) $this->opening_balance
            + (float) $this->entitled_days
            + (float) $this->adjusted_days
            - (float) $this->used_days;
    }
}
