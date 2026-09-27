<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_inventory_consumptions')) {
            Schema::create('order_inventory_consumptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
                $table->string('trigger_source', 40)->index();
                $table->timestamp('consumed_at')->index();
                $table->foreignId('stock_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique('order_id', 'order_inventory_consumptions_order_unique');
            });
        }

        // MySQL limits identifier names to 64 characters. A failed previous run may have
        // created this child table before the auto-generated FK name exceeded that limit.
        // If that partial table is empty, recreate it cleanly with explicit short FK names.
        if (Schema::hasTable('order_inventory_consumption_items')) {
            if (DB::table('order_inventory_consumption_items')->count() === 0) {
                Schema::drop('order_inventory_consumption_items');
            } else {
                throw new RuntimeException(
                    'order_inventory_consumption_items already exists with data. Review it before rerunning this migration.'
                );
            }
        }

        Schema::create('order_inventory_consumption_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_inventory_consumption_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('menu_item_id');
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedInteger('recipe_version_no');
            $table->unsignedBigInteger('ingredient_id');
            $table->decimal('quantity_base', 24, 8);
            $table->timestamps();

            $table->foreign('order_inventory_consumption_id', 'oic_items_consumption_fk')
                ->references('id')->on('order_inventory_consumptions')->cascadeOnDelete();
            $table->foreign('order_item_id', 'oic_items_order_item_fk')
                ->references('id')->on('order_details')->restrictOnDelete();
            $table->foreign('menu_item_id', 'oic_items_menu_item_fk')
                ->references('id')->on('food_items')->restrictOnDelete();
            $table->foreign('recipe_id', 'oic_items_recipe_fk')
                ->references('id')->on('menu_item_recipes')->restrictOnDelete();
            $table->foreign('ingredient_id', 'oic_items_ingredient_fk')
                ->references('id')->on('ingredients')->restrictOnDelete();

            $table->index(['order_inventory_consumption_id', 'ingredient_id'], 'oic_items_header_ingredient_idx');
            $table->index(['menu_item_id', 'recipe_id'], 'oic_items_menu_recipe_idx');
        });

        if (!Schema::hasTable('inventory_wastages')) {
            Schema::create('inventory_wastages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
                $table->string('wastage_no', 100)->unique();
                $table->string('reason_code', 40)->index();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('DRAFT')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable()->index();
                $table->timestamps();
                $table->index(['location_id', 'posted_at'], 'inventory_wastages_location_time_idx');
            });
        }

        if (!Schema::hasTable('inventory_wastage_items')) {
            Schema::create('inventory_wastage_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_wastage_id')->constrained('inventory_wastages')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
                $table->decimal('quantity', 24, 8);
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('conversion_factor_snapshot', 24, 8);
                $table->decimal('base_quantity', 24, 8);
                $table->timestamps();
                $table->unique(['inventory_wastage_id', 'ingredient_id'], 'inventory_wastage_items_header_ingredient_unique');
            });
        }

        if (!Schema::hasTable('inventory_adjustments')) {
            Schema::create('inventory_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
                $table->string('adjustment_no', 100)->unique();
                $table->text('reason');
                $table->string('status', 20)->default('DRAFT')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable()->index();
                $table->timestamps();
                $table->index(['location_id', 'posted_at'], 'inventory_adjustments_location_time_idx');
            });
        }

        if (!Schema::hasTable('inventory_adjustment_items')) {
            Schema::create('inventory_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
                $table->decimal('system_qty_base', 24, 8);
                $table->decimal('physical_qty_base', 24, 8);
                $table->decimal('difference_base', 24, 8);
                $table->timestamps();
                $table->unique(['inventory_adjustment_id', 'ingredient_id'], 'inventory_adjustment_items_header_ingredient_unique');
            });
        }

        if (!Schema::hasTable('inventory_exceptions')) {
            Schema::create('inventory_exceptions', function (Blueprint $table) {
                $table->id();
                $table->string('exception_type', 80)->index();
                $table->string('reference_type', 120)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
                $table->decimal('shortage_base', 24, 8)->default(0);
                $table->string('status', 20)->default('OPEN')->index();
                $table->timestamp('detected_at')->index();
                $table->timestamp('resolved_at')->nullable()->index();
                $table->text('resolution_note')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'detected_at'], 'inventory_exceptions_status_time_idx');
                $table->index(['reference_type', 'reference_id'], 'inventory_exceptions_reference_idx');
                $table->index(['ingredient_id', 'location_id', 'status'], 'inventory_exceptions_ingredient_location_status_idx');
            });
        }

        if (Schema::hasTable('stock_transfers') && !Schema::hasColumn('stock_transfers', 'original_transfer_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->foreignId('original_transfer_id')->nullable()->after('kitchen_request_id')
                    ->constrained('stock_transfers')->nullOnDelete();
                $table->index('original_transfer_id', 'stock_transfers_original_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfers') && Schema::hasColumn('stock_transfers', 'original_transfer_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->dropForeign(['original_transfer_id']);
                $table->dropIndex('stock_transfers_original_idx');
                $table->dropColumn('original_transfer_id');
            });
        }
        Schema::dropIfExists('inventory_exceptions');
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_wastage_items');
        Schema::dropIfExists('inventory_wastages');
        Schema::dropIfExists('order_inventory_consumption_items');
        Schema::dropIfExists('order_inventory_consumptions');
    }
};
