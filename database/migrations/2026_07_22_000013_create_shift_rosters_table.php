<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('roster_date');
            $table->enum('status', ['scheduled', 'off', 'leave'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'roster_date']);
            $table->index(['roster_date', 'shift_id']);
            $table->index(['roster_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_rosters');
    }
};
