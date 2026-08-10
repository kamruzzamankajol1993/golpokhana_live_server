<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'employee_code_next_number' => 'integer',
        'default_probation_months' => 'integer',
        'default_notice_period_days' => 'integer',
        'allow_employee_login' => 'boolean',
        'allow_waiter_access' => 'boolean',
        'status' => 'boolean',
    ];
}
