<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('product_discount_amount', 10, 2)->default(0)->after('discount_amount');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->string('product_discount_type')->nullable()->after('subtotal');
            $table->decimal('product_discount_value', 10, 2)->default(0)->after('product_discount_type');
            $table->decimal('product_discount_amount', 10, 2)->default(0)->after('product_discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn([
                'product_discount_type',
                'product_discount_value',
                'product_discount_amount',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('product_discount_amount');
        });
    }
};
