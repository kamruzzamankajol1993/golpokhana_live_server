<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->unique()->after('name');
            $table->time('start_time')->nullable()->after('code');
            $table->time('end_time')->nullable()->after('start_time');
            $table->unsignedSmallInteger('break_minutes')->default(0)->after('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(0)->after('break_minutes');
            $table->boolean('is_overnight')->default(false)->after('grace_minutes');
            $table->text('notes')->nullable()->after('is_overnight');
            $table->unsignedInteger('sort_order')->default(0)->after('notes');
            $table->index(['status', 'sort_order']);
        });

        // Backfill the two shifts already present in the supplied restaurant database.
        DB::table('shifts')->where('name', 'Evening(2 PM-12AM)')->whereNull('start_time')->update([
            'code' => 'EVENING',
            'start_time' => '14:00:00',
            'end_time' => '00:00:00',
            'is_overnight' => true,
            'sort_order' => 1,
        ]);
        DB::table('shifts')->where('name', 'Night(12AM-8AM)')->whereNull('start_time')->update([
            'code' => 'NIGHT',
            'start_time' => '00:00:00',
            'end_time' => '08:00:00',
            'is_overnight' => false,
            'sort_order' => 2,
        ]);
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex(['status', 'sort_order']);
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code',
                'start_time',
                'end_time',
                'break_minutes',
                'grace_minutes',
                'is_overnight',
                'notes',
                'sort_order',
            ]);
        });
    }
};
