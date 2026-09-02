<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kitchen_requests')) {
            Schema::create('kitchen_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->string('request_no', 100);
                $table->string('request_type', 20)->index();
                $table->date('request_date')->index();
                $table->string('status', 30)->default('DRAFT')->index();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'request_no'], 'kitchen_requests_branch_number_unique');
                $table->index(['branch_id', 'status', 'request_date'], 'kitchen_requests_branch_status_date_idx');
                $table->index(['branch_id', 'request_type', 'request_date'], 'kitchen_requests_branch_type_date_idx');
            });
        }

        if (!Schema::hasTable('kitchen_request_food_items')) {
            Schema::create('kitchen_request_food_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kitchen_request_id')->constrained('kitchen_requests')->cascadeOnDelete();
                $table->foreignId('menu_item_id')->constrained('food_items')->restrictOnDelete();
                $table->decimal('requested_food_qty', 24, 8);
                $table->foreignId('recipe_id')->constrained('menu_item_recipes')->restrictOnDelete();
                $table->unsignedInteger('recipe_version_no');
                $table->timestamps();

                $table->unique(['kitchen_request_id', 'menu_item_id'], 'kitchen_request_food_request_menu_unique');
                $table->index(['recipe_id', 'kitchen_request_id'], 'kitchen_request_food_recipe_request_idx');
            });
        }

        if (!Schema::hasTable('kitchen_request_ingredient_items')) {
            Schema::create('kitchen_request_ingredient_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kitchen_request_id')->constrained('kitchen_requests')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
                $table->string('source_kind', 20);
                $table->decimal('input_quantity', 24, 8)->nullable();
                $table->decimal('conversion_factor_snapshot', 24, 8)->nullable();
                $table->decimal('required_base_qty', 24, 8);
                $table->decimal('approved_base_qty', 24, 8)->default(0);
                $table->decimal('issued_base_qty', 24, 8)->default(0);
                $table->foreignId('display_unit_id')->constrained('units')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['kitchen_request_id', 'ingredient_id'], 'kitchen_request_ingredient_request_unique');
                $table->index(['ingredient_id', 'kitchen_request_id'], 'kitchen_request_ingredient_request_idx');
            });
        }

        if (!Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->string('transfer_no', 100);
                $table->string('direction', 30)->index();
                $table->foreignId('kitchen_request_id')->nullable()->constrained('kitchen_requests')->restrictOnDelete();
                $table->foreignId('source_location_id')->constrained('stock_locations')->restrictOnDelete();
                $table->foreignId('destination_location_id')->constrained('stock_locations')->restrictOnDelete();
                $table->string('status', 20)->default('DRAFT')->index();
                $table->string('idempotency_key', 80)->nullable();
                $table->foreignId('posted_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'transfer_no'], 'stock_transfers_branch_number_unique');
                $table->unique(['branch_id', 'idempotency_key'], 'stock_transfers_branch_idempotency_unique');
                $table->index(['branch_id', 'direction', 'posted_at'], 'stock_transfers_branch_direction_time_idx');
                $table->index(['kitchen_request_id', 'status'], 'stock_transfers_request_status_idx');
            });
        }

        if (!Schema::hasTable('stock_transfer_items')) {
            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
                $table->decimal('quantity', 24, 8);
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('conversion_factor_snapshot', 24, 8);
                $table->decimal('base_quantity', 24, 8);
                $table->timestamps();

                $table->unique(['stock_transfer_id', 'ingredient_id'], 'stock_transfer_items_transfer_ingredient_unique');
                $table->index(['ingredient_id', 'stock_transfer_id'], 'stock_transfer_items_ingredient_transfer_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('kitchen_request_ingredient_items');
        Schema::dropIfExists('kitchen_request_food_items');
        Schema::dropIfExists('kitchen_requests');
    }
};
