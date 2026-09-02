<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'table_booking_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('table_booking_id')->nullable()->after('booking_advance')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'table_booking_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('table_booking_id');
            });
        }
    }
};
