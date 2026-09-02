<?php

namespace App\Services;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Tables whose lifecycle is important enough to keep a model-level audit trail.
     * Very small lookup/pivot tables are intentionally omitted to avoid noise.
     */
    private const AUDITED_TABLES = [
        'users', 'branches',
        'restaurant_settings', 'tax_settings', 'invoice_settings', 'pos_settings',
        'hr_settings', 'attendance_settings', 'payroll_settings', 'reward_point_settings',
        'zones', 'tables', 'table_bookings', 'waiters', 'food_categories', 'food_items',
        'orders', 'order_kots', 'order_due_payments', 'pos_sessions', 'pos_deleted_item_histories', 'offline_pos_devices',
        'employees', 'shift_rosters', 'attendances', 'leave_requests',
        'employee_salary_structures', 'payroll_runs', 'payroll_items', 'payroll_payments',
        'employee_branch_transfers',
    ];

    private const HIDDEN_KEYS = [
        'password', 'remember_token', 'api_token', 'token', 'secret', 'api_key',
    ];

    public function logModel(string $event, Model $model): void
    {
        if (!in_array($model->getTable(), self::AUDITED_TABLES, true) || !$this->available()) {
            return;
        }

        if ($event === 'updated') {
            $changed = Arr::except($model->getChanges(), ['updated_at']);
            if ($changed === []) {
                return;
            }
            $keys = array_keys($changed);
            $before = Arr::only($model->getOriginal(), $keys);
            $after = Arr::only($model->getAttributes(), $keys);
        } elseif ($event === 'created') {
            $before = null;
            $after = $model->getAttributes();
        } else {
            $before = $model->getAttributes();
            $after = null;
        }

        $branchId = $model->getAttribute('branch_id');
        if (!$branchId && app(BranchContext::class)->isResolved()) {
            $branchId = app(BranchContext::class)->branchId();
        }

        $this->log(
            action: Str::singular($model->getTable()) . '.' . $event,
            branchId: $branchId ? (int) $branchId : null,
            description: ucfirst($event) . ' ' . class_basename($model) . ' #' . $model->getKey(),
            before: $before,
            after: $after,
            auditableType: get_class($model),
            auditableId: $model->getKey() ? (int) $model->getKey() : null,
            metadata: ['table' => $model->getTable()]
        );
    }

    public function log(
        string $action,
        ?int $branchId = null,
        ?string $description = null,
        ?array $before = null,
        ?array $after = null,
        ?string $auditableType = null,
        ?int $auditableId = null,
        array $metadata = []
    ): void {
        if (!$this->available()) {
            return;
        }

        $request = app()->bound('request') ? request() : null;
        $userId = Auth::id();

        try {
            DB::table('audit_logs')->insert([
                'branch_id' => $branchId,
                'user_id' => $userId,
                'action' => Str::limit($action, 80, ''),
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'description' => $description ? Str::limit($description, 500, '') : null,
                'before_values' => $before ? json_encode($this->sanitize($before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'after_values' => $after ? json_encode($this->sanitize($after), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'metadata' => $metadata ? json_encode($this->sanitize($metadata), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'route_name' => $request?->route()?->getName(),
                'http_method' => $request?->method(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 1000, '') : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit failure must not break a POS/HR/branch business transaction.
            // Production monitoring can surface the warning while the original request continues.
            logger()->warning('Branch audit log write failed.', ['message' => $e->getMessage(), 'action' => $action]);
        }
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable('audit_logs');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::HIDDEN_KEYS, true)) {
                $values[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }
}
