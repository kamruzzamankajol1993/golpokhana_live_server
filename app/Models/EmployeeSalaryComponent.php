<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryComponent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function salaryStructure()
    {
        return $this->belongsTo(EmployeeSalaryStructure::class, 'employee_salary_structure_id');
    }

    public function salaryComponent()
    {
        return $this->belongsTo(SalaryComponent::class);
    }

    /**
     * Resolve the value that is actually effective for this employee.
     * Global-mode rows intentionally store zero locally; their real value
     * comes from the salary component master setting.
     */
    public function getEffectiveAmountAttribute(): float
    {
        if (($this->rule_mode ?: 'custom') === 'global' && $this->salaryComponent) {
            return (float) $this->salaryComponent->default_amount;
        }

        return (float) $this->amount;
    }

    public function getEffectivePercentageAttribute(): float
    {
        if (($this->rule_mode ?: 'custom') === 'global' && $this->salaryComponent) {
            return (float) $this->salaryComponent->default_percentage;
        }

        return (float) $this->percentage;
    }

    public function getUsesGlobalRuleAttribute(): bool
    {
        return ($this->rule_mode ?: 'custom') === 'global';
    }
}
