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
        Schema::create('reward_point_settings', function (Blueprint $table) {
            $table->id();
            // কোন কন্ডিশন একটিভ থাকবে তা নির্ধারণ করার জন্য
            // 'order_based' = ১ অর্ডারে ১ পয়েন্ট
            // 'amount_based' = নির্দিষ্ট এমাউন্টে নির্দিষ্ট পয়েন্ট
            $table->enum('reward_type', ['order_based', 'amount_based'])->default('amount_based');

            // Order Based Settings
            $table->integer('points_per_order')->default(1);

            // Amount Based Settings
            $table->decimal('amount_to_spend', 10, 2)->default(500.00);
            $table->integer('points_per_amount')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_point_settings');
    }
};
