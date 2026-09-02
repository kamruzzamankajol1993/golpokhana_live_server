<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchases')) {
            return;
        }

        $addPath = !Schema::hasColumn('purchases', 'original_invoice_path');
        $addName = !Schema::hasColumn('purchases', 'original_invoice_name');
        $addMime = !Schema::hasColumn('purchases', 'original_invoice_mime');
        $addSize = !Schema::hasColumn('purchases', 'original_invoice_size');

        if (!$addPath && !$addName && !$addMime && !$addSize) {
            return;
        }

        Schema::table('purchases', function (Blueprint $table) use ($addPath, $addName, $addMime, $addSize) {
            if ($addPath) {
                $table->string('original_invoice_path', 500)->nullable();
            }
            if ($addName) {
                $table->string('original_invoice_name', 255)->nullable();
            }
            if ($addMime) {
                $table->string('original_invoice_mime', 120)->nullable();
            }
            if ($addSize) {
                $table->unsignedBigInteger('original_invoice_size')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchases')) {
            return;
        }

        $columns = [];
        foreach (['original_invoice_path', 'original_invoice_name', 'original_invoice_mime', 'original_invoice_size'] as $column) {
            if (Schema::hasColumn('purchases', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('purchases', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
