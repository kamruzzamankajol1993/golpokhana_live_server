<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_due_payments')) {
            return;
        }

        Schema::table('order_due_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('order_due_payments', 'card_type')) {
                $table->string('card_type', 100)->nullable();
            }
            if (!Schema::hasColumn('order_due_payments', 'mfs_provider')) {
                $table->string('mfs_provider', 100)->nullable();
            }
            if (!Schema::hasColumn('order_due_payments', 'paid_in_cash')) {
                $table->decimal('paid_in_cash', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('order_due_payments', 'paid_in_card')) {
                $table->decimal('paid_in_card', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('order_due_payments', 'paid_in_mfc')) {
                $table->decimal('paid_in_mfc', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('order_due_payments', 'split_card_reference')) {
                $table->string('split_card_reference', 255)->nullable();
            }
            if (!Schema::hasColumn('order_due_payments', 'split_mfs_reference')) {
                $table->string('split_mfs_reference', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('order_due_payments')) {
            return;
        }

        $columns = collect([
            'card_type',
            'mfs_provider',
            'paid_in_cash',
            'paid_in_card',
            'paid_in_mfc',
            'split_card_reference',
            'split_mfs_reference',
        ])->filter(fn ($column) => Schema::hasColumn('order_due_payments', $column))->values()->all();

        if ($columns !== []) {
            Schema::table('order_due_payments', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
