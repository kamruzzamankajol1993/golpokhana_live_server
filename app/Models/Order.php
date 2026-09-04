<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'pre_invoice_snapshot' => 'array',
        'pre_invoice_printed_at' => 'datetime',
    ];

    // ইনভয়েস সেটিং অনুযায়ী অর্ডার আইডি জেনারেট করার লজিক
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->feedback_token)) {
                $order->feedback_token = Str::random(48);
            }

            // ডাটাবেজ থেকে ইনভয়েস সেটিং নিয়ে আসা
            $invoiceSetting = \App\Models\InvoiceSetting::first();

            // যদি সেটিংসে starting_number থাকে তবে সেটি নিবে, নাহলে ডিফল্ট 1001 থেকে শুরু হবে
            $startingNumber = $invoiceSetting && $invoiceSetting->starting_number ? $invoiceSetting->starting_number : 1001;

            // ডাটাবেজের সর্বশেষ অর্ডারটি বের করা (যেগুলো QR- দিয়ে শুরু হয়নি)
            $lastOrder = self::where('order_number', 'NOT LIKE', 'QR-%')
                             ->orderBy('id', 'desc')
                             ->first();

            // চেক করা হচ্ছে অর্ডার নাম্বারটি আসলেই সংখ্যা (numeric) কিনা
            if ($lastOrder && is_numeric($lastOrder->order_number) && $lastOrder->order_number >= $startingNumber) {
                // যদি আগে কোনো অর্ডার থাকে, তবে সর্বশেষ অর্ডারের নাম্বারের সাথে ১ যোগ হবে
                $order->order_number = $lastOrder->order_number + 1;
            } else {
                // যদি এটিই প্রথম অর্ডার হয় অথবা আগের নাম্বার starting_number এর চেয়ে ছোট হয়
                $order->order_number = $startingNumber;
            }
        });
    }

    // ==========================================
    // রিলেশনশিপস (Relationships)
    // ==========================================

    public function deliveryPartner()
    {
        return $this->belongsTo(\App\Models\DeliveryPartner::class, "delivery_partner_id");
    }

    /**
     * Resolve the delivery partner name for both the current ID-based field and
     * older orders that still keep a partner key/ID in `delivery_partner`.
     */
    public function getDeliveryPartnerDisplayNameAttribute(): ?string
    {
        $partner = $this->relationLoaded('deliveryPartner') ? $this->getRelation('deliveryPartner') : null;

        if (!$partner && !empty($this->attributes['delivery_partner_id'] ?? null)) {
            $partner = $this->deliveryPartner()->first();
        }

        if ($partner) {
            return (string) $partner->name;
        }

        $raw = trim((string) ($this->attributes['delivery_partner'] ?? ''));
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $name = \App\Models\DeliveryPartner::query()->whereKey((int) $raw)->value('name');
            if ($name) {
                return (string) $name;
            }
        }

        $legacyLabels = [
            'inhouse' => 'In-house Delivery',
            'foodpanda' => 'Foodpanda',
            'foodi' => 'Foodi',
            'pathao_food' => 'Pathao Food',
        ];

        return $legacyLabels[$raw] ?? $raw;
    }

    /**
     * Resolve the selected delivery partner ID, including numeric legacy rows.
     */
    public function getResolvedDeliveryPartnerIdAttribute(): ?int
    {
        $partnerId = (int) ($this->attributes['delivery_partner_id'] ?? 0);
        if ($partnerId > 0) {
            return $partnerId;
        }

        $raw = trim((string) ($this->attributes['delivery_partner'] ?? ''));
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        $legacyLabels = [
            'inhouse' => 'In-house Delivery',
            'foodpanda' => 'Foodpanda',
            'foodi' => 'Foodi',
            'pathao_food' => 'Pathao Food',
        ];
        $partnerName = $legacyLabels[$raw] ?? $raw;

        $resolved = \App\Models\DeliveryPartner::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($partnerName))])
            ->value('id');

        return $resolved ? (int) $resolved : null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function kots()
    {
        return $this->hasMany(OrderKot::class);
    }

    public function waiter()
    {
        // এটি Waiter মডেলের সাথে কানেক্ট করবে
        return $this->belongsTo(Waiter::class, 'waiter_id');
    }

    public function tableBooking()
    {
        return $this->belongsTo(TableBooking::class, 'table_booking_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function duePayments()
    {
        return $this->hasMany(OrderDuePayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    /**
     * Human-readable Bank / Card label used by reports.
     * New orders store the selected card provider in `card_type`; legacy rows
     * safely fall back to the generic label.
     */
    public function getReportCardLabelAttribute(): string
    {
        $provider = trim((string) ($this->attributes['card_type'] ?? ''));

        return $provider !== '' ? 'Bank / Card (' . $provider . ')' : 'Bank / Card';
    }

    /** Human-readable MFS label used by reports. */
    public function getReportMfsLabelAttribute(): string
    {
        $provider = trim((string) ($this->attributes['mfs_provider'] ?? ''));

        return $provider !== '' ? 'MFS (' . $provider . ')' : 'MFS';
    }

    /**
     * Consistent report payment text for single and split payments.
     */
    public function reportPaymentText(int $decimals = 0, bool $includeSplitAmounts = true): string
    {
        $paymentType = trim((string) ($this->attributes['payment_type'] ?? ''));

        if ($paymentType === 'Card') {
            return $this->report_card_label;
        }

        if ($paymentType === 'Mobile Banking') {
            return $this->report_mfs_label;
        }

        if ($paymentType !== 'Split') {
            return $paymentType !== '' ? $paymentType : 'N/A';
        }

        $parts = [];
        $cash = max(0, (float) ($this->attributes['paid_in_cash'] ?? 0));
        $card = max(0, (float) ($this->attributes['paid_in_card'] ?? 0));
        $mfs = max(0, (float) ($this->attributes['paid_in_mfc'] ?? 0));

        if ($cash > 0) {
            $parts[] = $includeSplitAmounts ? 'Cash: ' . number_format($cash, $decimals) : 'Cash';
        }
        if ($card > 0) {
            $parts[] = $includeSplitAmounts
                ? $this->report_card_label . ': ' . number_format($card, $decimals)
                : $this->report_card_label;
        }
        if ($mfs > 0) {
            $parts[] = $includeSplitAmounts
                ? $this->report_mfs_label . ': ' . number_format($mfs, $decimals)
                : $this->report_mfs_label;
        }

        return $parts ? 'Split (' . implode(', ', $parts) . ')' : 'Split';
    }
    public function ensureFeedbackToken(): string
    {
        if (empty($this->feedback_token)) {
            do {
                $token = Str::random(48);
            } while (self::where('feedback_token', $token)->exists());

            $this->forceFill(['feedback_token' => $token])->saveQuietly();
        }

        return (string) $this->feedback_token;
    }

}
