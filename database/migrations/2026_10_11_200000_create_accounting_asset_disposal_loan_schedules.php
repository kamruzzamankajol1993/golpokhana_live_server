<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not fully transactional. A previous failed migration may
        // already have created one or more of these tables.
        if (! Schema::hasTable('accounting_asset_disposals')) {
            Schema::create('accounting_asset_disposals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->restrictOnDelete();
                $table->foreignId('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
                $table->string('disposal_type', 12);
                $table->date('disposed_on');
                $table->unsignedBigInteger('cash_account_id')->nullable();
                $table->unsignedBigInteger('gain_loss_account_id')->nullable();
                $table->decimal('sale_proceeds', 18, 4)->default(0);
                $table->decimal('accumulated_depreciation', 18, 4)->default(0);
                $table->decimal('book_value', 18, 4);
                $table->decimal('gain_loss', 18, 4)->default(0);
                $table->string('status', 20)->default('PENDING');
                $table->string('reference', 120);
                $table->text('reason');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['fixed_asset_id', 'status']);
            });
        }

        if (! Schema::hasTable('company_loan_installments')) {
            Schema::create('company_loan_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_loan_id')->constrained('company_loans')->restrictOnDelete();
                $table->unsignedSmallInteger('installment_no');
                $table->date('due_date');
                $table->decimal('principal_due', 18, 4);
                $table->decimal('interest_due', 18, 4)->default(0);
                $table->timestamps();
                $table->unique(['company_loan_id', 'installment_no'], 'acct_loan_installment_number_uq');
            });
        }

        if (! Schema::hasTable('company_loan_installment_allocations')) {
            Schema::create('company_loan_installment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('installment_id');
                $table->foreignId('company_loan_payment_id');
                $table->decimal('principal_amount', 18, 4);
                $table->decimal('interest_amount', 18, 4)->default(0);
                $table->timestamps();

                // MySQL identifier names must be 64 characters or shorter.
                $table->unique('company_loan_payment_id', 'acct_loan_alloc_payment_uq');
                $table->foreign('installment_id', 'acct_loan_alloc_inst_fk')
                    ->references('id')->on('company_loan_installments')->restrictOnDelete();
                $table->foreign('company_loan_payment_id', 'acct_loan_alloc_payment_fk')
                    ->references('id')->on('company_loan_payments')->restrictOnDelete();
            });
        }

        // On a failed prior run, the allocations table can already exist but
        // its final FK was never created. Repair only the missing constraints.
        if (! $this->hasSingleColumnUniqueIndex('company_loan_installment_allocations', 'company_loan_payment_id')) {
            Schema::table('company_loan_installment_allocations', function (Blueprint $table) {
                $table->unique('company_loan_payment_id', 'acct_loan_alloc_payment_uq');
            });
        }

        if (! $this->hasForeignKey('company_loan_installment_allocations', 'installment_id', 'company_loan_installments')) {
            Schema::table('company_loan_installment_allocations', function (Blueprint $table) {
                $table->foreign('installment_id', 'acct_loan_alloc_inst_fk')
                    ->references('id')->on('company_loan_installments')->restrictOnDelete();
            });
        }

        if (! $this->hasForeignKey('company_loan_installment_allocations', 'company_loan_payment_id', 'company_loan_payments')) {
            Schema::table('company_loan_installment_allocations', function (Blueprint $table) {
                $table->foreign('company_loan_payment_id', 'acct_loan_alloc_payment_fk')
                    ->references('id')->on('company_loan_payments')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_loan_installment_allocations');
        Schema::dropIfExists('company_loan_installments');
        Schema::dropIfExists('accounting_asset_disposals');
    }

    private function hasForeignKey(string $table, string $column, string $referencedTable): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            . 'AND REFERENCED_TABLE_NAME = ? LIMIT 1',
            [$table, $column, $referencedTable]
        ) !== null;
    }

    private function hasSingleColumnUniqueIndex(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 '
            . 'GROUP BY INDEX_NAME '
            . 'HAVING COUNT(*) = 1 AND SUM(CASE WHEN COLUMN_NAME = ? THEN 1 ELSE 0 END) = 1 '
            . 'LIMIT 1',
            [$table, $column]
        ) !== null;
    }
};
