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
        Schema::table('reward_point_settings', function (Blueprint $table) {
            // নতুন দুটি কলাম যোগ করা হচ্ছে
            $table->integer('points_to_redeem')->default(100)->after('points_per_amount');
            $table->decimal('discount_amount', 10, 2)->default(10.00)->after('points_to_redeem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_point_settings', function (Blueprint $table) {
            // রোলব্যাক করলে কলামগুলো রিমুভ হয়ে যাবে
            $table->dropColumn(['points_to_redeem', 'discount_amount']);
        });
    }
};
