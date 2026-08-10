<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->enum('deduction_basis', ['basic_salary', 'gross_salary'])->default('basic_salary')->after('absent_deduction_method');
            $table->decimal('half_day_deduction_percentage', 5, 2)->default(50)->after('deduction_basis');
            $table->enum('late_deduction_method', ['none', 'half_day_after_count', 'full_day_after_count'])->default('none')->after('half_day_deduction_percentage');
            $table->unsignedSmallInteger('late_count_threshold')->default(3)->after('late_deduction_method');
            $table->enum('overtime_basis', ['employee_rate', 'basic_hourly'])->default('employee_rate')->after('overtime_calculation_method');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn([
                'deduction_basis',
                'half_day_deduction_percentage',
                'late_deduction_method',
                'late_count_threshold',
                'overtime_basis',
            ]);
        });
    }
};
