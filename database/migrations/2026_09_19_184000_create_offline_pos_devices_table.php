<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_pos_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name');
            $table->string('device_uuid')->unique();
            $table->string('device_key', 120)->unique();
            $table->boolean('status')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_pos_devices');
    }
};
