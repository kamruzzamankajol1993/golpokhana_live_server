<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings')
            && !Schema::hasColumn('pos_settings', 'allow_payment_with_insufficient_given_money')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('allow_payment_with_insufficient_given_money')
                    ->default(false)
                    ->after('given_money_manual_toggle_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings')
            && Schema::hasColumn('pos_settings', 'allow_payment_with_insufficient_given_money')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('allow_payment_with_insufficient_given_money');
            });
        }
    }
};
