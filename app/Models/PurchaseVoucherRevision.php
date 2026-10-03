<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseVoucherRevision extends Model
{
    protected $guarded = [];

    protected $casts = [
        'revision_no' => 'integer',
        'snapshot' => 'array',
    ];

    public function voucher()
    {
        return $this->belongsTo(PurchaseVoucher::class, 'purchase_voucher_id');
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
