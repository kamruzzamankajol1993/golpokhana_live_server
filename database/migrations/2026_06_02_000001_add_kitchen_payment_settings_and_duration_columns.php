<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'final_payment_depends_on_kitchen_status')) {
                $table->boolean('final_payment_depends_on_kitchen_status')
                    ->default(false)
                    ->after('items_per_page')
                    ->comment('If true, POS final payment is enabled only when kitchen KOT status is Ready.');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'kitchen_to_payment_minutes')) {
                $table->unsignedInteger('kitchen_to_payment_minutes')
                    ->nullable()
                    ->after('preparation_time')
                    ->comment('Minutes from sending order to kitchen until final payment.');
            }
        });

        DB::table('pos_settings')->updateOrInsert(
            ['id' => 1],
            ['final_payment_depends_on_kitchen_status' => false]
        );
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'kitchen_to_payment_minutes')) {
                $table->dropColumn('kitchen_to_payment_minutes');
            }
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_settings', 'final_payment_depends_on_kitchen_status')) {
                $table->dropColumn('final_payment_depends_on_kitchen_status');
            }
        });
    }
};
