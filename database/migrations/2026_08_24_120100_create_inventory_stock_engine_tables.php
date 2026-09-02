<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventory_balances')) {
            Schema::create('inventory_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
                $table->decimal('quantity_base', 24, 8)->default(0);
                $table->timestamps();

                $table->unique(['branch_id', 'stock_location_id', 'ingredient_id'], 'inventory_balances_branch_location_ingredient_unique');
                $table->index(['branch_id', 'ingredient_id'], 'inventory_balances_branch_ingredient_idx');
            });
        }

        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->string('movement_no', 80);
                $table->string('movement_type', 40)->index();
                $table->string('reference_type', 120)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->foreignId('source_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
                $table->foreignId('destination_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
                $table->string('status', 20)->default('DRAFT')->index();
                $table->timestamp('occurred_at')->index();
                $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'movement_no'], 'stock_movements_branch_number_unique');
                $table->index(['branch_id', 'movement_type', 'occurred_at'], 'stock_movements_branch_type_time_idx');
                $table->index(['branch_id', 'reference_type', 'reference_id', 'movement_type'], 'stock_movements_reference_idx');
                $table->index(['branch_id', 'source_location_id', 'occurred_at'], 'stock_movements_source_time_idx');
                $table->index(['branch_id', 'destination_location_id', 'occurred_at'], 'stock_movements_destination_time_idx');
            });
        }

        if (!Schema::hasTable('stock_movement_items')) {
            Schema::create('stock_movement_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
                $table->decimal('quantity_base', 24, 8);
                $table->decimal('source_before', 24, 8)->nullable();
                $table->decimal('source_after', 24, 8)->nullable();
                $table->decimal('destination_before', 24, 8)->nullable();
                $table->decimal('destination_after', 24, 8)->nullable();
                $table->timestamps();

                $table->index(['ingredient_id', 'stock_movement_id'], 'stock_movement_items_ingredient_movement_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_items');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_balances');
    }
};
