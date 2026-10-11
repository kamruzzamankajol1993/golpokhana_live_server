<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_sales_refunds',function(Blueprint $t){
            $t->id(); $t->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $t->string('refund_reference',150)->unique();
            $t->date('refund_date'); $t->string('payment_method',25);
            $t->decimal('refund_amount',18,4); $t->decimal('vat_refund',18,4)->default(0);
            $t->text('supporting_evidence'); $t->string('status',20)->default('DRAFT');
            $t->foreignId('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('accounting_supplier_credit_notes',function(Blueprint $t){
            $t->id(); $t->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $t->unsignedBigInteger('stock_movement_id')->unique();
            $t->string('credit_note_reference',150)->unique(); $t->date('credit_note_date');
            $t->decimal('credit_amount',18,4); $t->text('supporting_evidence');
            $t->string('status',20)->default('DRAFT');
            $t->foreignId('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('accounting_customer_deposit_refunds',function(Blueprint $t){
            $t->id(); $t->foreignId('table_booking_id')->constrained('table_bookings')->restrictOnDelete();
            $t->string('refund_reference',150)->unique(); $t->date('refund_date');
            $t->string('payment_method',25); $t->decimal('amount',18,4);
            $t->text('supporting_evidence'); $t->string('status',20)->default('DRAFT');
            $t->foreignId('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('accounting_customer_deposit_refunds');
        Schema::dropIfExists('accounting_supplier_credit_notes');
        Schema::dropIfExists('accounting_sales_refunds');
    }
};
