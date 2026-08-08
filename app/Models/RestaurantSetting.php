<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantSetting extends Model
{
    use HasFactory;

    // সব কলাম ইনসার্ট/আপডেট করার পারমিশন দেওয়া হলো
    protected $guarded = [];
}
