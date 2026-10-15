<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employee_certificates') && !Schema::hasColumn('employee_certificates', 'description')) {
            Schema::table('employee_certificates', function (Blueprint $table) {
                $table->text('description')->nullable()->after('title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_certificates') && Schema::hasColumn('employee_certificates', 'description')) {
            Schema::table('employee_certificates', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
