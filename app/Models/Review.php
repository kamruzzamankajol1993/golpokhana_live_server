<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Relation: Review konti order er ta ber korar jonno
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
