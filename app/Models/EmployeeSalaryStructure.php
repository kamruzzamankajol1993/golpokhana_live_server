<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryStructure extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'basic_salary' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function components()
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeCurrent($query, $date = null)
    {
        $date = $date ?: now()->toDateString();

        return $query
            ->where('effective_from', '<=', $date)
            ->where(function ($builder) use ($date) {
                $builder->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->where('status', true);
    }

    public function getEstimatedGrossAttribute(): float
    {
        $gross = (float) $this->basic_salary;

        // Salary/allowance rows saved with rule_mode=global keep their local
        // amount/percentage as zero. For display calculations we must resolve
        // the current global master value instead of showing those zeros.
        foreach ($this->components as $component) {
            if (!$component->is_active
                || $component->component_type !== 'earning'
                || ($component->rule_mode ?: 'custom') === 'disabled') {
                continue;
            }

            $master = $component->salaryComponent;
            $ruleCode = strtolower((string) ($master?->rule_code ?: 'standard'));

            // Event-based values such as Day-Off/Gov-Off OT are hourly and
            // cannot be estimated until attendance exists, so do not add a
            // one-hour rate into monthly gross.
            if ($ruleCode !== 'standard') {
                continue;
            }

            // A global rule that is not configured should not affect the
            // estimate until HR configures it.
            if (($component->rule_mode ?: 'custom') === 'global' && $master && !$master->global_configured) {
                continue;
            }

            if ($component->calculation_type === 'fixed') {
                $gross += (float) $component->effective_amount;
            } elseif ($component->calculation_type === 'percentage') {
                $gross += ((float) $this->basic_salary * (float) $component->effective_percentage) / 100;
            }
        }

        return round($gross, 2);
    }
}
