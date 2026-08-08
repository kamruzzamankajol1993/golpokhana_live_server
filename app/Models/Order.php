<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    // ইনভয়েস সেটিং অনুযায়ী অর্ডার আইডি জেনারেট করার লজিক
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
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

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
