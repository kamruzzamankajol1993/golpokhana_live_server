<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('basic_salary', 14, 2);
            $table->decimal('overtime_rate', 14, 2)->nullable();
            $table->enum('payment_method', ['cash', 'bank', 'mobile_banking'])->default('cash');
            $table->string('account_name', 180)->nullable();
            $table->string('account_number', 120)->nullable();
            $table->string('mobile_banking_provider', 60)->nullable();
            $table->boolean('status')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from'], 'employee_salary_effective_unique');
            $table->index(['employee_id', 'status', 'effective_from'], 'employee_salary_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_structures');
    }
};
