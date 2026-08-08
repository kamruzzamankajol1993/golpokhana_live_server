<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $guarded = [];

    // রিলেশনশিপ: একটি জোনে অনেক ওয়েটার থাকতে পারে
    public function waiters()
    {
        return $this->hasMany(Waiter::class);
    }

    // রিলেশনশিপ: একটি জোনে অনেকগুলো টেবিল থাকতে পারে
    public function tables()
    {
        return $this->hasMany(Table::class);
    }
}
