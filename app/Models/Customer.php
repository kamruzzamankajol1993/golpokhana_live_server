<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $guarded = [];

    // কাস্টমার লয়্যালটি মেম্বার কিনা তা চেক করার জন্য একটি হেল্পার মেথড
    // (যাদের পয়েন্ট ৫ এর বেশি তারা লয়্যালটি মেম্বার)
    public function isLoyaltyMember()
    {
        return $this->points > 5;
    }

    // নতুন রিলেশনশিপ অ্যাড করা হলো
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function pointHistories() {
    return $this->hasMany(PointHistory::class);
}
}
