<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('table_booking_tables')) {
            Schema::create('table_booking_tables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('table_booking_id');
                $table->unsignedBigInteger('table_id');
                $table->timestamps();
                $table->unique(['table_booking_id', 'table_id'], 'booking_table_unique');
                $table->foreign('table_booking_id')->references('id')->on('table_bookings')->cascadeOnDelete();
                $table->foreign('table_id')->references('id')->on('tables')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('table_bookings') && Schema::hasTable('table_booking_tables')) {
            DB::table('table_bookings')->select(['id', 'table_id'])->whereNotNull('table_id')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('table_booking_tables')->updateOrInsert(
                        ['table_booking_id' => $row->id, 'table_id' => $row->table_id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('table_booking_tables');
    }
};
