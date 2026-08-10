<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->unsignedSmallInteger('half_day_after_minutes')->default(240);
            $table->unsignedSmallInteger('absent_after_minutes')->default(480);
            $table->unsignedSmallInteger('minimum_overtime_minutes')->default(30);
            $table->decimal('default_working_hours', 5, 2)->default(8);
            $table->json('weekly_off_days')->nullable();
            $table->boolean('allow_manual_attendance')->default(true);
            $table->boolean('auto_calculate_late')->default(true);
            $table->boolean('auto_calculate_overtime')->default(true);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
