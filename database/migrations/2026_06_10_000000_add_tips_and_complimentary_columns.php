<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'tips_amount')) {
                $table->decimal('tips_amount', 10, 2)->default(0)->after('total_paid_amount');
            }
        });

        Schema::table('order_details', function (Blueprint $table) {
            if (!Schema::hasColumn('order_details', 'is_complimentary')) {
                $table->boolean('is_complimentary')->default(false)->after('food_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            if (Schema::hasColumn('order_details', 'is_complimentary')) {
                $table->dropColumn('is_complimentary');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'tips_amount')) {
                $table->dropColumn('tips_amount');
            }
        });
    }
};
