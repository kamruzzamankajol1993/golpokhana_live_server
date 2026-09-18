<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pos_sessions', 'table_bookings'] as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'offline_uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->uuid('offline_uuid')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['table_bookings', 'pos_sessions'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'offline_uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('offline_uuid');
                });
            }
        }
    }
};
