<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_settings', 'complimentary_note_required')) {
                    $table->boolean('complimentary_note_required')->default(false)->after('show_honored_percentage_on_invoice');
                }
                if (!Schema::hasColumn('pos_settings', 'dine_in_waiter_required')) {
                    $table->boolean('dine_in_waiter_required')->default(false)->after('complimentary_note_required');
                }
            });
        }

        if (Schema::hasTable('order_details') && !Schema::hasColumn('order_details', 'complimentary_note')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->text('complimentary_note')->nullable()->after('is_complimentary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'complimentary_note')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('complimentary_note');
            });
        }

        if (Schema::hasTable('pos_settings')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('pos_settings', 'dine_in_waiter_required')) {
                    $columns[] = 'dine_in_waiter_required';
                }
                if (Schema::hasColumn('pos_settings', 'complimentary_note_required')) {
                    $columns[] = 'complimentary_note_required';
                }
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
