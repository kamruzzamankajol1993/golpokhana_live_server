<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->boolean('allow_non_current_month_payroll')
                ->default(false)
                ->after('lock_paid_payroll');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->enum('status', ['draft', 'approved', 'paid'])
                ->default('draft')
                ->after('payment_status');
            $table->foreignId('approved_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->index(['payroll_run_id', 'status'], 'payroll_run_item_status_index');
        });

        // Preserve the workflow state of payroll data created before this migration.
        DB::table('payroll_items')
            ->where('payment_status', 'paid')
            ->update(['status' => 'paid']);

        $approvedRunIds = DB::table('payroll_runs')
            ->whereIn('status', ['approved', 'paid'])
            ->pluck('id');

        if ($approvedRunIds->isNotEmpty()) {
            DB::table('payroll_items')
                ->whereIn('payroll_run_id', $approvedRunIds)
                ->where('payment_status', 'unpaid')
                ->update(['status' => 'approved']);
        }
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropIndex('payroll_run_item_status_index');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at']);
        });

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn('allow_non_current_month_payroll');
        });
    }
};
