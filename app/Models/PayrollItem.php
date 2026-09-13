<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'joining_date' => 'date', 'exit_date' => 'date', 'salary_divisor' => 'decimal:2',
        'payable_days' => 'decimal:2', 'basic_salary' => 'decimal:2', 'prorated_basic_salary' => 'decimal:2',
        'salary_total' => 'decimal:2', 'allowance_total' => 'decimal:2', 'gross_salary' => 'decimal:2',
        'total_deduction' => 'decimal:2', 'net_salary' => 'decimal:2', 'present_days' => 'decimal:2',
        'late_days' => 'decimal:2', 'absent_days' => 'decimal:2', 'half_days' => 'decimal:2',
        'paid_leave_days' => 'decimal:2', 'unpaid_leave_days' => 'decimal:2', 'off_days' => 'decimal:2',
        'overtime_minutes' => 'integer', 'attendance_summary' => 'array', 'approved_at' => 'datetime',
    ];

    public function payrollRun() { return $this->belongsTo(PayrollRun::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function salaryStructure() { return $this->belongsTo(EmployeeSalaryStructure::class, 'employee_salary_structure_id'); }
    public function components() { return $this->hasMany(PayrollItemComponent::class)->orderBy('component_group')->orderBy('sort_order')->orderBy('id'); }
    public function recoveryAllocations() { return $this->hasMany(PayrollRecoveryAllocation::class); }
    public function payment() { return $this->hasOne(PayrollPayment::class); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isPaid(): bool { return $this->status === 'paid'; }

    public function recalculateFromComponents(bool $allowNegative = false): void
    {
        $salary = (float) $this->components()->where('component_group', 'salary')->sum('amount');
        $allowance = (float) $this->components()->where('component_group', 'allowance')->sum('amount');
        $deduction = (float) $this->components()->where('component_group', 'deduction')->sum('amount');
        $gross = $salary + $allowance;
        $net = $gross - $deduction;
        if (!$allowNegative) $net = max(0, $net);

        $this->forceFill([
            'salary_total' => round($salary, 2), 'allowance_total' => round($allowance, 2),
            'gross_salary' => round($gross, 2), 'total_deduction' => round($deduction, 2),
            'net_salary' => round($net, 2),
        ])->save();
    }
}
