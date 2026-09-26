<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('table_bookings')) {
            return;
        }

        Schema::table('table_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('table_bookings', 'advance_paid_in_cash')) {
                $table->decimal('advance_paid_in_cash', 10, 2)->default(0)->after('advance_mfs_provider');
            }
            if (!Schema::hasColumn('table_bookings', 'advance_paid_in_card')) {
                $table->decimal('advance_paid_in_card', 10, 2)->default(0)->after('advance_paid_in_cash');
            }
            if (!Schema::hasColumn('table_bookings', 'advance_paid_in_mfs')) {
                $table->decimal('advance_paid_in_mfs', 10, 2)->default(0)->after('advance_paid_in_card');
            }
            if (!Schema::hasColumn('table_bookings', 'advance_split_card_reference')) {
                $table->string('advance_split_card_reference')->nullable()->after('advance_paid_in_mfs');
            }
            if (!Schema::hasColumn('table_bookings', 'advance_split_mfs_reference')) {
                $table->string('advance_split_mfs_reference')->nullable()->after('advance_split_card_reference');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('table_bookings')) {
            return;
        }

        $columns = [
            'advance_paid_in_cash',
            'advance_paid_in_card',
            'advance_paid_in_mfs',
            'advance_split_card_reference',
            'advance_split_mfs_reference',
        ];

        $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn('table_bookings', $column)));
        if ($existing) {
            Schema::table('table_bookings', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
