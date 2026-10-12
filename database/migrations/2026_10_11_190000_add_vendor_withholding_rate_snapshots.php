<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('vat_rate_histories')) {
            Schema::create('vat_rate_histories', function (Blueprint $t) {
                $t->id(); $t->foreignId('tax_setting_id')->nullable()->index();
                $t->decimal('old_rate', 7, 4)->nullable(); $t->decimal('new_rate', 7, 4);
                $t->boolean('old_included')->nullable(); $t->boolean('new_included');
                $t->timestamp('effective_at')->index(); $t->foreignId('changed_by')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('vendor_tax_rate_histories')) {
            Schema::create('vendor_tax_rate_histories', function (Blueprint $t) {
                $t->id(); $t->foreignId('vendor_id')->index();
                $t->string('tax_type', 3); $t->decimal('old_rate', 7, 4)->nullable();
                $t->decimal('new_rate', 7, 4); $t->timestamp('effective_at')->index();
                $t->foreignId('changed_by')->nullable(); $t->timestamps();
                $t->index(['vendor_id','tax_type','effective_at'], 'vendor_tax_history_lookup');
            });
        }
        foreach (['purchases','purchase_vouchers'] as $name) {
            if (!Schema::hasTable($name)) continue;
            Schema::table($name, function (Blueprint $t) use ($name) {
                if (!Schema::hasColumn($name,'tds_rate_snapshot')) $t->decimal('tds_rate_snapshot', 7, 4)->nullable();
                if (!Schema::hasColumn($name,'vds_rate_snapshot')) $t->decimal('vds_rate_snapshot', 7, 4)->nullable();
            });
        }
        if (Schema::hasTable('vendor_payments')) {
            Schema::table('vendor_payments', function (Blueprint $t) {
                if (!Schema::hasColumn('vendor_payments','tds_rate_snapshot')) $t->decimal('tds_rate_snapshot', 7, 4)->nullable();
                if (!Schema::hasColumn('vendor_payments','vds_rate_snapshot')) $t->decimal('vds_rate_snapshot', 7, 4)->nullable();
                if (!Schema::hasColumn('vendor_payments','withholding_base')) $t->decimal('withholding_base',18,4)->nullable();
                if (!Schema::hasColumn('vendor_payments','tds_amount')) $t->decimal('tds_amount',18,4)->default(0);
                if (!Schema::hasColumn('vendor_payments','vds_amount')) $t->decimal('vds_amount',18,4)->default(0);
                // Existing amount is the GROSS AP settled, paid_in_* is the NET money transferred.
            });
        }
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders','vat_rate_snapshot')) {
            Schema::table('orders', function (Blueprint $t) { $t->decimal('vat_rate_snapshot', 7, 4)->nullable(); });
        }
    }
    public function down(): void
    {
        // Preserve financially significant histories and applied snapshots on rollback.
    }
};
