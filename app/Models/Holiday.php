<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'holiday_date' => 'date',
        'is_paid' => 'boolean',
        'status' => 'boolean',
    ];
}
