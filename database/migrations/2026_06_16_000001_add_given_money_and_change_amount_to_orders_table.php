<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add separate received money and return/change amount columns for final payment flow.
     */
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'given_money')) {
                $givenMoneyColumn = $table->decimal('given_money', 12, 2)->default(0);

                if (Schema::hasColumn('orders', 'tips_amount')) {
                    $givenMoneyColumn->after('tips_amount');
                } elseif (Schema::hasColumn('orders', 'total_paid_amount')) {
                    $givenMoneyColumn->after('total_paid_amount');
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'change_amount')) {
                $changeAmountColumn = $table->decimal('change_amount', 12, 2)->default(0);

                if (Schema::hasColumn('orders', 'given_money')) {
                    $changeAmountColumn->after('given_money');
                } elseif (Schema::hasColumn('orders', 'total_paid_amount')) {
                    $changeAmountColumn->after('total_paid_amount');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'change_amount')) {
                $table->dropColumn('change_amount');
            }

            if (Schema::hasColumn('orders', 'given_money')) {
                $table->dropColumn('given_money');
            }
        });
    }
};
