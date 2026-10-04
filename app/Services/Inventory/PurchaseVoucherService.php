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

    public function submit(
        PurchaseVoucher|int $voucher,
        array $approverIds,
        int $userId,
        string $note
    ): PurchaseVoucher {
        $voucherId = $voucher instanceof PurchaseVoucher ? (int) $voucher->id : (int) $voucher;

        return DB::transaction(function () use ($voucherId, $approverIds, $userId, $note) {
            $voucher = PurchaseVoucher::query()->with('items')->whereKey($voucherId)->lockForUpdate()->firstOrFail();
            if (!$voucher->canSubmit()) {
                throw ValidationException::withMessages(['voucher' => 'Only a Draft voucher can be submitted for approval.']);
            }
            if ($voucher->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one ingredient before submitting the voucher.']);
            }

            return $this->dispatchApprovalsLocked($voucher, $approverIds, $userId, $note, true);
        }, 5);
    }

    public function dispatchApprovals(
        PurchaseVoucher|int $voucher,
        array $approverIds,
        int $userId,
        string $note
    ): PurchaseVoucher {
        $voucherId = $voucher instanceof PurchaseVoucher ? (int) $voucher->id : (int) $voucher;

        return DB::transaction(function () use ($voucherId, $approverIds, $userId, $note) {
            $voucher = PurchaseVoucher::query()->with('items')->whereKey($voucherId)->lockForUpdate()->firstOrFail();
            if (!$voucher->canDispatchApproval()) {
                throw ValidationException::withMessages(['voucher' => 'This voucher can no longer be sent for approval.']);
            }

            return $this->dispatchApprovalsLocked($voucher, $approverIds, $userId, $note, false);
        }, 5);
    }

    public function approve(PurchaseVoucher|int $voucher, int $userId, ?string $comment = null): PurchaseVoucher
    {
        if (trim((string) $comment) === '') {
            throw ValidationException::withMessages(['comment' => 'An approval note is required.']);
        }
        return $this->act($voucher, $userId, PurchaseVoucherApproval::STATUS_APPROVED, $comment);
    }

    public function reject(PurchaseVoucher|int $voucher, int $userId, ?string $comment = null): PurchaseVoucher
    {
        if (trim((string) $comment) === '') {
            throw ValidationException::withMessages(['comment' => 'A rejection note is required.']);
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

            // Supplier overrun starts a new revision, but Step 4 no longer sends the voucher
            // automatically to every configured approver. The manager selects the next officer(s)
            // from Approval History / Exchange and adds a note for that dispatch.
            $voucher->forceFill([
                'status' => PurchaseVoucher::STATUS_PENDING_APPROVAL,
                'submitted_at' => now(),
                'approved_at' => null,
                'rejected_at' => null,
            ])->save();

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

        return PurchaseVoucherApproval::query()
            ->where('purchase_voucher_id', $voucher->id)
            ->where('revision_no', $voucher->revision_no)
            ->where('approver_user_id', $userId)
            ->where('status', PurchaseVoucherApproval::STATUS_PENDING)
            ->exists();
    }

    public function approvalProgress(PurchaseVoucher $voucher): array
    {
        $setting = InventoryPurchaseApprovalSetting::query()->first();
        $minimum = max(1, (int) ($setting?->minimum_approvers ?? 1));
        $latest = PurchaseVoucherApproval::query()
            ->where('purchase_voucher_id', $voucher->id)
            ->where('revision_no', $voucher->revision_no)
            ->whereNotNull('approver_user_id')
            ->orderBy('id')
            ->get()
            ->groupBy('approver_user_id')
            ->map(fn ($rows) => $rows->last());

        return [
            'approved' => $latest->where('status', PurchaseVoucherApproval::STATUS_APPROVED)->count(),
            'minimum' => $minimum,
            'latest' => $latest,
        ];
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
                ->where('status', PurchaseVoucherApproval::STATUS_PENDING)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if (!$approval) {
                throw ValidationException::withMessages(['approval' => 'You do not have a pending approval action for this voucher.']);
            }

            $approval->forceFill([
                'status' => $decision,
                'comment' => trim((string) $comment),
                'acted_at' => now(),
            ])->save();

            $this->refreshApprovalStatus($voucher);

            return $voucher->fresh(['vendor', 'items.ingredient.baseUnit', 'currentApprovals.approver', 'currentApprovals.assigner']);
        }, 5);
    }

    private function dispatchApprovalsLocked(
        PurchaseVoucher $voucher,
        array $approverIds,
        int $userId,
        string $note,
        bool $initialSubmit
    ): PurchaseVoucher {
        $setting = InventoryPurchaseApprovalSetting::query()->first();
        $approvalEnabled = $setting?->is_enabled ?? true;
        $minimum = max(1, (int) ($setting?->minimum_approvers ?? 1));
        $configured = InventoryPurchaseApprover::query()->with('user')->active()->get();

        if (!$approvalEnabled) {
            $voucher->forceFill([
                'status' => PurchaseVoucher::STATUS_APPROVED,
                'submitted_at' => $voucher->submitted_at ?: now(),
                'approved_at' => now(),
                'rejected_at' => null,
            ])->save();
            return $voucher->fresh(['vendor', 'items.ingredient.baseUnit', 'currentApprovals.approver']);
        }

        if ($configured->count() < $minimum || $configured->isEmpty()) {
            throw ValidationException::withMessages([
                'approval' => "Purchase approval setup requires at least {$minimum} active approver(s). Configure them in Settings > Purchase Approval.",
            ]);
        }

        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['approval_note' => 'A note is required whenever the voucher is sent to an approver.']);
        }

        $approverIds = array_values(array_unique(array_filter(array_map('intval', $approverIds))));
        if ($approverIds === []) {
            throw ValidationException::withMessages(['approver_ids' => 'Select at least one approval user.']);
        }

        $configuredByUser = $configured->keyBy(fn ($row) => (int) $row->user_id);
        foreach ($approverIds as $approverId) {
            if (!$configuredByUser->has($approverId)) {
                throw ValidationException::withMessages(['approver_ids' => 'Only users assigned in Settings > Purchase Approval can receive a voucher.']);
            }
            $hasPending = PurchaseVoucherApproval::query()
                ->where('purchase_voucher_id', $voucher->id)
                ->where('revision_no', $voucher->revision_no)
                ->where('approver_user_id', $approverId)
                ->where('status', PurchaseVoucherApproval::STATUS_PENDING)
                ->exists();
            if ($hasPending) {
                $name = $configuredByUser->get($approverId)?->user?->name ?: 'Selected user';
                throw ValidationException::withMessages(['approver_ids' => $name . ' already has a pending approval request for this revision.']);
            }
        }

        $batchNo = (int) PurchaseVoucherApproval::query()
            ->where('purchase_voucher_id', $voucher->id)
            ->where('revision_no', $voucher->revision_no)
            ->max('batch_no') + 1;
        $nextOrder = (int) PurchaseVoucherApproval::query()
            ->where('purchase_voucher_id', $voucher->id)
            ->where('revision_no', $voucher->revision_no)
            ->max('approval_order') + 1;

        foreach ($approverIds as $offset => $approverId) {
            $configuredApprover = $configuredByUser->get($approverId);
            PurchaseVoucherApproval::query()->create([
                'purchase_voucher_id' => $voucher->id,
                'revision_no' => $voucher->revision_no,
                'approver_user_id' => $approverId,
                'approver_name' => $configuredApprover?->user?->name,
                'approver_email' => $configuredApprover?->user?->email,
                'approval_order' => $nextOrder + $offset,
                'batch_no' => max(1, $batchNo),
                'assigned_by' => $userId,
                'assigned_at' => now(),
                'dispatch_note' => $note,
                'status' => PurchaseVoucherApproval::STATUS_PENDING,
            ]);
        }

        $voucher->forceFill([
            'status' => PurchaseVoucher::STATUS_PENDING_APPROVAL,
            'submitted_at' => $voucher->submitted_at ?: now(),
            'approved_at' => null,
            'rejected_at' => null,
        ])->save();

        return $voucher->fresh(['vendor', 'items.ingredient.baseUnit', 'currentApprovals.approver', 'currentApprovals.assigner']);
    }

    private function refreshApprovalStatus(PurchaseVoucher $voucher): void
    {
        $progress = $this->approvalProgress($voucher);
        if ($progress['approved'] >= $progress['minimum']) {
            PurchaseVoucherApproval::query()
                ->where('purchase_voucher_id', $voucher->id)
                ->where('revision_no', $voucher->revision_no)
                ->where('status', PurchaseVoucherApproval::STATUS_PENDING)
                ->update(['status' => PurchaseVoucherApproval::STATUS_CANCELLED, 'updated_at' => now()]);

            $voucher->forceFill([
                'status' => PurchaseVoucher::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
            ])->save();
            return;
        }

        $voucher->forceFill([
            'status' => PurchaseVoucher::STATUS_PENDING_APPROVAL,
            'approved_at' => null,
            'rejected_at' => PurchaseVoucherApproval::query()
                ->where('purchase_voucher_id', $voucher->id)
                ->where('revision_no', $voucher->revision_no)
                ->where('status', PurchaseVoucherApproval::STATUS_REJECTED)
                ->max('acted_at'),
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
                        'approver_user_id', 'approver_name', 'approver_email', 'approval_order', 'batch_no', 'assigned_by', 'assigned_at', 'dispatch_note', 'status', 'comment', 'acted_at',
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
