<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeWeeklyRosterPattern extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'day_of_week' => 'integer',
        'week_of_month' => 'integer',
        'shift_id' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
