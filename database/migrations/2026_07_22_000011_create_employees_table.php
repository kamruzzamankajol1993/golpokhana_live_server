<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->foreignId('default_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();

            $table->string('employee_code', 50)->unique();
            $table->string('name', 180);
            $table->string('phone', 40);
            $table->string('email')->nullable()->unique();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('join_date');
            $table->date('probation_end_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->enum('employment_status', ['active', 'inactive', 'resigned', 'terminated'])->default('active');
            $table->boolean('is_waiter')->default(false);
            $table->boolean('can_login')->default(false);
            $table->string('image')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name', 180)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employment_status', 'department_id']);
            $table->index(['default_shift_id', 'employment_status']);
            $table->index(['is_waiter', 'can_login']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
