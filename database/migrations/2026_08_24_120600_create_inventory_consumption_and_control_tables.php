<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_inventory_consumptions')) {
            Schema::create('order_inventory_consumptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
                $table->string('trigger_source', 40)->index();
                $table->timestamp('consumed_at')->index();
                $table->unsignedBigInteger('stock_movement_id')->nullable()->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('order_id', 'order_inventory_consumptions_order_unique');
                $table->index(['branch_id', 'consumed_at'], 'order_inventory_consumptions_branch_time_idx');
            });
        }

        if (!Schema::hasTable('order_inventory_consumption_items')) {
            Schema::create('order_inventory_consumption_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_inventory_consumption_id');
                $table->foreignId('order_item_id')->constrained('order_details')->restrictOnDelete();
                $table->foreignId('menu_item_id')->constrained('food_items')->restrictOnDelete();
                $table->foreignId('recipe_id')->constrained('menu_item_recipes')->restrictOnDelete();
                $table->unsignedInteger('recipe_version_no');
                // Keep this as an indexed domain ID instead of a hard FK. Some existing/partial
                // inventory databases have a different integer definition on ingredients.id;
                // the inventory service validates the ingredient and this avoids MySQL errno 150.
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('quantity_base', 24, 8);
                $table->index('ingredient_id', 'oic_items_ingredient_idx');
                $table->timestamps();

                // Explicit short name: MySQL identifiers are limited to 64 characters.
                $table->foreign('order_inventory_consumption_id', 'oic_items_consumption_fk')
                    ->references('id')->on('order_inventory_consumptions')->cascadeOnDelete();

                $table->index(['order_inventory_consumption_id', 'ingredient_id'], 'order_inventory_consumption_items_header_ingredient_idx');
                $table->index(['menu_item_id', 'recipe_id'], 'order_inventory_consumption_items_menu_recipe_idx');
            });
        } else {
            // Repair a table left behind by the previous failed long FK-name attempt.
            // Because MySQL DDL auto-commits, the table can exist even though none of
            // the following FK/index commands were reached.
            $this->ensureForeignKey('order_inventory_consumption_items', 'order_inventory_consumption_id', 'order_inventory_consumptions', 'id', 'oic_items_consumption_fk', 'cascade');
            $this->ensureForeignKey('order_inventory_consumption_items', 'order_item_id', 'order_details', 'id', 'oic_items_order_item_fk', 'restrict');
            $this->ensureForeignKey('order_inventory_consumption_items', 'menu_item_id', 'food_items', 'id', 'oic_items_menu_item_fk', 'restrict');
            $this->ensureForeignKey('order_inventory_consumption_items', 'recipe_id', 'menu_item_recipes', 'id', 'oic_items_recipe_fk', 'restrict');
            // Do not repair/add a hard ingredient FK here. A previous failed migration can
            // leave ingredient_id with a legacy integer definition that is incompatible with
            // ingredients.id. It remains indexed and is validated by the inventory domain layer.
            $this->ensureIndex('order_inventory_consumption_items', ['ingredient_id'], 'oic_items_ingredient_idx');
            $this->ensureIndex('order_inventory_consumption_items', ['order_inventory_consumption_id', 'ingredient_id'], 'order_inventory_consumption_items_header_ingredient_idx');
            $this->ensureIndex('order_inventory_consumption_items', ['menu_item_id', 'recipe_id'], 'order_inventory_consumption_items_menu_recipe_idx');
        }

        if (!Schema::hasTable('inventory_wastages')) {
            Schema::create('inventory_wastages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                // Runtime service validates branch + stock location. Avoid the same
                // stock_locations FK compatibility problem seen in stock_transfers.
                $table->unsignedBigInteger('location_id');
                $table->string('wastage_no', 100);
                $table->string('reason_code', 40)->index();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('DRAFT')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['branch_id', 'wastage_no'], 'inventory_wastages_branch_number_unique');
                $table->index(['branch_id', 'location_id', 'posted_at'], 'inventory_wastages_branch_location_time_idx');
                $table->index('location_id', 'inventory_wastages_location_idx');
            });
        }

        if (!Schema::hasTable('inventory_wastage_items')) {
            Schema::create('inventory_wastage_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_wastage_id')->constrained('inventory_wastages')->cascadeOnDelete();
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('quantity', 24, 8);
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('conversion_factor_snapshot', 24, 8);
                $table->decimal('base_quantity', 24, 8);
                $table->timestamps();

                $table->unique(['inventory_wastage_id', 'ingredient_id'], 'inventory_wastage_items_header_ingredient_unique');
                $table->index('ingredient_id', 'inventory_wastage_items_ingredient_idx');
            });
        }

        if (!Schema::hasTable('inventory_adjustments')) {
            Schema::create('inventory_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->unsignedBigInteger('location_id');
                $table->string('adjustment_no', 100);
                $table->text('reason');
                $table->string('status', 20)->default('DRAFT')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['branch_id', 'adjustment_no'], 'inventory_adjustments_branch_number_unique');
                $table->index(['branch_id', 'location_id', 'posted_at'], 'inventory_adjustments_branch_location_time_idx');
                $table->index('location_id', 'inventory_adjustments_location_idx');
            });
        }

        if (!Schema::hasTable('inventory_adjustment_items')) {
            Schema::create('inventory_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('system_qty_base', 24, 8);
                $table->decimal('physical_qty_base', 24, 8);
                $table->decimal('difference_base', 24, 8);
                $table->timestamps();

                $table->unique(['inventory_adjustment_id', 'ingredient_id'], 'inventory_adjustment_items_header_ingredient_unique');
                $table->index('ingredient_id', 'inventory_adjustment_items_ingredient_idx');
            });
        }

        if (!Schema::hasTable('inventory_exceptions')) {
            Schema::create('inventory_exceptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->string('exception_type', 80)->index();
                $table->string('reference_type', 120)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('ingredient_id')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->decimal('shortage_base', 24, 8)->default(0);
                $table->string('status', 20)->default('OPEN')->index();
                $table->timestamp('detected_at')->index();
                $table->timestamp('resolved_at')->nullable()->index();
                $table->text('resolution_note')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['branch_id', 'status', 'detected_at'], 'inventory_exceptions_branch_status_time_idx');
                $table->index(['branch_id', 'reference_type', 'reference_id'], 'inventory_exceptions_reference_idx');
                $table->index(['ingredient_id', 'location_id', 'status'], 'inventory_exceptions_ingredient_location_status_idx');
                $table->index('location_id', 'inventory_exceptions_location_idx');
            });
        }

        if (Schema::hasTable('stock_transfers') && !Schema::hasColumn('stock_transfers', 'original_transfer_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->foreignId('original_transfer_id')->nullable()->after('kitchen_request_id')
                    ->constrained('stock_transfers')->nullOnDelete();
                $table->index(['branch_id', 'original_transfer_id'], 'stock_transfers_branch_original_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfers') && Schema::hasColumn('stock_transfers', 'original_transfer_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->dropForeign(['original_transfer_id']);
                $table->dropIndex('stock_transfers_branch_original_idx');
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

    private function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $constraintName,
        string $onDelete
    ): void {
        if ($this->foreignKeyExistsForColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $constraintName, $onDelete) {
            $foreign = $blueprint->foreign($column, $constraintName)
                ->references($referencedColumn)
                ->on($referencedTable);
            $foreign->onDelete($onDelete);
        });
    }

    private function ensureIndex(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function foreignKeyExistsForColumn(string $table, string $column): bool
    {
        $database = DB::connection()->getDatabaseName();

        return DB::table('information_schema.key_column_usage')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->whereNotNull('referenced_table_name')
            ->exists();
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::connection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
