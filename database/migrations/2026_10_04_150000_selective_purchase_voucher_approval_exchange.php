<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_voucher_approvals')) {
            return;
        }

        Schema::table('purchase_voucher_approvals', function (Blueprint $table) {
            try {
                $table->dropUnique('purchase_voucher_approvals_revision_user_unique');
            } catch (\Throwable $e) {
                // The index may already be absent on a partially-updated installation.
            }
        });

        Schema::table('purchase_voucher_approvals', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_voucher_approvals', 'batch_no')) {
                $table->unsignedInteger('batch_no')->default(1)->after('approval_order');
            }
            if (!Schema::hasColumn('purchase_voucher_approvals', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()->after('batch_no')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchase_voucher_approvals', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            }
            if (!Schema::hasColumn('purchase_voucher_approvals', 'dispatch_note')) {
                $table->text('dispatch_note')->nullable()->after('assigned_at');
            }
        });

        DB::table('purchase_voucher_approvals')
            ->whereNull('assigned_at')
            ->update([
                'assigned_at' => DB::raw('created_at'),
                'dispatch_note' => DB::raw("COALESCE(dispatch_note, 'Legacy approval assignment')"),
            ]);

        try {
            Schema::table('purchase_voucher_approvals', function (Blueprint $table) {
                $table->index(
                    ['purchase_voucher_id', 'revision_no', 'batch_no'],
                    'purchase_voucher_approvals_batch_idx'
                );
            });
        } catch (\Throwable $e) {
            // Safe for environments where the migration was partially applied.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_voucher_approvals')) {
            return;
        }

        Schema::table('purchase_voucher_approvals', function (Blueprint $table) {
            try {
                $table->dropIndex('purchase_voucher_approvals_batch_idx');
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('purchase_voucher_approvals', 'assigned_by')) {
                try {
                    $table->dropForeign(['assigned_by']);
                } catch (\Throwable $e) {
                }
            }
        });

        Schema::table('purchase_voucher_approvals', function (Blueprint $table) {
            $columns = [];
            foreach (['dispatch_note', 'assigned_at', 'assigned_by', 'batch_no'] as $column) {
                if (Schema::hasColumn('purchase_voucher_approvals', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
