<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'grace_minutes' => 'integer',
        'half_day_after_minutes' => 'integer',
        'absent_after_minutes' => 'integer',
        'minimum_overtime_minutes' => 'integer',
        'default_working_hours' => 'decimal:2',
        'weekly_off_days' => 'array',
        'allow_manual_attendance' => 'boolean',
        'auto_calculate_late' => 'boolean',
        'auto_calculate_overtime' => 'boolean',
        'status' => 'boolean',
    ];
}
