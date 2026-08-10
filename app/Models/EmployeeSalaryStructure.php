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

        foreach ($this->components as $component) {
            if (!$component->is_active || $component->component_type !== 'earning') {
                continue;
            }

            if ($component->calculation_type === 'fixed') {
                $gross += (float) $component->amount;
            } elseif ($component->calculation_type === 'percentage') {
                $gross += ((float) $this->basic_salary * (float) $component->percentage) / 100;
            }
        }

        return round($gross, 2);
    }
}
