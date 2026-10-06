<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tipsoi_devices')) {
            Schema::create('tipsoi_devices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tipsoi_id')->nullable()->unique();
                $table->string('identifier')->unique();
                $table->unsignedBigInteger('device_category_id')->nullable();
                $table->string('vendor_id')->nullable();
                $table->text('server_url')->nullable();
                $table->string('firmware_version')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('sim_id')->nullable();
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->string('imei_number')->nullable();
                $table->integer('timezone_offset_minutes')->nullable();
                $table->string('type')->nullable();
                $table->unsignedBigInteger('server_id')->nullable();
                $table->boolean('has_enrollment_feature')->default(false);
                $table->boolean('is_mqtt_enabled')->default(false);
                $table->boolean('mqtt_allow_batch_rfid')->default(false);
                $table->boolean('connected')->default(false);
                $table->boolean('data_dump_requested')->default(false);
                $table->timestamp('last_communication_at')->nullable();
                $table->unsignedBigInteger('device_type_id')->nullable();
                $table->unsignedInteger('total_allocated')->default(0);
                $table->string('last_seen')->nullable();
                $table->string('status', 50)->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipsoi_devices');
    }
};
