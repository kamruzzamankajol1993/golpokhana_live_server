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
            if (!Schema::hasColumn('orders', 'card_type')) {
                $table->string('card_type', 100)->nullable();
            }
            if (!Schema::hasColumn('orders', 'mfs_provider')) {
                $table->string('mfs_provider', 100)->nullable();
            }
            if (!Schema::hasColumn('orders', 'pre_invoice_snapshot')) {
                $table->json('pre_invoice_snapshot')->nullable();
            }
            if (!Schema::hasColumn('orders', 'pre_invoice_printed_at')) {
                $table->timestamp('pre_invoice_printed_at')->nullable();
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
            foreach (['card_type', 'mfs_provider', 'pre_invoice_snapshot', 'pre_invoice_printed_at'] as $column) {
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
