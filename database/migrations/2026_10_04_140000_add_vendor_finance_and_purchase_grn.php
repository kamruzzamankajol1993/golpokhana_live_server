<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                if (!Schema::hasColumn('vendors', 'tin')) {
                    $table->string('tin', 160)->nullable();
                }
                if (!Schema::hasColumn('vendors', 'tin_file_path')) {
                    $table->string('tin_file_path')->nullable();
                }
                if (!Schema::hasColumn('vendors', 'tin_file_name')) {
                    $table->string('tin_file_name')->nullable();
                }
                if (!Schema::hasColumn('vendors', 'bin')) {
                    $table->string('bin', 160)->nullable();
                }
                if (!Schema::hasColumn('vendors', 'bin_file_path')) {
                    $table->string('bin_file_path')->nullable();
                }
                if (!Schema::hasColumn('vendors', 'bin_file_name')) {
                    $table->string('bin_file_name')->nullable();
                }
                if (!Schema::hasColumn('vendors', 'tds')) {
                    $table->string('tds', 160)->nullable();
                }
                if (!Schema::hasColumn('vendors', 'vds')) {
                    $table->string('vds', 160)->nullable();
                }
                if (!Schema::hasColumn('vendors', 'tax')) {
                    $table->string('tax', 160)->nullable();
                }
                if (!Schema::hasColumn('vendors', 'tax_file_path')) {
                    $table->string('tax_file_path')->nullable();
                }
                if (!Schema::hasColumn('vendors', 'tax_file_name')) {
                    $table->string('tax_file_name')->nullable();
                }
            });
        }

        if (!Schema::hasTable('vendor_payments')) {
            Schema::create('vendor_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
                $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
                $table->string('payment_no', 100)->unique();
                $table->date('payment_date')->index();
                $table->string('payment_type', 30)->index();
                $table->decimal('amount', 18, 4)->default(0);
                $table->decimal('paid_in_cash', 18, 4)->default(0);
                $table->decimal('paid_in_card', 18, 4)->default(0);
                $table->decimal('paid_in_mfs', 18, 4)->default(0);
                $table->string('card_type', 100)->nullable();
                $table->string('mfs_provider', 100)->nullable();
                $table->string('card_reference', 255)->nullable();
                $table->string('mfs_reference', 255)->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['vendor_id', 'payment_date'], 'vendor_payments_vendor_date_idx');
                $table->index(['purchase_id', 'payment_date'], 'vendor_payments_purchase_date_idx');
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (!Schema::hasColumn('purchases', 'grn')) {
                    $table->text('grn')->nullable();
                }
                if (!Schema::hasColumn('purchases', 'grn_status')) {
                    $table->string('grn_status', 24)->default('PENDING')->index();
                }
                if (!Schema::hasColumn('purchases', 'grn_confirmed_at')) {
                    $table->timestamp('grn_confirmed_at')->nullable();
                }
                if (!Schema::hasColumn('purchases', 'grn_confirmed_by')) {
                    $table->foreignId('grn_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (Schema::hasColumn('purchases', 'grn_confirmed_by')) {
                    $table->dropConstrainedForeignId('grn_confirmed_by');
                }
                $columns = array_values(array_filter(
                    ['grn', 'grn_status', 'grn_confirmed_at'],
                    fn ($column) => Schema::hasColumn('purchases', $column)
                ));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('vendor_payments');

        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    'tin', 'tin_file_path', 'tin_file_name',
                    'bin', 'bin_file_path', 'bin_file_name',
                    'tds', 'vds', 'tax', 'tax_file_path', 'tax_file_name',
                ], fn ($column) => Schema::hasColumn('vendors', $column)));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
