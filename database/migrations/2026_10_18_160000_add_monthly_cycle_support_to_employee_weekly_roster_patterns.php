<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_weekly_roster_patterns')) {
            return;
        }

        if (!Schema::hasColumn('employee_weekly_roster_patterns', 'pattern_mode')) {
            Schema::table('employee_weekly_roster_patterns', function (Blueprint $table) {
                $table->string('pattern_mode', 20)->default('weekly')->after('day_of_week');
            });
        }

        if (!Schema::hasColumn('employee_weekly_roster_patterns', 'week_of_month')) {
            Schema::table('employee_weekly_roster_patterns', function (Blueprint $table) {
                $table->unsignedTinyInteger('week_of_month')->nullable()->after('pattern_mode');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_weekly_roster_patterns')) {
            return;
        }

        $hasPatternMode = Schema::hasColumn('employee_weekly_roster_patterns', 'pattern_mode');
        $hasWeekOfMonth = Schema::hasColumn('employee_weekly_roster_patterns', 'week_of_month');

        if ($hasPatternMode || $hasWeekOfMonth) {
            Schema::table('employee_weekly_roster_patterns', function (Blueprint $table) use ($hasPatternMode, $hasWeekOfMonth) {
                $columns = [];
                if ($hasWeekOfMonth) {
                    $columns[] = 'week_of_month';
                }
                if ($hasPatternMode) {
                    $columns[] = 'pattern_mode';
                }
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
