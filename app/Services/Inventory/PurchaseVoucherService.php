<?php

namespace App\Services\Inventory;

use App\Models\InventoryPurchaseApprover;
use App\Models\InventoryPurchaseApprovalSetting;
use App\Models\Purchase;
use App\Models\PurchaseVoucher;
use App\Models\PurchaseVoucherApproval;
use App\Models\PurchaseVoucherRevision;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseVoucherService
{
    public function __construct(
        private PurchaseService $purchases,
        private DecimalQuantity $decimal
    ) {
    }

    public function saveDraft(
        Vendor $vendor,
        array $header,
        array $itemRows,
        ?PurchaseVoucher $voucher = null,
        ?int $userId = null
    ): PurchaseVoucher {
        if (!$vendor->is_active) {
            throw ValidationException::withMessages(['vendor_id' => 'Select an active vendor.']);
        }

        $items = $this->purchases->normalizeItems($itemRows);
        [$subtotal, $discount, $tax, $total] = $this->totals($header, $items);

        return DB::transaction(function () use ($vendor, $header, $items, $subtotal, $discount, $tax, $total, $voucher, $userId) {
            if ($voucher) {
                $voucher = PurchaseVoucher::query()->whereKey($voucher->id)->lockForUpdate()->firstOrFail();
                if (!$voucher->isEditable()) {
                    throw ValidationException::withMessages(['voucher' => 'Only a Draft or Rejected voucher can be edited.']);
                }
                if ($voucher->status === PurchaseVoucher::STATUS_REJECTED) {
                    $this->archiveCurrentRevision($voucher, $userId, 'Rejected voucher revised');
                    $voucher->revision_no = (int) $voucher->revision_no + 1;
                }
            } else {
                $voucher = new PurchaseVoucher();
                $voucher->voucher_no = $this->nextVoucherNumber();
                $voucher->created_by = $userId;
                $voucher->revision_no = 1;
            }

            $voucher->fill([
                'vendor_id' => $vendor->id,
                'voucher_date' => $header['voucher_date'],
                'status' => PurchaseVoucher::STATUS_DRAFT,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'notes' => $header['notes'] ?? null,
                'reapproval_reason' => null,
                'submitted_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
            ]);
            $voucher->save();

            $voucher->items()->delete();
            foreach ($items as $item) {
                $voucher->items()->create($item);
            }

            return $voucher->fresh(['vendor', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion']);
        }, 5);
    }

    public function submit(PurchaseVoucher|int $voucher, ?int $userId = null): PurchaseVoucher
    {
        $voucherId = $voucher instanceof PurchaseVoucher ? (int) $voucher->id : (int) $voucher;

        return DB::transaction(function () use ($voucherId, $userId) {
            $voucher = PurchaseVoucher::query()->with('items')->whereKey($voucherId)->lockForUpdate()->firstOrFail();
            if (!$voucher->canSubmit()) {
                throw ValidationException::withMessages(['voucher' => 'Only a Draft voucher can be submitted for approval. Rejected vouchers must be revised and saved first so the rejected approval history is preserved.']);
            }
            if ($voucher->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one ingredient before submitting the voucher.']);
            }

            $this->createApprovalRound($voucher, false);
            return $voucher->fresh(['vendor', 'items.ingredient.baseUnit', 'currentApprovals.approver']);
        }, 5);
    }

    public function approve(PurchaseVoucher|int $voucher, int $userId, ?string $comment = null): PurchaseVoucher
    {
        return $this->act($voucher, $userId, PurchaseVoucherApproval::STATUS_APPROVED, $comment);
    }

    public function reject(PurchaseVoucher|int $voucher, int $userId, ?string $comment = null): PurchaseVoucher
    {
        if (trim((string) $comment) === '') {
            throw ValidationException::withMessages(['comment' => 'A rejection reason is required.']);
        }
        return $this->act($voucher, $userId, PurchaseVoucherApproval::STATUS_REJECTED, $comment);
    }

    public function sendToVendor(PurchaseVoucher|int $voucher, int $userId): PurchaseVoucher
    {
        $voucherId = $voucher instanceof PurchaseVoucher ? (int) $voucher->id : (int) $voucher;
        return DB::transaction(function () use ($voucherId, $userId) {
            $voucher = PurchaseVoucher::query()->whereKey($voucherId)->lockForUpdate()->firstOrFail();
            if (!$voucher->canSendToVendor()) {
                throw ValidationException::withMessages(['voucher' => 'Only a fully approved voucher can be sent to the vendor.']);
            }
            $voucher->forceFill([
                'status' => PurchaseVoucher::STATUS_SENT_TO_VENDOR,
                'sent_to_vendor_at' => now(),
                'sent_to_vendor_by' => $userId,
            ])->save();
            return $voucher->fresh(['vendor', 'items.ingredient.baseUnit', 'currentApprovals.approver', 'sentToVendorBy']);
        }, 5);
    }

    public function assertReadyForSupply(PurchaseVoucher $voucher): void
    {
        if (!$voucher->canReceiveSupply()) {
            throw ValidationException::withMessages([
                'voucher' => 'This voucher is not ready for supply receiving. It must be fully approved and sent to the vendor first.',
            ]);
        }
    }

    public function overrunReasons(PurchaseVoucher $voucher, Purchase $purchase): array
    {
        $voucher->loadMissing('items');
        $purchase->loadMissing('items');
        $reasons = [];
        $approvedByIngredient = $voucher->items->keyBy(fn ($item) => (int) $item->ingredient_id);

        foreach ($purchase->items as $actual) {
            $approved = $approvedByIngredient->get((int) $actual->ingredient_id);
            if (!$approved) {
                $reasons[] = 'A supplied ingredient was not included in the approved voucher.';
                continue;
            }
            if ($this->decimal->compare((string) $actual->base_quantity, (string) $approved->base_quantity) > 0) {
                $reasons[] = ($actual->ingredient?->name ?: 'Ingredient') . ' quantity exceeds the approved quantity.';
            }
            if ($this->decimal->compare((string) $actual->line_total, (string) $approved->line_total, 4) > 0) {
                $reasons[] = ($actual->ingredient?->name ?: 'Ingredient') . ' value exceeds the approved value.';
            }
        }

        if ($this->decimal->compare((string) $purchase->total, (string) $voucher->total, 4) > 0) {
            $reasons[] = 'The actual purchase total exceeds the approved voucher total.';
        }

        return array_values(array_unique($reasons));
    }

    public function assertPurchaseWithinApproval(PurchaseVoucher $voucher, Purchase $purchase): void
    {
        $this->assertReadyForSupply($voucher);
        $reasons = $this->overrunReasons($voucher, $purchase);
        if ($reasons !== []) {
            throw ValidationException::withMessages([
                'purchase' => 'Re-approval is required: ' . implode(' ', $reasons),
            ]);
        }
    }

    public function requestReapprovalFromPurchase(PurchaseVoucher $voucher, Purchase $purchase, ?int $userId, array $reasons = []): PurchaseVoucher
    {
        return DB::transaction(function () use ($voucher, $purchase, $userId, $reasons) {
            $voucher = PurchaseVoucher::query()
                ->with(['items', 'currentApprovals.approver'])
                ->whereKey($voucher->id)
                ->lockForUpdate()
                ->firstOrFail();
            $purchase = Purchase::query()->with(['items.ingredient'])->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (!$voucher->sent_to_vendor_at) {
                throw ValidationException::withMessages(['voucher' => 'Re-approval can only be triggered for a voucher already sent to the vendor.']);
            }

            $reasons = $reasons ?: $this->overrunReasons($voucher, $purchase);
            if ($reasons === []) {
                return $voucher;
            }

            $this->archiveCurrentRevision($voucher, $userId, 'Supply overrun before receiving');
            $nextRevision = (int) $voucher->revision_no + 1;

            $voucher->forceFill([
                'vendor_id' => $purchase->vendor_id,
                'voucher_date' => $purchase->purchase_date,
                'revision_no' => $nextRevision,
                'status' => PurchaseVoucher::STATUS_DRAFT,
                'subtotal' => $purchase->subtotal,
                'discount' => $purchase->discount,
                'tax' => $purchase->tax,
                'total' => $purchase->total,
                'reapproval_reason' => implode(' ', $reasons),
                'submitted_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
            ])->save();

            $voucher->items()->delete();
            foreach ($purchase->items as $item) {
                $voucher->items()->create([
                    'ingredient_id' => $item->ingredient_id,
                    'quantity' => $item->quantity,
                    'unit_id' => $item->unit_id,
                    'package_conversion_id' => $item->package_conversion_id,
                    'conversion_factor_snapshot' => $item->conversion_factor_snapshot,
                    'base_quantity' => $item->base_quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]);
            }

            // The supplier has already delivered against the previously sent voucher, so keep
            // sent_to_vendor_at as the audit marker and start a fresh approval round immediately.
            $this->createApprovalRound($voucher->fresh('items'), true);

            return $voucher->fresh(['vendor', 'items.ingredient.baseUnit', 'currentApprovals.approver', 'revisions']);
        }, 5);
    }

    public function markCompleted(PurchaseVoucher $voucher, Purchase $purchase): void
    {
        PurchaseVoucher::query()->whereKey($voucher->id)->update([
            'status' => PurchaseVoucher::STATUS_COMPLETED,
            'converted_purchase_id' => $purchase->id,
            'updated_at' => now(),
        ]);
    }

    public function canUserAct(PurchaseVoucher $voucher, int $userId): bool
    {
        if ($voucher->status !== PurchaseVoucher::STATUS_PENDING_APPROVAL) {
            return false;
        }
        $approval = PurchaseVoucherApproval::query()
            ->where('purchase_voucher_id', $voucher->id)
            ->where('revision_no', $voucher->revision_no)
            ->where('approver_user_id', $userId)
            ->where('status', PurchaseVoucherApproval::STATUS_PENDING)
            ->first();
        if (!$approval) {
            return false;
        }
        return !PurchaseVoucherApproval::query()
            ->where('purchase_voucher_id', $voucher->id)
            ->where('revision_no', $voucher->revision_no)
            ->where('approval_order', '<', $approval->approval_order)
            ->where('status', '!=', PurchaseVoucherApproval::STATUS_APPROVED)
            ->exists();
    }

    private function act(PurchaseVoucher|int $voucher, int $userId, string $decision, ?string $comment): PurchaseVoucher
    {
        $voucherId = $voucher instanceof PurchaseVoucher ? (int) $voucher->id : (int) $voucher;
        return DB::transaction(function () use ($voucherId, $userId, $decision, $comment) {
            $voucher = PurchaseVoucher::query()->whereKey($voucherId)->lockForUpdate()->firstOrFail();
            if ($voucher->status !== PurchaseVoucher::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages(['voucher' => 'This voucher is not waiting for approval.']);
            }

            $approval = PurchaseVoucherApproval::query()
                ->where('purchase_voucher_id', $voucher->id)
                ->where('revision_no', $voucher->revision_no)
                ->where('approver_user_id', $userId)
                ->lockForUpdate()
                ->first();
            if (!$approval || $approval->status !== PurchaseVoucherApproval::STATUS_PENDING) {
                throw ValidationException::withMessages(['approval' => 'You do not have a pending approval action for this voucher.']);
            }

            $previousPending = PurchaseVoucherApproval::query()
                ->where('purchase_voucher_id', $voucher->id)
                ->where('revision_no', $voucher->revision_no)
                ->where('approval_order', '<', $approval->approval_order)
                ->where('status', '!=', PurchaseVoucherApproval::STATUS_APPROVED)
                ->exists();
            if ($previousPending) {
                throw ValidationException::withMessages(['approval' => 'Earlier approval levels must approve this voucher first.']);
            }

            $approval->forceFill([
                'status' => $decision,
                'comment' => trim((string) $comment) ?: null,
                'acted_at' => now(),
            ])->save();

            if ($decision === PurchaseVoucherApproval::STATUS_REJECTED) {
                $voucher->forceFill([
                    'status' => PurchaseVoucher::STATUS_REJECTED,
                    'rejected_at' => now(),
                    'approved_at' => null,
                ])->save();
            } else {
                $pending = PurchaseVoucherApproval::query()
                    ->where('purchase_voucher_id', $voucher->id)
                    ->where('revision_no', $voucher->revision_no)
                    ->where('status', PurchaseVoucherApproval::STATUS_PENDING)
                    ->exists();
                if (!$pending) {
                    $voucher->forceFill([
                        'status' => PurchaseVoucher::STATUS_APPROVED,
                        'approved_at' => now(),
                        'rejected_at' => null,
                    ])->save();
                }
            }

            return $voucher->fresh(['vendor', 'items.ingredient.baseUnit', 'currentApprovals.approver']);
        }, 5);
    }

    private function createApprovalRound(PurchaseVoucher $voucher, bool $isReapproval): void
    {
        $setting = InventoryPurchaseApprovalSetting::query()->first();
        $approvalEnabled = $setting?->is_enabled ?? true;
        $approvers = InventoryPurchaseApprover::query()->with('user')->active()->get();
        $minimum = max(1, (int) ($setting?->minimum_approvers ?? 1));

        if (!$approvalEnabled) {
            $voucher->forceFill([
                'status' => PurchaseVoucher::STATUS_APPROVED,
                'submitted_at' => now(),
                'approved_at' => now(),
                'rejected_at' => null,
            ])->save();
            return;
        }

        if ($approvers->count() < $minimum || $approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approval' => "Purchase approval setup requires at least {$minimum} active approver(s). Configure them in Settings > Purchase Approval.",
            ]);
        }

        PurchaseVoucherApproval::query()
            ->where('purchase_voucher_id', $voucher->id)
            ->where('revision_no', $voucher->revision_no)
            ->delete();

        foreach ($approvers as $approver) {
            PurchaseVoucherApproval::query()->create([
                'purchase_voucher_id' => $voucher->id,
                'revision_no' => $voucher->revision_no,
                'approver_user_id' => $approver->user_id,
                'approver_name' => $approver->user?->name,
                'approver_email' => $approver->user?->email,
                'approval_order' => $approver->approval_order,
                'status' => PurchaseVoucherApproval::STATUS_PENDING,
            ]);
        }

        $voucher->forceFill([
            'status' => PurchaseVoucher::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
            'approved_at' => null,
            'rejected_at' => null,
        ])->save();
    }

    private function archiveCurrentRevision(PurchaseVoucher $voucher, ?int $userId, ?string $reason): void
    {
        $voucher->loadMissing(['items', 'currentApprovals']);
        PurchaseVoucherRevision::query()->updateOrCreate(
            ['purchase_voucher_id' => $voucher->id, 'revision_no' => $voucher->revision_no],
            [
                'snapshot' => [
                    'voucher' => [
                        'voucher_no' => $voucher->voucher_no,
                        'vendor_id' => $voucher->vendor_id,
                        'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
                        'status' => $voucher->status,
                        'revision_no' => $voucher->revision_no,
                        'subtotal' => (string) $voucher->subtotal,
                        'discount' => (string) $voucher->discount,
                        'tax' => (string) $voucher->tax,
                        'total' => (string) $voucher->total,
                        'notes' => $voucher->notes,
                        'submitted_at' => optional($voucher->submitted_at)?->toDateTimeString(),
                        'approved_at' => optional($voucher->approved_at)?->toDateTimeString(),
                        'sent_to_vendor_at' => optional($voucher->sent_to_vendor_at)?->toDateTimeString(),
                    ],
                    'items' => $voucher->items->map(fn ($item) => $item->only([
                        'ingredient_id', 'quantity', 'unit_id', 'package_conversion_id',
                        'conversion_factor_snapshot', 'base_quantity', 'unit_price', 'line_total',
                    ]))->values()->all(),
                    'approvals' => $voucher->currentApprovals->where('revision_no', $voucher->revision_no)->map(fn ($approval) => $approval->only([
                        'approver_user_id', 'approver_name', 'approver_email', 'approval_order', 'status', 'comment', 'acted_at',
                    ]))->values()->all(),
                ],
                'reason' => $reason,
                'archived_by' => $userId,
            ]
        );
    }

    private function totals(array $header, array $items): array
    {
        $subtotal = '0.0000';
        foreach ($items as $item) {
            $subtotal = $this->decimal->add($subtotal, $item['line_total'], 4);
        }
        $discount = $this->decimal->normalize((string) ($header['discount'] ?? '0'), 4);
        $tax = $this->decimal->normalize((string) ($header['tax'] ?? '0'), 4);
        if ($this->decimal->compare($discount, '0', 4) < 0 || $this->decimal->compare($tax, '0', 4) < 0) {
            throw ValidationException::withMessages(['discount' => 'Discount and tax cannot be negative.']);
        }
        if ($this->decimal->compare($discount, $subtotal, 4) > 0) {
            throw ValidationException::withMessages(['discount' => 'Voucher discount cannot exceed the subtotal.']);
        }
        $total = $this->decimal->add($this->decimal->subtract($subtotal, $discount, 4), $tax, 4);
        return [$subtotal, $discount, $tax, $total];
    }

    private function nextVoucherNumber(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = 'PV-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(5));
            if (!PurchaseVoucher::query()->where('voucher_no', $number)->exists()) {
                return $number;
            }
        }
        throw ValidationException::withMessages(['voucher_no' => 'Could not allocate a unique voucher number. Please try again.']);
    }
}
