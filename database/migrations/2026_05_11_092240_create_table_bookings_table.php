<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('table_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('table_id');
            $table->boolean('is_new_customer')->default(false); // বুলিয়েন টাইপ
            $table->integer('number_of_guests');
            $table->time('booking_time'); // বুকিং টাইম
            $table->date('booking_date'); // বুকিং ডেট
            $table->unsignedBigInteger('occasion_id')->nullable();
            $table->text('special_request')->nullable(); // টেক্সট এরিয়া
            $table->string('status')->default('Pending'); // Pending, Confirmed, Cancelled
            $table->timestamps();

            // Foreign Key Constraints
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('table_id')->references('id')->on('tables')->onDelete('cascade');
            $table->foreign('occasion_id')->references('id')->on('occasions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_bookings');
    }
};
