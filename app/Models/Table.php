<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use HasFactory;

    protected $guarded = [];

    // রিলেশনশিপ (একটি টেবিল একটি জোনের অধীনে থাকে)
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    // নতুন রিলেশনশিপ: একটি টেবিলে একাধিক অর্ডার থাকতে পারে
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
