<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'delivery_partner')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('delivery_partner', 100)->nullable()->after('order_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'delivery_partner')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('delivery_partner');
            });
        }
    }
};
