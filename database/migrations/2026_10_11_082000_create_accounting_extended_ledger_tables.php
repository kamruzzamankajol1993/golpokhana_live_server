<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Company-wide analytical tags (not branch ledgers).
        Schema::create('cost_centers', function(Blueprint $t) {
            $t->id(); $t->string('code',32)->unique(); $t->string('name',120);
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::table('journal_lines', function(Blueprint $t) {
            $t->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
        });
        Schema::create('cost_center_allocations', function(Blueprint $t) {
            $t->id(); $t->foreignId('journal_line_id')->constrained('journal_lines')->cascadeOnDelete();
            $t->foreignId('cost_center_id')->constrained('cost_centers')->restrictOnDelete();
            $t->decimal('percentage',7,4); $t->timestamps();
            $t->unique(['journal_line_id','cost_center_id'],'acct_cost_alloc_unique');
        });
        Schema::create('payment_terms', function(Blueprint $t) {
            $t->id(); $t->string('name',120)->unique(); $t->unsignedSmallInteger('days_due')->default(0);
            $t->unsignedSmallInteger('grace_days')->default(0); $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('accounting_due_terms', function(Blueprint $t) {
            $t->id(); $t->string('source_type',25); $t->unsignedBigInteger('source_id');
            $t->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $t->date('due_date'); $t->string('followup_status',30)->default('OPEN');
            $t->date('next_followup_on')->nullable(); $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps(); $t->unique(['source_type','source_id'],'acct_due_terms_source_unique');
        });
        Schema::create('payment_followups', function(Blueprint $t) {
            $t->id(); $t->foreignId('accounting_due_term_id')->constrained('accounting_due_terms')->restrictOnDelete();
            $t->string('stage',30); $t->date('promise_to_pay_on')->nullable();
            $t->date('next_followup_on')->nullable(); $t->text('note');
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('bank_statement_lines', function(Blueprint $t) {
            $t->id(); $t->foreignId('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $t->date('statement_date'); $t->string('statement_reference',180);
            $t->string('direction',6); $t->decimal('amount',18,4);
            $t->text('description')->nullable(); $t->string('status',22)->default('UNMATCHED');
            $t->foreignId('matched_journal_entry_id')->nullable()->unique('acct_bank_matched_je_uq')
                ->constrained('journal_entries','id','acct_bank_matched_je_fk')->restrictOnDelete();
            $t->unsignedBigInteger('matched_by')->nullable(); $t->timestamp('matched_at')->nullable();
            $t->timestamps(); $t->unique(['payment_account_id','statement_reference','direction'],'acct_bank_statement_ref_uq');
        });
        Schema::create('accounting_employee_funding', function(Blueprint $t) {
            $t->id(); $t->string('source_type',28); $t->unsignedBigInteger('source_id');
            $t->date('paid_on'); $t->string('method',20); $t->string('reference',150);
            $t->decimal('amount',18,4); $t->string('evidence',1000);
            $t->foreignId('accounting_event_id')->nullable()->unique('acct_emp_funding_event_uq')
                ->constrained('accounting_events','id','acct_emp_funding_event_fk')->nullOnDelete();
            $t->unsignedBigInteger('verified_by'); $t->timestamps();
            $t->unique(['source_type','source_id'],'acct_emp_funding_source_uq');
        });
        Schema::create('accounting_employee_repayment_verifications', function(Blueprint $t) {
            $t->id(); $t->string('source_type',35); $t->unsignedBigInteger('source_id');
            $t->date('received_on'); $t->string('method',20); $t->string('reference',150);
            $t->decimal('principal',18,4); $t->decimal('interest',18,4)->default(0);
            $t->text('evidence'); $t->unsignedBigInteger('verified_by');
            $t->foreignId('accounting_event_id')->unique('acct_emp_repay_event_uq')
                ->constrained('accounting_events','id','acct_emp_repay_event_fk')->restrictOnDelete();
            $t->timestamps(); $t->unique(['source_type','source_id'],'acct_emp_repay_source_uq');
        });
        Schema::create('accounting_deferral_schedules', function(Blueprint $t) {
            $t->id(); $t->string('type',20); $t->string('name',160);
            $t->foreignId('source_journal_entry_id')->constrained('journal_entries','id','acct_def_source_fk')->restrictOnDelete();
            $t->foreignId('balance_account_id')->constrained('chart_of_accounts','id','acct_def_balance_fk')->restrictOnDelete();
            $t->foreignId('recognition_account_id')->constrained('chart_of_accounts','id','acct_def_recognize_fk')->restrictOnDelete();
            $t->decimal('total_amount',18,4); $t->date('start_month');
            $t->unsignedSmallInteger('months'); $t->unsignedBigInteger('created_by')->nullable();
            $t->string('status',18)->default('ACTIVE'); $t->timestamps();
        });
        Schema::create('accounting_deferral_lines', function(Blueprint $t) {
            $t->id(); $t->foreignId('schedule_id')->constrained('accounting_deferral_schedules','id','acct_def_line_schedule_fk')->restrictOnDelete();
            $t->date('period_month'); $t->decimal('amount',18,4);
            $t->foreignId('journal_entry_id')->nullable()->unique('acct_def_line_journal_uq')
                ->constrained('journal_entries','id','acct_def_line_journal_fk')->restrictOnDelete();
            $t->timestamps(); $t->unique(['schedule_id','period_month'],'acct_def_line_period_uq');
        });
        Schema::create('accounting_asset_policies', function(Blueprint $t) {
            $t->id(); $t->boolean('is_approved')->default(false); $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->text('approval_reference')->nullable(); $t->timestamps();
        });
        Schema::create('asset_categories', function(Blueprint $t) {
            $t->id(); $t->string('name',120)->unique(); $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('fixed_assets', function(Blueprint $t) {
            $t->id(); $t->foreignId('asset_category_id')->constrained('asset_categories','id','acct_asset_category_fk')->restrictOnDelete();
            $t->string('code',60)->unique(); $t->string('name',180);
            $t->foreignId('source_journal_entry_id')->nullable()->constrained('journal_entries','id','acct_asset_source_fk')->restrictOnDelete();
            $t->foreignId('asset_account_id')->constrained('chart_of_accounts','id','acct_asset_gl_fk')->restrictOnDelete();
            $t->foreignId('accumulated_depreciation_account_id')->constrained('chart_of_accounts','id','acct_asset_accum_fk')->restrictOnDelete();
            $t->foreignId('depreciation_expense_account_id')->constrained('chart_of_accounts','id','acct_asset_dep_exp_fk')->restrictOnDelete();
            $t->decimal('cost',18,4); $t->decimal('residual_value',18,4)->default(0);
            $t->unsignedSmallInteger('useful_life_months'); $t->date('in_service_on');
            $t->string('status',20)->default('ACTIVE'); $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('asset_depreciations', function(Blueprint $t) {
            $t->id(); $t->foreignId('fixed_asset_id')->constrained('fixed_assets','id','acct_dep_asset_fk')->restrictOnDelete();
            $t->date('period_month'); $t->decimal('amount',18,4);
            $t->foreignId('journal_entry_id')->nullable()->unique('acct_dep_journal_uq')
                ->constrained('journal_entries','id','acct_dep_journal_fk')->restrictOnDelete();
            $t->timestamps(); $t->unique(['fixed_asset_id','period_month'],'acct_dep_asset_period_uq');
        });
        Schema::create('company_loans', function(Blueprint $t) {
            $t->id(); $t->string('reference',80)->unique(); $t->string('lender',180);
            $t->foreignId('liability_account_id')->constrained('chart_of_accounts','id','acct_loan_liability_fk')->restrictOnDelete();
            $t->foreignId('funding_journal_entry_id')->constrained('journal_entries','id','acct_loan_funding_fk')->restrictOnDelete();
            $t->decimal('principal',18,4); $t->date('received_on'); $t->date('maturity_on')->nullable();
            $t->decimal('annual_interest_rate',8,4)->default(0); $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('company_loan_payments', function(Blueprint $t) {
            $t->id(); $t->foreignId('company_loan_id')->constrained('company_loans','id','acct_loan_payment_loan_fk')->restrictOnDelete();
            $t->date('paid_on'); $t->decimal('principal',18,4); $t->decimal('interest',18,4)->default(0);
            $t->string('reference',120); $t->foreignId('journal_entry_id')->unique('acct_loan_payment_journal_uq')
                ->constrained('journal_entries','id','acct_loan_payment_journal_fk')->restrictOnDelete();
            $t->timestamps(); $t->unique(['company_loan_id','reference'],'acct_loan_payment_ref_uq');
        });
    }
    public function down(): void
    {
        foreach (['company_loan_payments','company_loans','asset_depreciations','fixed_assets','asset_categories','accounting_asset_policies',
            'accounting_deferral_lines','accounting_deferral_schedules','accounting_employee_repayment_verifications',
            'accounting_employee_funding','bank_statement_lines',
            'payment_followups','accounting_due_terms','payment_terms','cost_center_allocations'] as $table) Schema::dropIfExists($table);
        Schema::table('journal_lines',fn(Blueprint $t)=>$t->dropConstrainedForeignId('cost_center_id'));
        Schema::dropIfExists('cost_centers');
    }
};
