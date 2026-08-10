<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'break_minutes' => 'integer',
        'grace_minutes' => 'integer',
        'is_overnight' => 'boolean',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function waiters()
    {
        return $this->hasMany(Waiter::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'default_shift_id');
    }

    public function rosters()
    {
        return $this->hasMany(ShiftRoster::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function getTimeRangeAttribute(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return 'Time not configured';
        }

        return date('h:i A', strtotime($this->start_time)) . ' - ' . date('h:i A', strtotime($this->end_time));
    }
}
