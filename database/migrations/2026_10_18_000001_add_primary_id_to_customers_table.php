<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'primary_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('primary_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'primary_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('primary_id');
            });
        }
    }
};
