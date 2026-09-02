<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('phone', 60)->nullable();
                $table->string('email', 160)->nullable();
                $table->text('address')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index('name', 'vendors_name_idx');
            });
        }

        if (!Schema::hasTable('vendor_ingredients')) {
            Schema::create('vendor_ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->string('vendor_sku', 120)->nullable();
                $table->decimal('last_price', 18, 4)->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'ingredient_id'], 'vendor_ingredients_vendor_ingredient_unique');
            });
        }

        if (!Schema::hasTable('purchases')) {
            Schema::create('purchases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
                $table->string('purchase_no', 100);
                $table->date('purchase_date')->index();
                $table->string('invoice_no', 120)->nullable();
                $table->string('reference_no', 120)->nullable();
                $table->string('status', 24)->default('DRAFT')->index();
                $table->decimal('subtotal', 18, 4)->default(0);
                $table->decimal('discount', 18, 4)->default(0);
                $table->decimal('tax', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('received_at')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('received_stock_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['branch_id', 'purchase_no'], 'purchases_branch_number_unique');
                $table->index(['branch_id', 'status', 'purchase_date'], 'purchases_branch_status_date_idx');
                $table->index(['branch_id', 'vendor_id', 'purchase_date'], 'purchases_branch_vendor_date_idx');
                $table->index(['branch_id', 'invoice_no'], 'purchases_branch_invoice_idx');
            });
        }

        if (!Schema::hasTable('purchase_items')) {
            Schema::create('purchase_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
                $table->decimal('quantity', 24, 8);
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('conversion_factor_snapshot', 24, 8);
                $table->decimal('base_quantity', 24, 8);
                $table->decimal('unit_price', 18, 4);
                $table->decimal('line_total', 18, 4);
                $table->timestamps();

                $table->unique(['purchase_id', 'ingredient_id'], 'purchase_items_purchase_ingredient_unique');
                $table->index(['ingredient_id', 'purchase_id'], 'purchase_items_ingredient_purchase_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('vendor_ingredients');
        Schema::dropIfExists('vendors');
    }
};
