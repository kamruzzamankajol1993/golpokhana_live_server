<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    use HasFactory;

    public const TYPE_EARNING = 'earning';
    public const TYPE_DEDUCTION = 'deduction';
    public const GROUP_SALARY = 'salary';
    public const GROUP_ALLOWANCE = 'allowance';
    public const GROUP_DEDUCTION = 'deduction';

    public const CALCULATION_FIXED = 'fixed';
    public const CALCULATION_PERCENTAGE = 'percentage';
    public const CALCULATION_MANUAL = 'manual';

    protected $guarded = [];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'default_percentage' => 'decimal:4',
        'global_configured' => 'boolean',
        'apply_to_all' => 'boolean',
        'allow_employee_override' => 'boolean',
        'show_zero_on_payslip' => 'boolean',
        'is_taxable' => 'boolean',
        'is_required' => 'boolean',
        'status' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function employeeSalaryComponents() { return $this->hasMany(EmployeeSalaryComponent::class); }

    public function getDisplayGroupAttribute(): string
    {
        return $this->component_group ?: ($this->type === self::TYPE_DEDUCTION ? self::GROUP_DEDUCTION : self::GROUP_SALARY);
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->payslip_label ?: $this->name;
    }
}
