<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_loans')) {
            return;
        }

        if (! Schema::hasColumn('employee_loans', 'number_of_installments')) {
            Schema::table('employee_loans', function (Blueprint $table) {
                $table->unsignedSmallInteger('number_of_installments')
                    ->nullable()
                    ->after('repayment_start_month');
            });
        }

        DB::table('employee_loans')
            ->whereNull('number_of_installments')
            ->orderBy('id')
            ->get(['id', 'total_payable', 'installment_amount'])
            ->each(function ($loan) {
                $total = max(0, (float) $loan->total_payable);
                $installment = (float) ($loan->installment_amount ?? 0);

                $count = $installment > 0
                    ? max(1, (int) ceil($total / $installment))
                    : 1;

                $updates = ['number_of_installments' => min($count, 120)];

                // Legacy loans allowed an empty installment to mean "recover full balance".
                // Keep that meaning, but store the now-explicit auto monthly installment.
                if ($installment <= 0) {
                    $updates['installment_amount'] = round($total, 2);
                }

                DB::table('employee_loans')
                    ->where('id', $loan->id)
                    ->update($updates);
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_loans') && Schema::hasColumn('employee_loans', 'number_of_installments')) {
            Schema::table('employee_loans', function (Blueprint $table) {
                $table->dropColumn('number_of_installments');
            });
        }
    }
};
