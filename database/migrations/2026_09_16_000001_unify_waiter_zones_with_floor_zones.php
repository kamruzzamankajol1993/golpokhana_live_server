<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Use Table Management's floor_zones as the single zone source for waiters.
     *
     * IMPORTANT:
     * - employees.zone_id is intentionally NOT changed here. In this database it
     *   is varchar(11), while floor_zones.id is BIGINT UNSIGNED, so adding a
     *   direct FK there would fail with errno 150.
     * - This migration is idempotent for a partially-applied previous attempt.
     *   If waiters.zone_id already points to floor_zones, it leaves it untouched.
     */
    public function up(): void
    {
        if (
            !Schema::hasTable('waiters') ||
            !Schema::hasColumn('waiters', 'zone_id') ||
            !Schema::hasTable('floor_zones')
        ) {
            return;
        }

        // A previous failed migration may already have completed the waiter FK.
        // In that case, do not remap the IDs a second time.
        if ($this->hasForeignKeyTo('waiters', 'zone_id', 'floor_zones')) {
            return;
        }

        // Drop only the actual FK(s) currently attached to waiters.zone_id.
        // This works whether the old constraint uses Laravel's default name,
        // a custom name, or there is no FK at all.
        $this->dropForeignKeysForColumn('waiters', 'zone_id');

        if (Schema::hasTable('zones')) {
            $rows = DB::table('waiters')
                ->whereNotNull('zone_id')
                ->get(['id', 'zone_id']);

            foreach ($rows as $row) {
                $currentZoneId = $row->zone_id;

                // Legacy waiter data used zones.id. Resolve it by name and move
                // the waiter to the matching floor_zones.id.
                $legacyZone = DB::table('zones')
                    ->where('id', $currentZoneId)
                    ->first();

                if ($legacyZone) {
                    $floorZoneId = $this->findOrCreateFloorZone($legacyZone);

                    DB::table('waiters')
                        ->where('id', $row->id)
                        ->update(['zone_id' => $floorZoneId]);

                    continue;
                }

                // If the ID is already a valid floor_zones ID, preserve it.
                // This protects a partially migrated database with no FK.
                $alreadyFloorZone = DB::table('floor_zones')
                    ->where('id', $currentZoneId)
                    ->exists();

                if (!$alreadyFloorZone) {
                    // Unknown/orphaned value cannot satisfy the new FK safely.
                    DB::table('waiters')
                        ->where('id', $row->id)
                        ->update(['zone_id' => null]);
                }
            }
        } else {
            // No legacy zones table: keep valid floor-zone IDs and null only
            // orphaned values so the FK can be created safely.
            $validFloorZoneIds = DB::table('floor_zones')->pluck('id')->all();

            DB::table('waiters')
                ->whereNotNull('zone_id')
                ->when(
                    count($validFloorZoneIds) > 0,
                    fn ($query) => $query->whereNotIn('zone_id', $validFloorZoneIds),
                    fn ($query) => $query
                )
                ->update(['zone_id' => null]);
        }

        if (!$this->hasForeignKeyTo('waiters', 'zone_id', 'floor_zones')) {
            DB::statement(
                'ALTER TABLE `waiters` '
                . 'ADD CONSTRAINT `waiters_zone_id_foreign` '
                . 'FOREIGN KEY (`zone_id`) REFERENCES `floor_zones` (`id`) '
                . 'ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('waiters') ||
            !Schema::hasColumn('waiters', 'zone_id') ||
            !Schema::hasTable('zones')
        ) {
            return;
        }

        // Already restored to the legacy zones table: nothing to do.
        if ($this->hasForeignKeyTo('waiters', 'zone_id', 'zones')) {
            return;
        }

        $this->dropForeignKeysForColumn('waiters', 'zone_id');

        if (Schema::hasTable('floor_zones')) {
            $rows = DB::table('waiters')
                ->whereNotNull('zone_id')
                ->get(['id', 'zone_id']);

            foreach ($rows as $row) {
                $floorZone = DB::table('floor_zones')
                    ->where('id', $row->zone_id)
                    ->first();

                if (!$floorZone) {
                    DB::table('waiters')
                        ->where('id', $row->id)
                        ->update(['zone_id' => null]);
                    continue;
                }

                $legacyZoneId = $this->findOrCreateLegacyZone($floorZone);

                DB::table('waiters')
                    ->where('id', $row->id)
                    ->update(['zone_id' => $legacyZoneId]);
            }
        }

        if (!$this->hasForeignKeyTo('waiters', 'zone_id', 'zones')) {
            DB::statement(
                'ALTER TABLE `waiters` '
                . 'ADD CONSTRAINT `waiters_zone_id_foreign` '
                . 'FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) '
                . 'ON DELETE SET NULL'
            );
        }
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->filter()
            ->unique()
            ->values();

        foreach ($foreignKeys as $foreignKey) {
            $safeName = str_replace('`', '``', (string) $foreignKey);
            DB::statement("ALTER TABLE `waiters` DROP FOREIGN KEY `{$safeName}`");
        }
    }

    private function hasForeignKeyTo(string $table, string $column, string $referencedTable): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->where('REFERENCED_TABLE_NAME', $referencedTable)
            ->exists();
    }

    private function findOrCreateFloorZone(object $legacyZone): int
    {
        $matched = DB::table('floor_zones')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $legacyZone->name))])
            ->value('id');

        if ($matched) {
            return (int) $matched;
        }

        return (int) DB::table('floor_zones')->insertGetId([
            'name' => $legacyZone->name,
            'status' => $legacyZone->status ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function findOrCreateLegacyZone(object $floorZone): int
    {
        $matched = DB::table('zones')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $floorZone->name))])
            ->value('id');

        if ($matched) {
            return (int) $matched;
        }

        return (int) DB::table('zones')->insertGetId([
            'name' => $floorZone->name,
            'status' => $floorZone->status ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
