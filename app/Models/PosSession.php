<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSession extends Model
{
    use HasFactory;

    protected $table = 'pos_sessions';

    protected $fillable = [
        'user_id',
        'weekday',
        'start_time',
        'end_time',
        'duration',
        'status',
        'sales_total',
        'service_charge',
        'vat_total',
        'grand_total',
        'incomes_summary'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'incomes_summary' => 'array', // JSON ডেটাকে অটোমেটিক Array-তে কনভার্ট করার জন্য
    ];

    /**
     * রিলেশনশিপ: সেশনটি কোন ইউজারের
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * এই সেশনের আন্ডারে হওয়া অর্ডারগুলো পাওয়ার জন্য হেল্পার মেথড
     */
    public function getSessionOrders()
    {
        $query = Order::where('created_at', '>=', $this->start_time);

        if ($this->end_time) {
            $query->where('created_at', '<=', $this->end_time);
        }

        return $query->get();
    }
}
