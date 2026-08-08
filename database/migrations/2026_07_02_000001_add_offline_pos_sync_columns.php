<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addOfflineUuid('customers');
        $this->addOfflineUuid('orders');
        $this->addOfflineUuid('order_kots');
        $this->addOfflineUuid('order_details');
    }

    public function down(): void
    {
        foreach (['order_details', 'order_kots', 'orders', 'customers'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'offline_uuid')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('offline_uuid');
                });
            }
        }
    }

    private function addOfflineUuid(string $tableName): void
    {
        if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'offline_uuid')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->uuid('offline_uuid')->nullable()->unique()->after('id');
        });
    }
};
