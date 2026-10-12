<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('qualification', 180);
            $table->string('institution', 255)->nullable();
            $table->string('field_of_study', 180)->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();
            $table->string('result', 100)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'passing_year']);
        });

        Schema::create('employee_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('file_path', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_certificates');
        Schema::dropIfExists('employee_educations');
    }
};
