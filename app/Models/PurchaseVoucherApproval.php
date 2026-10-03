<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseVoucherApproval extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $guarded = [];

    protected $casts = [
        'revision_no' => 'integer',
        'approval_order' => 'integer',
        'acted_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(PurchaseVoucher::class, 'purchase_voucher_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
