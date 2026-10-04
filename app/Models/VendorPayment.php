<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorPayment extends Model
{
    use HasFactory;

    public const TYPE_CASH = 'Cash';
    public const TYPE_CARD = 'Card';
    public const TYPE_MFS = 'MFS';
    public const TYPE_SPLIT = 'Split';

    public const CARD_TYPES = [
        'Visa', 'Mastercard', 'American Express', 'UnionPay', 'JCB', 'Nexus',
        'Diners Club', 'GPay', 'Bangla QR Card', 'Other',
    ];

    public const MFS_PROVIDERS = [
        'Rocket', 'bKash', 'MYCash', 'Islami Bank mCash', 'tap', 'FirstCash',
        'Upay', 'OK Wallet', 'RUPALICASH', 'TeleCash', 'Islamic Wallet',
        'Meghna Pay', 'Nagad', 'Bangla QR', 'LENDEN', 'Other',
    ];

    protected $guarded = [];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:4',
        'paid_in_cash' => 'decimal:4',
        'paid_in_card' => 'decimal:4',
        'paid_in_mfs' => 'decimal:4',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
