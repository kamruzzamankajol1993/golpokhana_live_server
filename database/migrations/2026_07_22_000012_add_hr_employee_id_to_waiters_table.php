<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waiters', function (Blueprint $table) {
            $table->foreignId('hr_employee_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('waiters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hr_employee_id');
        });
    }
};
