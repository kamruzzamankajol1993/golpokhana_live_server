<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('salary_cycle_start_day')->default(1);
            $table->unsignedTinyInteger('salary_cycle_end_day')->nullable();
            $table->enum('working_days_method', ['calendar_days', 'fixed_days', 'attendance_days'])->default('calendar_days');
            $table->decimal('default_working_days', 6, 2)->default(30);
            $table->enum('absent_deduction_method', ['per_day', 'none'])->default('per_day');
            $table->enum('overtime_calculation_method', ['hourly_rate', 'fixed_rate', 'none'])->default('hourly_rate');
            $table->decimal('overtime_rate_multiplier', 8, 4)->default(1.5);
            $table->enum('rounding_method', ['none', 'nearest', 'floor', 'ceil'])->default('nearest');
            $table->boolean('allow_negative_salary')->default(false);
            $table->boolean('lock_paid_payroll')->default(true);
            $table->string('currency', 10)->default('BDT');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
