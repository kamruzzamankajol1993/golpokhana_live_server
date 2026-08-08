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
        Schema::create('waiters', function (Blueprint $table) {
            $table->id();
            // Foreign Keys
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // একাউন্ট ক্রিয়েট করলে ইউজার আইডি বসবে
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();

            // Waiter Info
            $table->string('employee_id')->unique(); // অটো জেনারেটেড হবে কন্ট্রোলারে
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('image')->nullable();
            $table->date('join_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('status')->default(true); // 1 = Active, 0 = Inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waiters');
    }
};
