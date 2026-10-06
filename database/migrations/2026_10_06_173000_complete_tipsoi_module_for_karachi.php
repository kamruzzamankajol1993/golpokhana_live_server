<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            $columns = [
                'tipsoi_id_in_device' => fn (Blueprint $table) => $table->string('tipsoi_id_in_device')->nullable()->index(),
                'tipsoi_old_identifier' => fn (Blueprint $table) => $table->string('tipsoi_old_identifier')->nullable(),
                'tipsoi_primary_display_text' => fn (Blueprint $table) => $table->string('tipsoi_primary_display_text', 10)->nullable(),
                'tipsoi_secondary_display_text' => fn (Blueprint $table) => $table->string('tipsoi_secondary_display_text', 10)->nullable(),
                'tipsoi_photo_url' => fn (Blueprint $table) => $table->text('tipsoi_photo_url')->nullable(),
                'tipsoi_description' => fn (Blueprint $table) => $table->text('tipsoi_description')->nullable(),
                'tipsoi_person_type' => fn (Blueprint $table) => $table->string('tipsoi_person_type')->nullable(),
                'tipsoi_nid' => fn (Blueprint $table) => $table->string('tipsoi_nid')->nullable(),
                'tipsoi_from_module' => fn (Blueprint $table) => $table->string('tipsoi_from_module')->nullable(),
                'tipsoi_total_fingerprints' => fn (Blueprint $table) => $table->unsignedInteger('tipsoi_total_fingerprints')->default(0),
                'tipsoi_remote_updated_at' => fn (Blueprint $table) => $table->timestamp('tipsoi_remote_updated_at')->nullable(),
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
                'tipsoi_project_id' => fn (Blueprint $table) => $table->unsignedBigInteger('tipsoi_project_id')->nullable()->index(),
                'tipsoi_person_name' => fn (Blueprint $table) => $table->string('tipsoi_person_name')->nullable(),
                'tipsoi_rfid' => fn (Blueprint $table) => $table->string('tipsoi_rfid')->nullable(),
                'tipsoi_primary_display_text' => fn (Blueprint $table) => $table->string('tipsoi_primary_display_text')->nullable(),
                'tipsoi_secondary_display_text' => fn (Blueprint $table) => $table->string('tipsoi_secondary_display_text')->nullable(),
                'tipsoi_hours' => fn (Blueprint $table) => $table->string('tipsoi_hours')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn('attendances', $column)) {
                    Schema::table('attendances', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }
        }

        if (!Schema::hasTable('tipsoi_remote_people')) {
            Schema::create('tipsoi_remote_people', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tipsoi_person_id')->nullable()->unique();
                $table->string('id_in_device')->nullable()->index();
                $table->string('identifier')->nullable()->index();
                $table->string('old_identifier')->nullable();
                $table->string('name')->nullable()->index();
                $table->text('photo_url')->nullable();
                $table->string('rfid')->nullable()->index();
                $table->string('primary_display_text', 10)->nullable();
                $table->string('secondary_display_text', 10)->nullable();
                $table->text('description')->nullable();
                $table->string('person_type')->nullable();
                $table->string('nid')->nullable();
                $table->string('from_module')->nullable();
                $table->timestamp('remote_updated_at')->nullable();
                $table->unsignedInteger('total_fingerprints')->default(0);
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->json('raw_payload')->nullable();
                $table->timestamp('last_refreshed_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tipsoi_attendance_logs')) {
            Schema::create('tipsoi_attendance_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('uid', 191)->nullable()->unique();
                $table->timestamp('sync_time')->nullable()->index();
                $table->timestamp('logged_time')->nullable()->index();
                $table->string('type', 30)->nullable();
                $table->string('device_identifier')->nullable()->index();
                $table->string('location')->nullable();
                $table->unsignedBigInteger('person_id')->nullable()->index();
                $table->string('person_identifier')->nullable()->index();
                $table->string('rfid')->nullable();
                $table->string('primary_display_text')->nullable();
                $table->string('secondary_display_text')->nullable();
                $table->string('project_code')->nullable();
                $table->string('project_name')->nullable();
                $table->string('project_organization')->nullable();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tipsoi_sync_histories')) {
            Schema::create('tipsoi_sync_histories', function (Blueprint $table): void {
                $table->id();
                $table->string('sync_type', 50)->index();
                $table->string('mode', 20)->nullable();
                $table->date('from_date')->nullable();
                $table->date('to_date')->nullable();
                $table->unsignedInteger('summary_records')->default(0);
                $table->unsignedInteger('raw_logs')->default(0);
                $table->unsignedInteger('skipped_records')->default(0);
                $table->string('status', 30)->default('success');
                $table->text('message')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipsoi_sync_histories');
        Schema::dropIfExists('tipsoi_attendance_logs');
        Schema::dropIfExists('tipsoi_remote_people');

        if (Schema::hasTable('attendances')) {
            $columns = ['tipsoi_project_id', 'tipsoi_person_name', 'tipsoi_rfid', 'tipsoi_primary_display_text', 'tipsoi_secondary_display_text', 'tipsoi_hours'];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('attendances', $column)));
            if ($existing) {
                Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }

        if (Schema::hasTable('employees')) {
            $columns = ['tipsoi_id_in_device', 'tipsoi_old_identifier', 'tipsoi_primary_display_text', 'tipsoi_secondary_display_text', 'tipsoi_photo_url', 'tipsoi_description', 'tipsoi_person_type', 'tipsoi_nid', 'tipsoi_from_module', 'tipsoi_total_fingerprints', 'tipsoi_remote_updated_at'];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('employees', $column)));
            if ($existing) {
                Schema::table('employees', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
    }
};
