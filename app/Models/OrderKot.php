<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderKot extends Model
{
    use HasFactory;

    // ডাটাবেসের সকল কলামে ডাটা ইনসার্ট করার অনুমতি দেওয়ার জন্য
    protected $guarded = [];

    /**
     * এই KOT টি কোন অর্ডারের আন্ডারে আছে তা বের করার জন্য (Inverse One-to-Many)
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * এই নির্দিষ্ট KOT এর আন্ডারে কি কি ফুড আইটেম (Order Details) আছে তা বের করার জন্য (One-to-Many)
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'order_kot_id');
    }
}
