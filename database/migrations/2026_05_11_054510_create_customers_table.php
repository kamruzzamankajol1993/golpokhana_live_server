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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // রিকুইরিড
            $table->string('phone'); // রিকুইরিড
            $table->string('email')->nullable();
            $table->date('dob')->nullable(); // Date of birth
            $table->text('address')->nullable();

            // পয়েন্ট এবং অর্ডার ট্র্যাকিং
            $table->integer('points')->default(0); // Initial points + Earned points
            $table->integer('total_orders')->default(0); // আপাতত ০ থাকবে
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
