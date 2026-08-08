<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // ৬ ডিজিট ইউনিক id
            $table->unsignedBigInteger('customer_id')->nullable(); // ওয়াক-ইন কাস্টমার হতে পারে
            $table->unsignedBigInteger('table_id')->nullable(); // টেবিল আইডি
            $table->boolean('send_to_kitchen')->default(false); // বুলিয়েন

            // Financial Data
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat_tax', 10, 2)->default(0);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_type')->nullable(); // fixed বা percentage
            $table->decimal('reward_point_discount', 10, 2)->default(0);
            $table->decimal('delivery_charge', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->decimal('due', 10, 2)->default(0);

            // Delivery & Status
            $table->text('delivery_address')->nullable();
            $table->string('status')->default('Pending'); // Pending, Processing,Cooking, Completed, Cancelled
            $table->string('order_type'); // Dine-In, Takeaway, Delivery

            // User & Time
            $table->unsignedBigInteger('user_id')->nullable(); // যে অর্ডার ক্রিয়েট করেছে
            $table->timestamp('order_time')->useCurrent(); // অর্ডার টাইম (লারাভেল ডিফল্ট টাইমজোন অনুযায়ী কাজ করবে)

            // Payment
            $table->string('payment_type')->default('Cash'); // Cash, Card, Mobile Banking
            $table->string('transaction_id')->nullable(); // মোবাইল ব্যাঙ্কিং ও কার্ডের জন্য
            $table->text('notes')->nullable();

            $table->timestamps();

            // Foreign Key Constraints
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('table_id')->references('id')->on('tables')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
