<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

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
