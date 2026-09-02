<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $settingTables = [
        'restaurant_settings',
        'tax_settings',
        'invoice_settings',
        'pos_settings',
        'reward_point_settings',
        'hr_settings',
        'attendance_settings',
        'payroll_settings',
    ];

    public function up(): void
    {
        $mainBranchId = (int) (DB::table('branches')->where('is_main', 1)->value('id')
            ?: DB::table('branches')->orderBy('id')->value('id'));

        if (!$mainBranchId) {
            throw new RuntimeException('Phase 5/6 requires the Phase 1 branches migration first.');
        }

        $this->backfillCriticalBranchIds($mainBranchId);
        $this->assertOneSettingRowPerBranch();
        $this->cloneMainSettingsIntoExistingBranches($mainBranchId);
        $this->addSettingUniqueness();
        $this->extendInvoiceNumberingSettings();
        $this->extendOrdersForInvoiceAndQrReference();
        $this->convertOrderAndKotUniqueness();
        $this->createBranchNumberSequences();
        $this->seedBranchSequences();
        $this->createOfflinePosDevices();
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_pos_devices');
        Schema::dropIfExists('branch_number_sequences');

        if (Schema::hasTable('order_kots') && Schema::hasColumn('order_kots', 'branch_id')) {
            if ($this->indexExists('order_kots', 'order_kots_branch_kot_number_unique')) {
                Schema::table('order_kots', function (Blueprint $table) {
                    $table->dropUnique('order_kots_branch_kot_number_unique');
                });
            }
            if ($this->indexExists('order_kots', 'order_kots_branch_kot_number_idx')) {
                Schema::table('order_kots', function (Blueprint $table) {
                    $table->dropIndex('order_kots_branch_kot_number_idx');
                });
            }
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'branch_id')) {
            foreach (['orders_branch_order_number_unique', 'orders_branch_invoice_number_unique', 'orders_qr_reference_unique'] as $indexName) {
                if ($this->indexExists('orders', $indexName)) {
                    Schema::table('orders', function (Blueprint $table) use ($indexName) {
                        $table->dropUnique($indexName);
                    });
                }
            }

            // Only restore the old global order_number unique if the current data still permits it.
            $duplicates = DB::table('orders')
                ->select('order_number', DB::raw('COUNT(*) as aggregate'))
                ->whereNotNull('order_number')
                ->groupBy('order_number')
                ->having('aggregate', '>', 1)
                ->exists();

            if (!$duplicates && !$this->indexExists('orders', 'orders_order_number_unique')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->unique('order_number', 'orders_order_number_unique');
                });
            }

            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'invoice_number')) {
                    $table->dropColumn('invoice_number');
                }
                if (Schema::hasColumn('orders', 'qr_reference')) {
                    $table->dropColumn('qr_reference');
                }
            });
        }

        if (Schema::hasTable('invoice_settings')) {
            $columns = [
                'order_prefix', 'order_starting_number', 'order_padding',
                'invoice_padding', 'kot_prefix', 'kot_starting_number', 'kot_padding',
            ];
            $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn('invoice_settings', $column)));
            if ($existing) {
                Schema::table('invoice_settings', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }

        foreach ($this->settingTables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }
            $indexName = $this->settingUniqueName($tableName);
            if ($this->indexExists($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
        }
    }

    private function backfillCriticalBranchIds(int $mainBranchId): void
    {
        foreach (array_merge($this->settingTables, [
            'orders', 'order_kots', 'pos_sessions', 'pos_deleted_item_histories',
        ]) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                DB::table($table)->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
            }
        }
    }

    private function assertOneSettingRowPerBranch(): void
    {
        foreach ($this->settingTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            $duplicate = DB::table($table)
                ->select('branch_id', DB::raw('COUNT(*) as aggregate'))
                ->whereNotNull('branch_id')
                ->groupBy('branch_id')
                ->having('aggregate', '>', 1)
                ->first();

            if ($duplicate) {
                throw new RuntimeException(
                    "Cannot continue Phase 5: {$table} contains more than one settings row for branch {$duplicate->branch_id}. Resolve it before migrating."
                );
            }
        }
    }

    private function cloneMainSettingsIntoExistingBranches(int $mainBranchId): void
    {
        $branchIds = DB::table('branches')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($this->settingTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            $main = DB::table($table)->where('branch_id', $mainBranchId)->first();
            if (!$main) {
                continue;
            }

            $base = (array) $main;
            unset($base['id']);

            foreach ($branchIds as $branchId) {
                if ($branchId === $mainBranchId || DB::table($table)->where('branch_id', $branchId)->exists()) {
                    continue;
                }

                $copy = $base;
                $copy['branch_id'] = $branchId;
                if (array_key_exists('created_at', $copy)) {
                    $copy['created_at'] = now();
                }
                if (array_key_exists('updated_at', $copy)) {
                    $copy['updated_at'] = now();
                }
                DB::table($table)->insert($copy);
            }
        }
    }

    private function addSettingUniqueness(): void
    {
        foreach ($this->settingTables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            $indexName = $this->settingUniqueName($tableName);
            if ($this->indexExists($tableName, $indexName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->unique('branch_id', $indexName);
            });
        }
    }

    private function extendInvoiceNumberingSettings(): void
    {
        if (!Schema::hasTable('invoice_settings')) {
            return;
        }

        Schema::table('invoice_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_settings', 'order_prefix')) {
                $table->string('order_prefix', 30)->default('')->after('branch_id');
            }
            if (!Schema::hasColumn('invoice_settings', 'order_starting_number')) {
                $table->unsignedBigInteger('order_starting_number')->default(1001)->after('order_prefix');
            }
            if (!Schema::hasColumn('invoice_settings', 'order_padding')) {
                $table->unsignedTinyInteger('order_padding')->default(0)->after('order_starting_number');
            }
            if (!Schema::hasColumn('invoice_settings', 'invoice_padding')) {
                $table->unsignedTinyInteger('invoice_padding')->default(0)->after('starting_number');
            }
            if (!Schema::hasColumn('invoice_settings', 'kot_prefix')) {
                $table->string('kot_prefix', 30)->default('KOT-')->after('invoice_padding');
            }
            if (!Schema::hasColumn('invoice_settings', 'kot_starting_number')) {
                $table->unsignedBigInteger('kot_starting_number')->default(1)->after('kot_prefix');
            }
            if (!Schema::hasColumn('invoice_settings', 'kot_padding')) {
                $table->unsignedTinyInteger('kot_padding')->default(0)->after('kot_starting_number');
            }
        });
    }

    private function extendOrdersForInvoiceAndQrReference(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'invoice_number')) {
                $table->string('invoice_number', 100)->nullable()->after('order_number');
            }
            if (!Schema::hasColumn('orders', 'qr_reference')) {
                $table->string('qr_reference', 100)->nullable()->after('invoice_number');
            }
        });

        if (Schema::hasColumn('orders', 'qr_reference')) {
            DB::table('orders')
                ->whereNull('qr_reference')
                ->where('order_number', 'like', 'QR-%')
                ->update(['qr_reference' => DB::raw('order_number')]);
        }
    }

    private function convertOrderAndKotUniqueness(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'branch_id')) {
            $duplicate = DB::table('orders')
                ->select('branch_id', 'order_number', DB::raw('COUNT(*) as aggregate'))
                ->whereNotNull('branch_id')
                ->whereNotNull('order_number')
                ->groupBy('branch_id', 'order_number')
                ->having('aggregate', '>', 1)
                ->first();

            if ($duplicate) {
                throw new RuntimeException('Duplicate order number exists inside a branch: ' . $duplicate->order_number);
            }

            // This migration may be re-run after a MySQL DDL failure. MySQL commits many
            // ALTER TABLE statements immediately, so every index operation must be resumable.
            if ($this->indexExists('orders', 'orders_order_number_unique')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->dropUnique('orders_order_number_unique');
                });
            }

            if (!$this->indexExists('orders', 'orders_branch_order_number_unique')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->unique(['branch_id', 'order_number'], 'orders_branch_order_number_unique');
                });
            }
            if (!$this->indexExists('orders', 'orders_branch_invoice_number_unique')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->unique(['branch_id', 'invoice_number'], 'orders_branch_invoice_number_unique');
                });
            }
            if (!$this->indexExists('orders', 'orders_qr_reference_unique')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->unique('qr_reference', 'orders_qr_reference_unique');
                });
            }
        }

        if (Schema::hasTable('order_kots') && Schema::hasColumn('order_kots', 'branch_id')) {
            // Legacy installations never enforced KOT-number uniqueness. Historical duplicate
            // KOT numbers can therefore exist legitimately after concurrency/restart/imports.
            // Do NOT rename/delete historical KOTs just to make this migration pass.
            // New KOT numbers are allocated by branch_number_sequences under DB row locks,
            // so the new generator cannot re-use an existing numeric tail once seeded.
            // Keep a lookup index instead of forcing a retroactive unique constraint.
            if (!$this->indexExists('order_kots', 'order_kots_branch_kot_number_unique')
                && !$this->indexExists('order_kots', 'order_kots_branch_kot_number_idx')) {
                Schema::table('order_kots', function (Blueprint $table) {
                    $table->index(['branch_id', 'kot_number'], 'order_kots_branch_kot_number_idx');
                });
            }
        }
    }

    private function createBranchNumberSequences(): void
    {
        if (Schema::hasTable('branch_number_sequences')) {
            return;
        }

        Schema::create('branch_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('sequence_type', 20); // order / invoice / kot
            $table->string('prefix', 30)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'sequence_type'], 'branch_number_sequences_branch_type_unique');
        });
    }

    private function seedBranchSequences(): void
    {
        if (!Schema::hasTable('branch_number_sequences')) {
            return;
        }

        $mainBranchId = (int) (DB::table('branches')->where('is_main', 1)->value('id') ?: DB::table('branches')->min('id'));
        $mainSettings = Schema::hasTable('invoice_settings')
            ? DB::table('invoice_settings')->where('branch_id', $mainBranchId)->first()
            : null;

        foreach (DB::table('branches')->orderBy('id')->get() as $branch) {
            $settings = Schema::hasTable('invoice_settings')
                ? (DB::table('invoice_settings')->where('branch_id', $branch->id)->first() ?: $mainSettings)
                : null;

            $orderStart = max(1, (int) ($settings->order_starting_number ?? $settings->starting_number ?? 1001));
            $invoiceStart = max(1, (int) ($settings->starting_number ?? 1001));
            $kotStart = max(1, (int) ($settings->kot_starting_number ?? 1));

            $historicalOrderMax = $this->maxNumericTail('orders', 'order_number', (int) $branch->id, true);
            $historicalKotMax = $this->maxNumericTail('order_kots', 'kot_number', (int) $branch->id, false);
            $historicalInvoiceMax = $this->maxNumericTail('orders', 'invoice_number', (int) $branch->id, false);

            // Existing installations displayed invoice prefix + order_number. If invoice_number is not yet stored,
            // continue the new invoice sequence after the highest historical official order number.
            $historicalInvoiceMax = max($historicalInvoiceMax, $historicalOrderMax);

            $rows = [
                [
                    'sequence_type' => 'order',
                    'prefix' => (string) ($settings->order_prefix ?? ''),
                    'next_number' => max($orderStart, $historicalOrderMax + 1),
                    'padding' => max(0, (int) ($settings->order_padding ?? 0)),
                ],
                [
                    'sequence_type' => 'invoice',
                    'prefix' => (string) ($settings->prefix ?? 'INV-'),
                    'next_number' => max($invoiceStart, $historicalInvoiceMax + 1),
                    'padding' => max(0, (int) ($settings->invoice_padding ?? 0)),
                ],
                [
                    'sequence_type' => 'kot',
                    'prefix' => (string) ($settings->kot_prefix ?? 'KOT-'),
                    'next_number' => max($kotStart, $historicalKotMax + 1),
                    'padding' => max(0, (int) ($settings->kot_padding ?? 0)),
                ],
            ];

            foreach ($rows as $row) {
                DB::table('branch_number_sequences')->updateOrInsert(
                    ['branch_id' => $branch->id, 'sequence_type' => $row['sequence_type']],
                    array_merge($row, ['updated_at' => now(), 'created_at' => now()])
                );
            }
        }
    }

    private function createOfflinePosDevices(): void
    {
        if (Schema::hasTable('offline_pos_devices')) {
            return;
        }

        Schema::create('offline_pos_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'is_active'], 'offline_pos_devices_branch_active_idx');
        });
    }

    private function maxNumericTail(string $table, string $column, int $branchId, bool $skipQr): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || !Schema::hasColumn($table, 'branch_id')) {
            return 0;
        }

        $max = 0;
        $query = DB::table($table)
            ->select(['id', $column])
            ->where('branch_id', $branchId)
            ->whereNotNull($column);

        if ($skipQr) {
            $query->where($column, 'not like', 'QR-%');
        }

        $query->orderBy('id')->chunkById(1000, function ($rows) use (&$max, $column) {
            foreach ($rows as $row) {
                $value = (string) $row->{$column};
                if (preg_match('/(\d+)$/', $value, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }
        });

        return $max;
    }


    private function indexExists(string $table, string $indexName): bool
    {
        // MySQL/MariaDB compatible and safe for partially-applied DDL migrations.
        return DB::table('information_schema.statistics')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    private function settingUniqueName(string $table): string
    {
        return $table . '_branch_unique';
    }

};
