<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableBooking extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'booking_date' => 'date',
        'booking_start_time' => 'datetime:H:i',
        'booking_end_time' => 'datetime:H:i',
    ];

    // রিলেশনশিপস
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function occasion()
    {
        return $this->belongsTo(Occasion::class);
    }
}
