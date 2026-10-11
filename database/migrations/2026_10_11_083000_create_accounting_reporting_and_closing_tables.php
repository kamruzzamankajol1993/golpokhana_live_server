<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_cutovers', function (Blueprint $t) {
            $t->id(); $t->date('starts_on')->unique('acct_cutover_start_uq');
            $t->string('status',20)->default('PREPARED');
            $t->string('evidence_reference',250); $t->text('notes')->nullable();
            $t->foreignId('opening_journal_entry_id')->unique('acct_cutover_journal_uq')
                ->constrained('journal_entries','id','acct_cutover_journal_fk')->restrictOnDelete();
            $t->unsignedBigInteger('created_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->timestamps();
        });
        Schema::create('accounting_opening_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('cutover_id')->constrained('accounting_cutovers','id','acct_open_item_cutover_fk')->restrictOnDelete();
            $t->string('side',2); // AR or AP, never journal entry by itself
            $t->unsignedBigInteger('party_id')->nullable(); $t->string('invoice_reference',150);
            $t->date('due_on'); $t->decimal('amount',18,4); $t->timestamps();
            $t->unique(['cutover_id','side','invoice_reference'],'acct_open_item_ref_uq');
        });
        Schema::create('accounting_opening_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('opening_item_id')->constrained('accounting_opening_items','id','acct_open_alloc_item_fk')->restrictOnDelete();
            $t->foreignId('journal_entry_id')->constrained('journal_entries','id','acct_open_alloc_journal_fk')->restrictOnDelete();
            $t->decimal('amount',18,4); $t->string('evidence',250);
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
            $t->unique(['opening_item_id','journal_entry_id'],'acct_open_alloc_ref_uq');
        });
        Schema::create('accounting_budgets', function (Blueprint $t) {
            $t->id(); $t->foreignId('fiscal_year_id')->constrained('fiscal_years','id','acct_budget_year_fk')->restrictOnDelete();
            $t->string('name',150); $t->string('status',20)->default('DRAFT');
            $t->unsignedBigInteger('created_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->timestamps();
            $t->unique(['fiscal_year_id','name'],'acct_budget_year_name_uq');
        });
        Schema::create('accounting_budget_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('budget_id')->constrained('accounting_budgets','id','acct_budget_line_budget_fk')->restrictOnDelete();
            $t->foreignId('period_id')->constrained('accounting_periods','id','acct_budget_line_period_fk')->restrictOnDelete();
            $t->foreignId('account_id')->constrained('chart_of_accounts','id','acct_budget_line_account_fk')->restrictOnDelete();
            $t->foreignId('cost_center_id')->nullable()->constrained('cost_centers','id','acct_budget_line_center_fk')->restrictOnDelete();
            $t->decimal('amount',18,4); $t->string('notes',500)->nullable(); $t->timestamps();
            $t->index(['period_id','account_id'],'acct_budget_line_lookup_idx');
        });
        Schema::create('accounting_cash_flow_rules', function (Blueprint $t) {
            $t->id(); $t->foreignId('account_id')->unique('acct_cf_account_uq')
                ->constrained('chart_of_accounts','id','acct_cf_account_fk')->restrictOnDelete();
            $t->string('activity',18); // CASH, OPERATING, INVESTING, FINANCING
            $t->unsignedBigInteger('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('accounting_tax_report_accounts', function (Blueprint $t) {
            $t->id(); $t->foreignId('account_id')->unique('acct_tax_report_account_uq')
                ->constrained('chart_of_accounts','id','acct_tax_report_account_fk')->restrictOnDelete();
            $t->string('tax_type',12); $t->timestamps();
        });
        Schema::create('accounting_closing_runs', function (Blueprint $t) {
            $t->id(); $t->string('run_key',48)->unique('acct_close_run_key_uq');
            $t->foreignId('fiscal_year_id')->constrained('fiscal_years','id','acct_close_year_fk')->restrictOnDelete();
            $t->foreignId('accounting_period_id')->nullable()
                ->constrained('accounting_periods','id','acct_close_period_fk')->restrictOnDelete();
            $t->string('run_type',20); // MONTH_END, YEAR_END
            $t->string('status',20)->default('REVIEW');
            $t->foreignId('journal_entry_id')->nullable()->unique('acct_close_journal_uq')
                ->constrained('journal_entries','id','acct_close_journal_fk')->restrictOnDelete();
            $t->unsignedBigInteger('reviewed_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->text('review_note')->nullable(); $t->timestamps();
        });
        Schema::create('accounting_closing_check_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('closing_run_id')->constrained('accounting_closing_runs','id','acct_close_check_run_fk')->cascadeOnDelete();
            $t->string('check_key',60); $t->string('severity',12); $t->string('status',12);
            $t->string('summary',250); $t->text('detail')->nullable(); $t->timestamps();
            $t->unique(['closing_run_id','check_key'],'acct_close_check_unique');
        });
    }
    public function down(): void
    {
        foreach (['accounting_closing_check_items','accounting_closing_runs','accounting_tax_report_accounts',
            'accounting_cash_flow_rules','accounting_budget_lines','accounting_budgets',
            'accounting_opening_allocations','accounting_opening_items','accounting_cutovers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
