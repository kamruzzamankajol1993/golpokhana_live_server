<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shift_rosters') && !Schema::hasColumn('shift_rosters', 'is_recurring_generated')) {
            Schema::table('shift_rosters', function (Blueprint $table) {
                $table->boolean('is_recurring_generated')->default(false)->after('assigned_by');
            });
        }

        if (!Schema::hasTable('employee_weekly_roster_patterns')) {
            Schema::create('employee_weekly_roster_patterns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->unsignedTinyInteger('day_of_week'); // Carbon: Sunday=0 ... Saturday=6
                $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
                $table->string('status', 16)->default('scheduled'); // scheduled, off, unassigned
                $table->date('effective_from');
                $table->date('effective_until')->nullable();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['employee_id', 'day_of_week', 'effective_from'], 'ewrp_employee_day_start_idx');
                $table->index(['effective_from', 'effective_until'], 'ewrp_effective_dates_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_weekly_roster_patterns');
        if (Schema::hasTable('shift_rosters') && Schema::hasColumn('shift_rosters', 'is_recurring_generated')) {
            Schema::table('shift_rosters', function (Blueprint $table) {
                $table->dropColumn('is_recurring_generated');
            });
        }
    }
};
