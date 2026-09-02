<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('food_items') && !Schema::hasColumn('food_items', 'inventory_tracking')) {
            Schema::table('food_items', function (Blueprint $table) {
                $table->boolean('inventory_tracking')->default(false)->index()->after('is_draft');
            });
        }

        if (!Schema::hasTable('menu_item_recipes')) {
            Schema::create('menu_item_recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('menu_item_id')->constrained('food_items')->cascadeOnDelete();
                $table->unsignedInteger('version_no');
                $table->decimal('yield_quantity', 24, 8)->default(1);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('effective_from')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['menu_item_id', 'version_no'], 'menu_item_recipes_item_version_unique');
                $table->index(['menu_item_id', 'is_active'], 'menu_item_recipes_item_active_idx');
            });
        }

        if (!Schema::hasTable('menu_item_recipe_items')) {
            Schema::create('menu_item_recipe_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('menu_item_recipe_id')->constrained('menu_item_recipes')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
                $table->decimal('input_quantity', 24, 8);
                $table->foreignId('input_unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('base_quantity', 24, 8);
                $table->timestamps();

                $table->unique(['menu_item_recipe_id', 'ingredient_id'], 'menu_item_recipe_items_recipe_ingredient_unique');
                $table->index(['ingredient_id', 'menu_item_recipe_id'], 'menu_item_recipe_items_ingredient_recipe_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_recipe_items');
        Schema::dropIfExists('menu_item_recipes');

        if (Schema::hasTable('food_items') && Schema::hasColumn('food_items', 'inventory_tracking')) {
            Schema::table('food_items', function (Blueprint $table) {
                $table->dropColumn('inventory_tracking');
            });
        }
    }
};
