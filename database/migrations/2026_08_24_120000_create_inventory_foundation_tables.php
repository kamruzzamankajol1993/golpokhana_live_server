<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                $table->string('symbol', 20)->unique();
                $table->string('dimension', 20)->index();
                $table->boolean('is_base')->default(false)->index();
                $table->decimal('standard_to_base_factor', 24, 8)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ingredients')) {
            Schema::create('ingredients', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160)->unique();
                $table->string('code', 80)->nullable()->unique();
                $table->string('measurement_dimension', 20)->index();
                $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
                $table->boolean('track_inventory')->default(true)->index();
                $table->decimal('low_stock_level_base', 24, 8)->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ingredient_unit_conversions')) {
            Schema::create('ingredient_unit_conversions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('factor_to_base', 24, 8);
                $table->boolean('purchase_allowed')->default(true);
                $table->boolean('recipe_allowed')->default(true);
                $table->date('effective_from')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['ingredient_id', 'unit_id'], 'ingredient_unit_conversions_ingredient_unit_unique');
            });
        }

        if (!Schema::hasTable('stock_locations')) {
            Schema::create('stock_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->string('code', 40);
                $table->string('name', 120);
                $table->string('type', 30)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['branch_id', 'code'], 'stock_locations_branch_code_unique');
                $table->index(['branch_id', 'type', 'is_active'], 'stock_locations_branch_type_active_idx');
            });
        }

        $this->seedStandardUnits();
        $this->seedDefaultStockLocations();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('ingredient_unit_conversions');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('units');
    }

    private function seedStandardUnits(): void
    {
        if (!Schema::hasTable('units')) {
            return;
        }

        $now = now();
        $units = [
            ['name' => 'Gram', 'symbol' => 'g', 'dimension' => 'WEIGHT', 'is_base' => 1, 'standard_to_base_factor' => '1.00000000'],
            ['name' => 'Kilogram', 'symbol' => 'kg', 'dimension' => 'WEIGHT', 'is_base' => 0, 'standard_to_base_factor' => '1000.00000000'],
            ['name' => 'Milliliter', 'symbol' => 'ml', 'dimension' => 'VOLUME', 'is_base' => 1, 'standard_to_base_factor' => '1.00000000'],
            ['name' => 'Liter', 'symbol' => 'L', 'dimension' => 'VOLUME', 'is_base' => 0, 'standard_to_base_factor' => '1000.00000000'],
            ['name' => 'Piece', 'symbol' => 'pcs', 'dimension' => 'COUNT', 'is_base' => 1, 'standard_to_base_factor' => '1.00000000'],
            ['name' => 'Dozen', 'symbol' => 'doz', 'dimension' => 'COUNT', 'is_base' => 0, 'standard_to_base_factor' => '12.00000000'],
            ['name' => 'Packet', 'symbol' => 'pkt', 'dimension' => 'PACKAGE', 'is_base' => 0, 'standard_to_base_factor' => null],
            ['name' => 'Bottle', 'symbol' => 'btl', 'dimension' => 'PACKAGE', 'is_base' => 0, 'standard_to_base_factor' => null],
            ['name' => 'Bag', 'symbol' => 'bag', 'dimension' => 'PACKAGE', 'is_base' => 0, 'standard_to_base_factor' => null],
            ['name' => 'Box', 'symbol' => 'box', 'dimension' => 'PACKAGE', 'is_base' => 0, 'standard_to_base_factor' => null],
            ['name' => 'Carton', 'symbol' => 'ctn', 'dimension' => 'PACKAGE', 'is_base' => 0, 'standard_to_base_factor' => null],
        ];

        foreach ($units as $unit) {
            DB::table('units')->updateOrInsert(
                ['name' => $unit['name']],
                $unit + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    private function seedDefaultStockLocations(): void
    {
        if (!Schema::hasTable('branches') || !Schema::hasTable('stock_locations')) {
            return;
        }

        $now = now();
        foreach (DB::table('branches')->pluck('id') as $branchId) {
            foreach ([
                ['code' => 'MAIN', 'name' => 'Main Stock', 'type' => 'MAIN'],
                ['code' => 'KITCHEN', 'name' => 'Kitchen Stock', 'type' => 'KITCHEN'],
            ] as $location) {
                DB::table('stock_locations')->updateOrInsert(
                    ['branch_id' => (int) $branchId, 'code' => $location['code']],
                    $location + ['branch_id' => (int) $branchId, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }
};
