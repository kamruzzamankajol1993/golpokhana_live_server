<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_settings')) return;
        $values = [];
        if (Schema::hasColumn('pos_settings', 'offline_pos_sync_interval_seconds')) $values['offline_pos_sync_interval_seconds'] = 180;
        if (Schema::hasColumn('pos_settings', 'offline_pos_retry_interval_seconds')) $values['offline_pos_retry_interval_seconds'] = 180;
        if ($values) DB::table('pos_settings')->update($values);
    }

    public function down(): void
    {
        // Keep the configured cadence unchanged on rollback.
    }
};
