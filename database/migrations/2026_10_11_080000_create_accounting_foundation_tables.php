<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $t) {
            $t->id(); $t->string('code', 30)->unique(); $t->string('name', 150);
            $t->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $t->string('type', 30); $t->string('normal_balance', 6); $t->boolean('is_header')->default(false);
            $t->boolean('is_control')->default(false); $t->boolean('is_active')->default(true);
            $t->text('description')->nullable(); $t->timestamps();
        });
        Schema::create('accounting_journals', function (Blueprint $t) {
            $t->id(); $t->string('code', 12)->unique(); $t->string('name', 100);
            $t->string('type', 32); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('number_sequences', function (Blueprint $t) {
            $t->id(); $t->string('key', 50); $t->unsignedInteger('year');
            $t->unsignedBigInteger('next_number')->default(1); $t->timestamps(); $t->unique(['key','year']);
        });
        Schema::create('accounting_settings', function (Blueprint $t) {
            $t->id(); $t->boolean('capture_enabled')->default(false);
            $t->string('posting_mode', 12)->default('manual');
            $t->timestamp('activated_at')->nullable(); $t->date('locked_until')->nullable();
            $t->foreignId('rounding_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->string('valuation_method', 24)->default('weighted_average');
            $t->unsignedBigInteger('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('accounting_setting_histories', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('field', 60); $t->text('old_value')->nullable(); $t->text('new_value')->nullable();
            $t->string('reason', 500); $t->timestamp('changed_at')->useCurrent();
            $t->index(['field','changed_at']);
        });
        Schema::create('accounting_audit_logs', function (Blueprint $t) {
            $t->id(); $t->string('subject_type', 50); $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('action', 50); $t->unsignedBigInteger('actor_id')->nullable();
            $t->json('before_data')->nullable(); $t->json('after_data')->nullable();
            $t->string('reason', 500)->nullable(); $t->timestamps();
            $t->index(['subject_type','subject_id']);
        });
        Schema::create('fiscal_years', function (Blueprint $t) {
            $t->id(); $t->string('name', 60)->unique(); $t->date('starts_on'); $t->date('ends_on');
            $t->string('status', 16)->default('OPEN'); $t->timestamps();
        });
        Schema::create('accounting_periods', function (Blueprint $t) {
            $t->id(); $t->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $t->string('name', 35); $t->date('starts_on'); $t->date('ends_on');
            $t->string('status', 16)->default('OPEN'); $t->timestamps();
            $t->unique(['fiscal_year_id','starts_on']); $t->index(['starts_on','ends_on','status']);
        });
        Schema::create('period_close_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $t->string('old_status', 16); $t->string('new_status', 16); $t->text('reason');
            $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps();
        });
        Schema::create('account_mappings', function (Blueprint $t) {
            $t->id(); $t->string('source_type', 70); $t->string('event_type', 80);
            $t->string('name', 150); $t->foreignId('journal_id')->constrained('accounting_journals')->restrictOnDelete();
            $t->boolean('is_active')->default(false); $t->boolean('requires_approval')->default(true); $t->timestamps();
            $t->unique(['source_type','event_type']);
        });
        Schema::create('account_mapping_rules', function (Blueprint $t) {
            $t->id(); $t->foreignId('mapping_id')->constrained('account_mappings')->cascadeOnDelete();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->string('role', 70); $t->string('direction', 6);
            $t->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->string('amount_key', 80); $t->timestamps();
        });
        Schema::create('accounting_events', function (Blueprint $t) {
            $t->id(); $t->string('event_key', 191)->unique(); $t->string('source_type', 70);
            $t->string('source_id', 100); $t->string('event_type', 80);
            $t->unsignedInteger('source_version')->default(1); $t->dateTime('occurred_at');
            $t->date('business_date')->nullable(); $t->decimal('amount', 18, 4)->default(0);
            $t->json('snapshot'); $t->string('status', 24)->default('PENDING');
            $t->text('last_error')->nullable(); $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->timestamps(); $t->unique(['source_type','source_id','event_type','source_version'], 'accounting_event_source_unique');
            $t->index(['status','occurred_at']);
        });
        Schema::create('accounting_event_attempts', function (Blueprint $t) {
            $t->id(); $t->foreignId('accounting_event_id')->constrained('accounting_events')->cascadeOnDelete();
            $t->string('action', 30); $t->string('result', 24); $t->text('message')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps();
        });
        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id(); $t->string('number', 60)->nullable()->unique();
            $t->foreignId('journal_id')->constrained('accounting_journals')->restrictOnDelete();
            $t->foreignId('period_id')->constrained('accounting_periods')->restrictOnDelete();
            $t->date('entry_date'); $t->date('business_date')->nullable();
            $t->string('status', 20)->default('DRAFT'); $t->string('origin', 20)->default('MANUAL');
            $t->foreignId('accounting_event_id')->nullable()->unique()->constrained('accounting_events')->nullOnDelete();
            $t->string('reference', 100)->nullable(); $t->text('narration');
            $t->unsignedBigInteger('created_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->unsignedBigInteger('posted_by')->nullable();
            $t->timestamp('posted_at')->nullable(); $t->unsignedBigInteger('reversal_of_id')->nullable()->unique();
            $t->timestamps(); $t->index(['status','entry_date']);
        });
        Schema::create('journal_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $t->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->decimal('debit',18,4)->default(0); $t->decimal('credit',18,4)->default(0);
            $t->string('memo', 500)->nullable(); $t->timestamps(); $t->index('account_id');
        });
        Schema::create('journal_approvals', function (Blueprint $t) {
            $t->id(); $t->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $t->string('action', 30); $t->unsignedBigInteger('user_id')->nullable();
            $t->text('reason')->nullable(); $t->timestamps();
        });
        Schema::create('journal_attachments', function (Blueprint $t) {
            $t->id(); $t->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $t->string('filename'); $t->string('path'); $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamps();
        });
        Schema::table('accounting_events', function (Blueprint $t) {
            $t->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('accounting_events', fn (Blueprint $t) => $t->dropForeign(['journal_entry_id']));
        foreach (['journal_attachments','journal_approvals','journal_lines','journal_entries',
            'accounting_event_attempts','accounting_events','account_mapping_rules','account_mappings',
            'period_close_logs','accounting_periods','fiscal_years','accounting_setting_histories',
            'accounting_settings','accounting_audit_logs','number_sequences','accounting_journals','chart_of_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
