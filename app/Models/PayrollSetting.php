<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'salary_cycle_start_day' => 'integer',
        'salary_cycle_end_day' => 'integer',
        'default_working_days' => 'decimal:2',
        'overtime_rate_multiplier' => 'decimal:4',
        'half_day_deduction_percentage' => 'decimal:2',
        'late_count_threshold' => 'integer',
        'allow_negative_salary' => 'boolean',
        'lock_paid_payroll' => 'boolean',
        'allow_non_current_month_payroll' => 'boolean',
        'status' => 'boolean',
    ];
}
