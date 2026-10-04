<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class Purchase extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const GRN_PENDING = 'PENDING';
    public const GRN_CONFIRMED = 'CONFIRMED';

    protected $guarded = [];

    protected $casts = [
        'purchase_date' => 'date',
        'received_at' => 'datetime',
        'grn_confirmed_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::updating(function (Purchase $purchase) {
            if ($purchase->getOriginal('status') === self::STATUS_RECEIVED) {
                throw new LogicException('Received purchases are immutable. Use purchase return/reversal or an authorized adjustment for corrections.');
            }
        });

        static::deleting(function (Purchase $purchase) {
            if ($purchase->status === self::STATUS_RECEIVED) {
                throw new LogicException('Received purchases cannot be deleted. Use purchase return/reversal instead.');
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receivedMovement()
    {
        return $this->belongsTo(StockMovement::class, 'received_stock_movement_id');
    }

    public function voucher()
    {
        return $this->belongsTo(PurchaseVoucher::class, 'purchase_voucher_id');
    }

    public function vendorPayments()
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function grnConfirmer()
    {
        return $this->belongsTo(User::class, 'grn_confirmed_by');
    }

    public function paidAmount(): float
    {
        if (array_key_exists('vendor_payments_sum_amount', $this->attributes)) {
            return (float) ($this->attributes['vendor_payments_sum_amount'] ?? 0);
        }

        if ($this->relationLoaded('vendorPayments')) {
            return (float) $this->vendorPayments->sum('amount');
        }

        return (float) $this->vendorPayments()->sum('amount');
    }

    public function dueAmount(): float
    {
        if ($this->status !== self::STATUS_RECEIVED) {
            return 0.0;
        }

        return max(0, round((float) $this->total - $this->paidAmount(), 4));
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->received_stock_movement_id === null;
    }
}
