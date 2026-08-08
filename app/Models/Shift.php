<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $guarded = [];

    // রিলেশনশিপ: একটি শিফটে অনেক ওয়েটার থাকতে পারে
    public function waiters()
    {
        return $this->hasMany(Waiter::class);
    }
}
