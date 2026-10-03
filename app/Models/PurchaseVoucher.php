<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseVoucher extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_SENT_TO_VENDOR = 'SENT_TO_VENDOR';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $guarded = [];

    protected $casts = [
        'voucher_date' => 'date',
        'subtotal' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax' => 'decimal:4',
        'total' => 'decimal:4',
        'revision_no' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'sent_to_vendor_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseVoucherItem::class);
    }

    public function approvals()
    {
        return $this->hasMany(PurchaseVoucherApproval::class)->orderBy('revision_no')->orderBy('approval_order');
    }

    public function currentApprovals()
    {
        // Kept as a relation for eager loading; callers filter by the voucher's current revision_no.
        return $this->hasMany(PurchaseVoucherApproval::class)
            ->orderByDesc('revision_no')
            ->orderBy('approval_order');
    }

    public function revisions()
    {
        return $this->hasMany(PurchaseVoucherRevision::class)->orderByDesc('revision_no');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sentToVendorBy()
    {
        return $this->belongsTo(User::class, 'sent_to_vendor_by');
    }

    public function convertedPurchase()
    {
        return $this->belongsTo(Purchase::class, 'converted_purchase_id');
    }

    public function purchase()
    {
        return $this->hasOne(Purchase::class, 'purchase_voucher_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)
            && !$this->converted_purchase_id;
    }

    public function canSubmit(): bool
    {
        // Rejected vouchers must be revised/saved first so the rejected revision
        // remains immutable in purchase_voucher_revisions and approval history.
        return $this->status === self::STATUS_DRAFT && !$this->converted_purchase_id;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function canSendToVendor(): bool
    {
        return $this->status === self::STATUS_APPROVED && !$this->sent_to_vendor_at;
    }

    public function canReceiveSupply(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_SENT_TO_VENDOR], true)
            && $this->sent_to_vendor_at !== null
            && !$this->converted_purchase_id;
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', $this->status);
    }
}
