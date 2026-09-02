<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reviews') && !Schema::hasColumn('reviews', 'comment')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->text('comment')->nullable()->after('review');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'comment')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropColumn('comment');
            });
        }
    }
};
