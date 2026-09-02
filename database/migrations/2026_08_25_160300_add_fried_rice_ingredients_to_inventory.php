<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ingredients') || !Schema::hasTable('units')) {
            throw new RuntimeException('Inventory tables are missing. Run the inventory foundation migration first.');
        }

        $baseUnits = DB::table('units')
            ->whereIn('name', ['Gram', 'Milliliter', 'Piece'])
            ->pluck('id', 'name');

        foreach (['Gram', 'Milliliter', 'Piece'] as $requiredUnit) {
            if (!$baseUnits->has($requiredUnit)) {
                throw new RuntimeException("Required base unit [{$requiredUnit}] was not found in the units table.");
            }
        }

        // Salt is intentionally excluded because it already exists in the user's database.
        $ingredients = [
            ['name' => 'Cooked Rice',         'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Egg',                 'dimension' => 'COUNT',  'unit' => 'Piece'],
            ['name' => 'Carrot',              'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Green Peas',          'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Capsicum',            'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Spring Onion',        'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Onion',               'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Garlic',              'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Soy Sauce',           'dimension' => 'VOLUME', 'unit' => 'Milliliter'],
            ['name' => 'Cooking Oil',         'dimension' => 'VOLUME', 'unit' => 'Milliliter'],
            ['name' => 'Black Pepper Powder', 'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'MSG / Tasting Salt',  'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Green Chili',         'dimension' => 'WEIGHT', 'unit' => 'Gram'],
            ['name' => 'Sesame Oil',          'dimension' => 'VOLUME', 'unit' => 'Milliliter'],
            ['name' => 'Boneless Chicken',    'dimension' => 'WEIGHT', 'unit' => 'Gram'],
        ];

        $now = now();

        DB::transaction(function () use ($ingredients, $baseUnits, $now) {
            foreach ($ingredients as $ingredient) {
                // Do not overwrite an ingredient the user has already created.
                if (DB::table('ingredients')->where('name', $ingredient['name'])->exists()) {
                    continue;
                }

                DB::table('ingredients')->insert([
                    'name' => $ingredient['name'],
                    'code' => null,
                    'measurement_dimension' => $ingredient['dimension'],
                    'base_unit_id' => (int) $baseUnits[$ingredient['unit']],
                    'track_inventory' => true,
                    'low_stock_level_base' => '0.00000000',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty.
        // This is master inventory data and may be referenced by purchases, recipes,
        // stock movements, or balances after the migration runs. Automatically
        // deleting it during rollback could break inventory history.
    }
};
