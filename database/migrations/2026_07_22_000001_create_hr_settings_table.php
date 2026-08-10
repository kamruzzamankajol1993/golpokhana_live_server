<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code_prefix', 20)->default('EMP');
            $table->unsignedBigInteger('employee_code_next_number')->default(1);
            $table->unsignedTinyInteger('employee_code_padding')->default(4);
            $table->unsignedTinyInteger('default_probation_months')->default(3);
            $table->unsignedSmallInteger('default_notice_period_days')->default(30);
            $table->boolean('allow_employee_login')->default(true);
            $table->boolean('allow_waiter_access')->default(true);
            $table->string('date_format', 30)->default('d-m-Y');
            $table->string('time_format', 20)->default('h:i A');
            $table->string('timezone', 60)->default('Asia/Dhaka');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_settings');
    }
};
