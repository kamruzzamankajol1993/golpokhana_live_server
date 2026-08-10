<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'payroll_month' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_gross' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'total_net' => 'decimal:2',
        'total_paid' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function getMonthLabelAttribute(): string
    {
        return $this->payroll_month?->format('F Y') ?? '';
    }

    public function recalculateTotals(): void
    {
        $totals = $this->items()
            ->selectRaw('COUNT(*) AS total_employees')
            ->selectRaw('COALESCE(SUM(gross_salary), 0) AS total_gross')
            ->selectRaw('COALESCE(SUM(total_deduction), 0) AS total_deduction')
            ->selectRaw('COALESCE(SUM(net_salary), 0) AS total_net')
            ->first();

        $totalPaid = $this->items()
            ->where('payment_status', 'paid')
            ->sum('net_salary');

        $this->forceFill([
            'total_employees' => (int) ($totals->total_employees ?? 0),
            'total_gross' => (float) ($totals->total_gross ?? 0),
            'total_deduction' => (float) ($totals->total_deduction ?? 0),
            'total_net' => (float) ($totals->total_net ?? 0),
            'total_paid' => (float) $totalPaid,
        ])->save();
    }

    /**
     * Keep the run-level status compatible with the original batch workflow while
     * allowing individual employees to progress independently.
     *
     * - Any draft employee keeps the run in Draft.
     * - No drafts and at least one unpaid approved employee makes it Approved.
     * - Every employee paid makes it Paid.
     */
    public function syncWorkflowStatus(?int $actorId = null): void
    {
        if ($this->status === 'cancelled') {
            $this->recalculateTotals();
            return;
        }

        $counts = $this->items()
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_count")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count")
            ->first();

        $total = (int) ($counts->total_count ?? 0);
        $draft = (int) ($counts->draft_count ?? 0);
        $paid = (int) ($counts->paid_count ?? 0);

        $attributes = [];

        if ($total > 0 && $paid === $total) {
            $attributes['status'] = 'paid';
            $attributes['paid_by'] = $this->paid_by ?: $actorId;
            $attributes['paid_at'] = $this->paid_at ?: now();
            $attributes['approved_by'] = $this->approved_by ?: $actorId;
            $attributes['approved_at'] = $this->approved_at ?: now();
        } elseif ($total > 0 && $draft === 0) {
            $attributes['status'] = 'approved';
            $attributes['approved_by'] = $this->approved_by ?: $actorId;
            $attributes['approved_at'] = $this->approved_at ?: now();
            $attributes['paid_by'] = null;
            $attributes['paid_at'] = null;
        } else {
            $attributes['status'] = 'draft';
            $attributes['paid_by'] = null;
            $attributes['paid_at'] = null;
        }

        $this->forceFill($attributes)->save();
        $this->recalculateTotals();
    }
}
