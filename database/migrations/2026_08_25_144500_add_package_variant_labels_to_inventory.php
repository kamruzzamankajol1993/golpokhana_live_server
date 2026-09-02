<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ingredient_unit_conversions')) {
            if (!Schema::hasColumn('ingredient_unit_conversions', 'label')) {
                Schema::table('ingredient_unit_conversions', function (Blueprint $table) {
                    $table->string('label', 160)->nullable()->after('unit_id');
                });
            }

            // MySQL may use the old UNIQUE (ingredient_id, unit_id) index to support
            // the ingredient_id foreign key. Create a replacement supporting index
            // BEFORE dropping the unique index, otherwise MySQL raises error 1553.
            if (!$this->indexExists('ingredient_unit_conversions', 'ingredient_unit_conversions_ingredient_unit_idx')) {
                Schema::table('ingredient_unit_conversions', function (Blueprint $table) {
                    $table->index(['ingredient_id', 'unit_id'], 'ingredient_unit_conversions_ingredient_unit_idx');
                });
            }

            if ($this->indexExists('ingredient_unit_conversions', 'ingredient_unit_conversions_ingredient_unit_unique')) {
                Schema::table('ingredient_unit_conversions', function (Blueprint $table) {
                    $table->dropUnique('ingredient_unit_conversions_ingredient_unit_unique');
                });
            }

            $rows = DB::table('ingredient_unit_conversions as c')
                ->join('units as package_unit', 'package_unit.id', '=', 'c.unit_id')
                ->join('ingredients as ingredient', 'ingredient.id', '=', 'c.ingredient_id')
                ->join('units as base_unit', 'base_unit.id', '=', 'ingredient.base_unit_id')
                ->where(function ($query) {
                    $query->whereNull('c.label')->orWhere('c.label', '');
                })
                ->select(['c.id', 'c.factor_to_base', 'package_unit.name as package_name', 'base_unit.symbol as base_symbol'])
                ->get();

            foreach ($rows as $row) {
                DB::table('ingredient_unit_conversions')->where('id', $row->id)->update([
                    'label' => $row->package_name . ' (' . $this->formatFactor((string) $row->factor_to_base) . ' ' . $row->base_symbol . ')',
                ]);
            }
        }

        $this->addPackageConversionColumn('purchase_items', 'unit_id');
        $this->addPackageConversionColumn('menu_item_recipe_items', 'input_unit_id');
        $this->addPackageConversionColumn('kitchen_request_ingredient_items', 'display_unit_id');
        $this->addPackageConversionColumn('stock_transfer_items', 'unit_id');
        $this->addPackageConversionColumn('inventory_wastage_items', 'unit_id');
    }

    public function down(): void
    {
        // Once multiple variants such as Packet (500 g) and Packet (1000 g)
        // exist for the same ingredient/unit pair, restoring the old unique
        // constraint would be destructive/impossible. Stop before making a
        // partial rollback and ask the operator to resolve duplicates first.
        if (Schema::hasTable('ingredient_unit_conversions') && $this->hasDuplicateIngredientUnitPairs()) {
            throw new RuntimeException(
                'Cannot roll back package variants while multiple conversions exist for the same ingredient and package unit. '
                . 'Remove/merge duplicate variants first, then retry the rollback.'
            );
        }

        $this->dropPackageConversionColumn('inventory_wastage_items');
        $this->dropPackageConversionColumn('stock_transfer_items');
        $this->dropPackageConversionColumn('kitchen_request_ingredient_items');
        $this->dropPackageConversionColumn('menu_item_recipe_items');
        $this->dropPackageConversionColumn('purchase_items');

        if (Schema::hasTable('ingredient_unit_conversions')) {
            // Restore the old unique index first. It also provides the left-most
            // ingredient_id index required by the foreign key, so the replacement
            // non-unique index can then be removed safely.
            if (!$this->indexExists('ingredient_unit_conversions', 'ingredient_unit_conversions_ingredient_unit_unique')) {
                Schema::table('ingredient_unit_conversions', function (Blueprint $table) {
                    $table->unique(['ingredient_id', 'unit_id'], 'ingredient_unit_conversions_ingredient_unit_unique');
                });
            }

            if ($this->indexExists('ingredient_unit_conversions', 'ingredient_unit_conversions_ingredient_unit_idx')) {
                Schema::table('ingredient_unit_conversions', function (Blueprint $table) {
                    $table->dropIndex('ingredient_unit_conversions_ingredient_unit_idx');
                });
            }

            if (Schema::hasColumn('ingredient_unit_conversions', 'label')) {
                Schema::table('ingredient_unit_conversions', function (Blueprint $table) {
                    $table->dropColumn('label');
                });
            }
        }
    }

    private function addPackageConversionColumn(string $tableName, string $after): void
    {
        if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'package_conversion_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($after, $tableName) {
            $table->unsignedBigInteger('package_conversion_id')->nullable()->after($after);
            $table->index('package_conversion_id', $tableName . '_package_conversion_idx');
        });
    }

    private function dropPackageConversionColumn(string $tableName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'package_conversion_id')) {
            return;
        }

        $indexName = $tableName . '_package_conversion_idx';
        $hasIndex = $this->indexExists($tableName, $indexName);
        Schema::table($tableName, function (Blueprint $table) use ($indexName, $hasIndex) {
            if ($hasIndex) {
                $table->dropIndex($indexName);
            }
            $table->dropColumn('package_conversion_id');
        });
    }

    private function hasDuplicateIngredientUnitPairs(): bool
    {
        if (!Schema::hasTable('ingredient_unit_conversions')) {
            return false;
        }

        return DB::table('ingredient_unit_conversions')
            ->select(['ingredient_id', 'unit_id'])
            ->groupBy('ingredient_id', 'unit_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
    }

    private function formatFactor(string $value): string
    {
        $value = rtrim(rtrim($value, '0'), '.');
        return $value === '' ? '0' : $value;
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
