<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_remark')) {
                $table->text('payment_remark')->nullable();
            }
            if (!Schema::hasColumn('orders', 'split_card_reference')) {
                $table->string('split_card_reference', 255)->nullable();
            }
            if (!Schema::hasColumn('orders', 'split_mfs_reference')) {
                $table->string('split_mfs_reference', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $columns = [];
            foreach (['payment_remark', 'split_card_reference', 'split_mfs_reference'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
