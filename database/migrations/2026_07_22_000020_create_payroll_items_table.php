<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('employee_salary_structure_id')->nullable()->constrained('employee_salary_structures')->nullOnDelete();

            $table->string('employee_code', 50);
            $table->string('employee_name', 180);
            $table->string('department_name', 120)->nullable();
            $table->string('designation_name', 120)->nullable();
            $table->date('joining_date')->nullable();
            $table->date('exit_date')->nullable();

            $table->decimal('salary_divisor', 8, 2)->default(30);
            $table->decimal('payable_days', 8, 2)->default(0);
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('prorated_basic_salary', 14, 2)->default(0);
            $table->decimal('gross_salary', 14, 2)->default(0);
            $table->decimal('total_deduction', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);

            $table->decimal('present_days', 8, 2)->default(0);
            $table->decimal('late_days', 8, 2)->default(0);
            $table->decimal('absent_days', 8, 2)->default(0);
            $table->decimal('half_days', 8, 2)->default(0);
            $table->decimal('paid_leave_days', 8, 2)->default(0);
            $table->decimal('unpaid_leave_days', 8, 2)->default(0);
            $table->decimal('off_days', 8, 2)->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->json('attendance_summary')->nullable();

            $table->enum('payment_method', ['cash', 'bank', 'mobile_banking'])->default('cash');
            $table->string('account_name', 180)->nullable();
            $table->string('account_number', 120)->nullable();
            $table->string('mobile_banking_provider', 60)->nullable();
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'payroll_run_employee_unique');
            $table->index(['payroll_run_id', 'payment_status']);
            $table->index(['employee_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
