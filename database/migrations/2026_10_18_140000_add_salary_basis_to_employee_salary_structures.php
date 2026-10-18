<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_salary_structures') && !Schema::hasColumn('employee_salary_structures', 'salary_basis')) {
            Schema::table('employee_salary_structures', function (Blueprint $table) {
                $table->string('salary_basis', 20)->default('period_rate')->after('pay_frequency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_salary_structures') && Schema::hasColumn('employee_salary_structures', 'salary_basis')) {
            Schema::table('employee_salary_structures', function (Blueprint $table) {
                $table->dropColumn('salary_basis');
            });
        }
    }
};
