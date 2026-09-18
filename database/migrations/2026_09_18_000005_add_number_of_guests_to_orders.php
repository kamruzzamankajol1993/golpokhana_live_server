<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders') || Schema::hasColumn('orders', 'number_of_guests')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('number_of_guests')->nullable()->after('order_type');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'number_of_guests')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('number_of_guests');
            });
        }
    }
};
