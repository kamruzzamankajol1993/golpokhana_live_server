<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_settings')) {
            return;
        }

        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'offline_pos_enabled')) {
                $table->boolean('offline_pos_enabled')->default(true);
            }
            if (!Schema::hasColumn('pos_settings', 'offline_pos_base_url')) {
                $table->string('offline_pos_base_url', 255)->nullable();
            }
            if (!Schema::hasColumn('pos_settings', 'offline_pos_show_pull_button')) {
                $table->boolean('offline_pos_show_pull_button')->default(true);
            }
            if (!Schema::hasColumn('pos_settings', 'offline_pos_show_push_button')) {
                $table->boolean('offline_pos_show_push_button')->default(true);
            }
            if (!Schema::hasColumn('pos_settings', 'offline_pos_auto_pull_enabled')) {
                $table->boolean('offline_pos_auto_pull_enabled')->default(true);
            }
            if (!Schema::hasColumn('pos_settings', 'offline_pos_auto_push_enabled')) {
                $table->boolean('offline_pos_auto_push_enabled')->default(true);
            }
            if (!Schema::hasColumn('pos_settings', 'offline_pos_sync_interval_seconds')) {
                $table->unsignedInteger('offline_pos_sync_interval_seconds')->default(30);
            }
            if (!Schema::hasColumn('pos_settings', 'offline_pos_retry_interval_seconds')) {
                $table->unsignedInteger('offline_pos_retry_interval_seconds')->default(15);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pos_settings')) {
            return;
        }

        $columns = [
            'offline_pos_enabled',
            'offline_pos_base_url',
            'offline_pos_show_pull_button',
            'offline_pos_show_push_button',
            'offline_pos_auto_pull_enabled',
            'offline_pos_auto_push_enabled',
            'offline_pos_sync_interval_seconds',
            'offline_pos_retry_interval_seconds',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('pos_settings', $column)
        ));

        if ($existing) {
            Schema::table('pos_settings', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
