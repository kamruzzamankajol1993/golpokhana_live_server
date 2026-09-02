<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('floor_zones') || !Schema::hasTable('tables') || !Schema::hasTable('zones')) {
            return;
        }

        $zones = DB::table('zones')->get();

        foreach ($zones as $zone) {
            $floorZoneId = DB::table('floor_zones')
                ->where('name', $zone->name)
                ->value('id');

            if (!$floorZoneId) {
                $floorZoneId = DB::table('floor_zones')->insertGetId([
                    'name' => $zone->name,
                    'status' => $zone->status ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('tables')
                ->where('zone_id', $zone->id)
                ->whereNull('floor_zone_id')
                ->update([
                    'floor_zone_id' => $floorZoneId,
                ]);
        }
    }

    public function down(): void
    {
        // Data migration. No rollback required.
    }
};
