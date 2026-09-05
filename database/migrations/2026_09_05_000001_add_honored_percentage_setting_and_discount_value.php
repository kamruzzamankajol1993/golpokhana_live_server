<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings') && !Schema::hasColumn('pos_settings', 'show_honored_percentage_on_invoice')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('show_honored_percentage_on_invoice')->default(true);
            });
        }

        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'discount_value')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('discount_value', 10, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'discount_value')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('discount_value');
            });
        }

        if (Schema::hasTable('pos_settings') && Schema::hasColumn('pos_settings', 'show_honored_percentage_on_invoice')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('show_honored_percentage_on_invoice');
            });
        }
    }
};
