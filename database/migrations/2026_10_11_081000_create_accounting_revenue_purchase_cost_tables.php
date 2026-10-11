<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_receivables', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->decimal('original_due',18,4); $t->date('due_date')->nullable();
            $t->timestamps();
        });
        Schema::create('receivable_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('receivable_id')->constrained('customer_receivables')->restrictOnDelete();
            $t->foreignId('order_due_payment_id')->unique()->constrained('order_due_payments')->restrictOnDelete();
            $t->decimal('amount',18,4); $t->timestamps();
        });
        Schema::create('customer_deposits', function (Blueprint $t) {
            $t->id(); $t->foreignId('table_booking_id')->unique()->constrained('table_bookings')->restrictOnDelete();
            $t->unsignedBigInteger('customer_id')->nullable(); $t->decimal('received_amount',18,4);
            $t->timestamps();
        });
        Schema::create('deposit_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_deposit_id')->constrained('customer_deposits')->restrictOnDelete();
            $t->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $t->decimal('amount',18,4); $t->timestamps();
        });
        Schema::create('payment_accounts', function (Blueprint $t) {
            $t->id(); $t->string('name',120); $t->string('method',20);
            $t->foreignId('gl_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->string('provider',120)->nullable(); $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('payment_account_transactions', function (Blueprint $t) {
            $t->id(); $t->foreignId('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $t->string('reference',150); $t->string('direction',10); $t->decimal('amount',18,4);
            $t->string('status',20)->default('UNSETTLED'); $t->string('external_reference',200)->nullable();
            $t->foreignId('accounting_event_id')->nullable()->constrained('accounting_events')->nullOnDelete();
            $t->timestamps(); $t->unique(['payment_account_id','reference','direction'],'payacct_tx_unique');
        });
        Schema::create('accounting_vouchers', function (Blueprint $t) {
            $t->id(); $t->string('voucher_type',4); $t->foreignId('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
            $t->string('party_name',150)->nullable(); $t->string('payment_reference',200)->nullable();
            $t->timestamps();
        });
        Schema::create('cash_reconciliations', function (Blueprint $t) {
            $t->id(); $t->foreignId('pos_session_id')->unique()->constrained('pos_sessions')->restrictOnDelete();
            $t->decimal('expected_cash',18,4); $t->decimal('counted_cash',18,4);
            $t->decimal('variance',18,4); $t->string('status',24)->default('REVIEW');
            $t->text('reason')->nullable(); $t->unsignedBigInteger('checked_by')->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_payables', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_id')->unique()->constrained('purchases')->restrictOnDelete();
            $t->unsignedBigInteger('vendor_id'); $t->decimal('original_amount',18,4);
            $t->date('due_date')->nullable(); $t->timestamps();
        });
        Schema::create('payable_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('vendor_payable_id')->constrained('vendor_payables')->restrictOnDelete();
            $t->foreignId('vendor_payment_id')->unique()->constrained('vendor_payments')->restrictOnDelete();
            $t->decimal('amount',18,4); $t->timestamps();
        });
        Schema::create('inventory_cost_balances', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('stock_location_id'); $t->unsignedBigInteger('ingredient_id');
            $t->decimal('verified_qty',24,8)->default(0); $t->decimal('verified_value',18,4)->default(0);
            $t->timestamps(); $t->unique(['stock_location_id','ingredient_id'],'acct_cost_balance_unique');
        });
        Schema::create('inventory_cost_movements', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('stock_movement_id'); $t->unsignedBigInteger('ingredient_id');
            $t->string('movement_type',45); $t->decimal('verified_qty',24,8)->default(0);
            $t->decimal('verified_cost',18,4)->default(0);
            $t->timestamps(); $t->unique(['stock_movement_id','ingredient_id'],'acct_cost_movement_unique');
        });
        Schema::create('negative_inventory_cost_pending', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('stock_movement_id'); $t->unsignedBigInteger('ingredient_id');
            $t->unsignedBigInteger('order_id')->nullable(); $t->unsignedBigInteger('stock_location_id')->nullable();
            $t->decimal('quantity_base',24,8); $t->decimal('verified_cost',18,4)->nullable();
            $t->string('status',24)->default('COST_PENDING'); $t->text('reason')->nullable();
            $t->unsignedBigInteger('verified_by')->nullable(); $t->timestamp('verified_at')->nullable();
            $t->unsignedBigInteger('accounting_event_id')->nullable();
            $t->timestamps(); $t->unique(['stock_movement_id','ingredient_id'],'acct_cost_pending_unique');
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id(); $t->string('name',150)->unique(); $t->foreignId('expense_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id(); $t->string('expense_no',65)->unique(); $t->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $t->date('expense_date'); $t->decimal('amount',18,4); $t->text('description');
            $t->string('status',24)->default('DRAFT'); $t->string('payment_method',20)->nullable();
            $t->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $t->unsignedBigInteger('created_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->timestamps();
        });
        Schema::create('expense_attachments', function (Blueprint $t) {
            $t->id(); $t->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $t->string('filename'); $t->string('path'); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['expense_attachments','expenses','expense_categories','negative_inventory_cost_pending',
            'inventory_cost_movements','inventory_cost_balances','payable_allocations','vendor_payables',
            'cash_reconciliations','accounting_vouchers','payment_account_transactions','payment_accounts',
            'deposit_allocations','customer_deposits','receivable_allocations','customer_receivables'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
