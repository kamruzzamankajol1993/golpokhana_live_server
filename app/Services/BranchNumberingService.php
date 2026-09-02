<?php

namespace App\Services;

use App\Models\BranchNumberSequence;
use App\Models\InvoiceSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BranchNumberingService
{
    public const TYPE_ORDER = 'order';
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_KOT = 'kot';

    public function __construct(private BranchSettingResolver $settings)
    {
    }

    public function nextOrderNumber(int $branchId): string
    {
        return $this->next($branchId, self::TYPE_ORDER);
    }

    public function nextInvoiceNumber(int $branchId): string
    {
        return $this->next($branchId, self::TYPE_INVOICE);
    }

    public function nextKotNumber(int $branchId): string
    {
        return $this->next($branchId, self::TYPE_KOT);
    }

    public function initializeBranch(int $branchId): void
    {
        if (!Schema::hasTable('branch_number_sequences')) {
            return;
        }

        foreach ([self::TYPE_ORDER, self::TYPE_INVOICE, self::TYPE_KOT] as $type) {
            $this->ensureSequence($branchId, $type);
        }
    }

    /**
     * Sync formatting/start preferences without moving a sequence backwards.
     * Called after Invoice/Numbering settings are updated.
     */
    public function syncBranchConfiguration(int $branchId): void
    {
        if (!Schema::hasTable('branch_number_sequences')) {
            return;
        }

        DB::transaction(function () use ($branchId) {
            foreach ([self::TYPE_ORDER, self::TYPE_INVOICE, self::TYPE_KOT] as $type) {
                $defaults = $this->defaults($branchId, $type);
                $sequence = BranchNumberSequence::query()
                    ->where('branch_id', $branchId)
                    ->where('sequence_type', $type)
                    ->lockForUpdate()
                    ->first();

                if (!$sequence) {
                    $this->createSequence($branchId, $type, $defaults);
                    continue;
                }

                $sequence->prefix = $defaults['prefix'];
                $sequence->padding = $defaults['padding'];
                $sequence->next_number = max((int) $sequence->next_number, (int) $defaults['starting_number']);
                $sequence->save();
            }
        });
    }

    private function next(int $branchId, string $type): string
    {
        if (!Schema::hasTable('branch_number_sequences')) {
            // Never silently fall back to a non-locking number generator. Once the
            // Phase-5 code is deployed, the schema migration must be applied before
            // traffic is allowed to create new Order/Invoice/KOT numbers; otherwise
            // two concurrent cashiers/devices could receive the same number.
            throw new RuntimeException(
                'Branch numbering schema is not installed. Run the Phase 5 migration before creating orders.'
            );
        }

        return DB::transaction(function () use ($branchId, $type) {
            $sequence = BranchNumberSequence::query()
                ->where('branch_id', $branchId)
                ->where('sequence_type', $type)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $defaults = $this->defaults($branchId, $type);
                // insertOrIgnore closes the first-request race between two cashiers/devices.
                // The unique (branch_id, sequence_type) key guarantees exactly one row.
                DB::table('branch_number_sequences')->insertOrIgnore([
                    'branch_id' => $branchId,
                    'sequence_type' => $type,
                    'prefix' => $defaults['prefix'],
                    'next_number' => $defaults['starting_number'],
                    'padding' => $defaults['padding'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = BranchNumberSequence::query()
                    ->where('branch_id', $branchId)
                    ->where('sequence_type', $type)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $number = (int) $sequence->next_number;
            if ($number < 1) {
                throw new RuntimeException("Invalid {$type} sequence for branch {$branchId}.");
            }

            $formatted = $this->format((string) $sequence->prefix, $number, (int) $sequence->padding);
            $sequence->next_number = $number + 1;
            $sequence->save();

            return $formatted;
        }, 5);
    }

    private function ensureSequence(int $branchId, string $type): BranchNumberSequence
    {
        $existing = BranchNumberSequence::query()
            ->where('branch_id', $branchId)
            ->where('sequence_type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        $defaults = $this->defaults($branchId, $type);
        DB::table('branch_number_sequences')->insertOrIgnore([
            'branch_id' => $branchId,
            'sequence_type' => $type,
            'prefix' => $defaults['prefix'],
            'next_number' => $defaults['starting_number'],
            'padding' => $defaults['padding'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return BranchNumberSequence::query()
            ->where('branch_id', $branchId)
            ->where('sequence_type', $type)
            ->firstOrFail();
    }

    private function createSequence(int $branchId, string $type, array $defaults): BranchNumberSequence
    {
        return BranchNumberSequence::query()->create([
            'branch_id' => $branchId,
            'sequence_type' => $type,
            'prefix' => $defaults['prefix'],
            'next_number' => $defaults['starting_number'],
            'padding' => $defaults['padding'],
        ]);
    }

    private function defaults(int $branchId, string $type): array
    {
        /** @var InvoiceSetting|null $setting */
        $setting = $this->settings->get(InvoiceSetting::class, $branchId, true);

        return match ($type) {
            self::TYPE_ORDER => [
                'prefix' => (string) ($setting?->order_prefix ?? ''),
                'starting_number' => max(1, (int) ($setting?->order_starting_number ?? $setting?->starting_number ?? 1001)),
                'padding' => max(0, (int) ($setting?->order_padding ?? 0)),
            ],
            self::TYPE_INVOICE => [
                'prefix' => (string) ($setting?->prefix ?? 'INV-'),
                'starting_number' => max(1, (int) ($setting?->starting_number ?? 1001)),
                'padding' => max(0, (int) ($setting?->invoice_padding ?? 0)),
            ],
            self::TYPE_KOT => [
                'prefix' => (string) ($setting?->kot_prefix ?? 'KOT-'),
                'starting_number' => max(1, (int) ($setting?->kot_starting_number ?? 1)),
                'padding' => max(0, (int) ($setting?->kot_padding ?? 0)),
            ],
            default => throw new RuntimeException("Unknown branch sequence type: {$type}"),
        };
    }

    private function format(string $prefix, int $number, int $padding): string
    {
        $numberText = $padding > 0 ? str_pad((string) $number, $padding, '0', STR_PAD_LEFT) : (string) $number;
        return $prefix . $numberText;
    }
}
