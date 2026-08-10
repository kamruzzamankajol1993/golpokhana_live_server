<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_salary_structure_id')
                ->constrained('employee_salary_structures')
                ->cascadeOnDelete();
            $table->foreignId('salary_component_id')
                ->constrained('salary_components')
                ->restrictOnDelete();
            $table->enum('component_type', ['earning', 'deduction']);
            $table->enum('calculation_type', ['fixed', 'percentage', 'manual']);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('percentage', 8, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['employee_salary_structure_id', 'salary_component_id'],
                'employee_salary_component_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
