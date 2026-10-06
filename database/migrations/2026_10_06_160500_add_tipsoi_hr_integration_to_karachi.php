<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_settings')) {
            $columns = [
                'global_start_time' => fn (Blueprint $table) => $table->time('global_start_time')->default('09:00:00'),
                'global_end_time' => fn (Blueprint $table) => $table->time('global_end_time')->default('17:00:00'),
                'tipsoi_enabled' => fn (Blueprint $table) => $table->boolean('tipsoi_enabled')->default(false),
                'tipsoi_mode' => fn (Blueprint $table) => $table->string('tipsoi_mode', 20)->default('live'),
                'tipsoi_demo_url' => fn (Blueprint $table) => $table->string('tipsoi_demo_url')->nullable(),
                'tipsoi_demo_api_key' => fn (Blueprint $table) => $table->text('tipsoi_demo_api_key')->nullable(),
                'tipsoi_live_url' => fn (Blueprint $table) => $table->string('tipsoi_live_url')->nullable(),
                'tipsoi_live_api_key' => fn (Blueprint $table) => $table->text('tipsoi_live_api_key')->nullable(),
                'tipsoi_ssl_mode' => fn (Blueprint $table) => $table->string('tipsoi_ssl_mode', 20)->default('auto'),
                'tipsoi_last_sync_at' => fn (Blueprint $table) => $table->timestamp('tipsoi_last_sync_at')->nullable(),
                'tipsoi_last_sync_status' => fn (Blueprint $table) => $table->string('tipsoi_last_sync_status', 30)->nullable(),
                'tipsoi_last_sync_message' => fn (Blueprint $table) => $table->text('tipsoi_last_sync_message')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn('attendance_settings', $column)) {
                    Schema::table('attendance_settings', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }
        }

        if (Schema::hasTable('employees')) {
            $columns = [
                'tipsoi_person_id' => fn (Blueprint $table) => $table->unsignedBigInteger('tipsoi_person_id')->nullable()->index(),
                'tipsoi_identifier' => fn (Blueprint $table) => $table->string('tipsoi_identifier')->nullable()->index(),
                'tipsoi_rfid' => fn (Blueprint $table) => $table->string('tipsoi_rfid')->nullable(),
                'tipsoi_synced_at' => fn (Blueprint $table) => $table->timestamp('tipsoi_synced_at')->nullable(),
                'tipsoi_sync_status' => fn (Blueprint $table) => $table->string('tipsoi_sync_status', 30)->nullable(),
                'tipsoi_sync_error' => fn (Blueprint $table) => $table->text('tipsoi_sync_error')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn('employees', $column)) {
                    Schema::table('employees', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }
        }

        if (Schema::hasTable('attendances')) {
            $columns = [
                'tipsoi_person_id' => fn (Blueprint $table) => $table->unsignedBigInteger('tipsoi_person_id')->nullable()->index(),
                'tipsoi_person_identifier' => fn (Blueprint $table) => $table->string('tipsoi_person_identifier')->nullable()->index(),
                'tipsoi_external_id' => fn (Blueprint $table) => $table->string('tipsoi_external_id')->nullable()->index(),
                'tipsoi_synced_at' => fn (Blueprint $table) => $table->timestamp('tipsoi_synced_at')->nullable(),
                'tipsoi_pushed_at' => fn (Blueprint $table) => $table->timestamp('tipsoi_pushed_at')->nullable(),
                'tipsoi_push_status' => fn (Blueprint $table) => $table->string('tipsoi_push_status', 30)->nullable(),
                'tipsoi_push_error' => fn (Blueprint $table) => $table->text('tipsoi_push_error')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn('attendances', $column)) {
                    Schema::table('attendances', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendances')) {
            $columns = [
                'tipsoi_person_id', 'tipsoi_person_identifier', 'tipsoi_external_id',
                'tipsoi_synced_at', 'tipsoi_pushed_at', 'tipsoi_push_status', 'tipsoi_push_error',
            ];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('attendances', $column)));
            if ($existing) {
                Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }

        if (Schema::hasTable('employees')) {
            $columns = [
                'tipsoi_person_id', 'tipsoi_identifier', 'tipsoi_rfid',
                'tipsoi_synced_at', 'tipsoi_sync_status', 'tipsoi_sync_error',
            ];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('employees', $column)));
            if ($existing) {
                Schema::table('employees', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }

        if (Schema::hasTable('attendance_settings')) {
            $columns = [
                'global_start_time', 'global_end_time', 'tipsoi_enabled', 'tipsoi_mode',
                'tipsoi_demo_url', 'tipsoi_demo_api_key', 'tipsoi_live_url', 'tipsoi_live_api_key',
                'tipsoi_ssl_mode', 'tipsoi_last_sync_at',
                'tipsoi_last_sync_status', 'tipsoi_last_sync_message',
            ];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('attendance_settings', $column)));
            if ($existing) {
                Schema::table('attendance_settings', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
    }
};
