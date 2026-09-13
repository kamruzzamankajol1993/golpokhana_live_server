<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_leave_balances') || Schema::hasColumn('employee_leave_balances', 'entitlement_mode')) {
            return;
        }

        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->string('entitlement_mode', 20)->default('global')->after('year');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_leave_balances') || !Schema::hasColumn('employee_leave_balances', 'entitlement_mode')) {
            return;
        }

        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->dropColumn('entitlement_mode');
        });
    }
};
