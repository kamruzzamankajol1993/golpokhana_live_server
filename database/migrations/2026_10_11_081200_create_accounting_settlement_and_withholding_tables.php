<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL's identifier limit is 64 characters. Explicit short names are
        // required for the payment_account_transaction_id indexes/foreign keys.
        // The hasTable guards also allow a retry after MySQL has already
        // created the first table but failed while adding a foreign key.
        if (! Schema::hasTable('accounting_bank_settlements')) {
            Schema::create('accounting_bank_settlements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_account_transaction_id');
                $table->foreignId('journal_entry_id');
                $table->decimal('gross_amount', 18, 4);
                $table->decimal('fee', 18, 4);
                $table->decimal('bank_net', 18, 4);
                $table->string('reference', 200);
                $table->string('status', 20)->default('DRAFT');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounting_withholdings')) {
            Schema::create('accounting_withholdings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('purchase_id');
                $table->string('tax_type', 5);
                $table->decimal('amount', 18, 4);
                $table->string('reference', 200);
                $table->text('evidence');
                $table->foreignId('journal_entry_id');
                $table->string('status', 20)->default('DRAFT');
                $table->timestamps();
            });
        }

        // Repair missing indexes/FKs if a prior attempt failed midway.
        // Never drop or truncate a potentially existing table/data.
        $this->ensureUnique('accounting_bank_settlements', 'payment_account_transaction_id', 'acct_bsettle_payment_uq');
        $this->ensureUnique('accounting_bank_settlements', 'journal_entry_id', 'acct_bsettle_journal_uq');
        $this->ensureForeign('accounting_bank_settlements', 'payment_account_transaction_id', 'payment_account_transactions', 'acct_bsettle_payment_fk');
        $this->ensureForeign('accounting_bank_settlements', 'journal_entry_id', 'journal_entries', 'acct_bsettle_journal_fk');

        $this->ensureUnique('accounting_withholdings', 'journal_entry_id', 'acct_withholding_journal_uq');
        $this->ensureUniqueColumns('accounting_withholdings', ['purchase_id', 'tax_type', 'reference'], 'acct_tax_unique');
        $this->ensureForeign('accounting_withholdings', 'purchase_id', 'purchases', 'acct_withholding_purchase_fk');
        $this->ensureForeign('accounting_withholdings', 'journal_entry_id', 'journal_entries', 'acct_withholding_journal_fk');
    }

    private function ensureUnique(string $table, string $column, string $name): void
    {
        $this->ensureUniqueColumns($table, [$column], $name);
    }

    private function ensureUniqueColumns(string $table, array $columns, string $name): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === $columns) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->unique($columns, $name);
        });
    }

    private function ensureForeign(string $table, string $column, string $references, string $name): void
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['columns'] ?? []) !== [$column]) {
                continue;
            }

            if (($foreignKey['foreign_table'] ?? null) !== $references ||
                ($foreignKey['foreign_columns'] ?? []) !== ['id']) {
                throw new RuntimeException("Existing foreign key on {$table}.{$column} targets an unexpected table/column; refusing to overwrite it.");
            }
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $name): void {
            $blueprint->foreign($column, $name)->references('id')->on($references)->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_withholdings');
        Schema::dropIfExists('accounting_bank_settlements');
    }
};
