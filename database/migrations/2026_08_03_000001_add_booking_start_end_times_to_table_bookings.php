<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('table_bookings', 'booking_start_time')) {
            Schema::table('table_bookings', function (Blueprint $table) {
                $table->time('booking_start_time')->nullable()->after('booking_time');
            });
        }

        if (!Schema::hasColumn('table_bookings', 'booking_end_time')) {
            Schema::table('table_bookings', function (Blueprint $table) {
                $table->time('booking_end_time')->nullable()->after('booking_start_time');
            });
        }

        // Preserve existing bookings: old booking_time becomes start time; default duration is one hour.
        DB::table('table_bookings')
            ->whereNull('booking_start_time')
            ->update([
                'booking_start_time' => DB::raw('booking_time'),
            ]);

        DB::table('table_bookings')
            ->whereNull('booking_end_time')
            ->update([
                'booking_end_time' => DB::raw("ADDTIME(COALESCE(booking_start_time, booking_time), '01:00:00')"),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('table_bookings', 'booking_end_time')) {
            Schema::table('table_bookings', function (Blueprint $table) {
                $table->dropColumn('booking_end_time');
            });
        }

        if (Schema::hasColumn('table_bookings', 'booking_start_time')) {
            Schema::table('table_bookings', function (Blueprint $table) {
                $table->dropColumn('booking_start_time');
            });
        }
    }
};
