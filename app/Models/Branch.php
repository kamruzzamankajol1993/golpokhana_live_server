<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_main' => 'boolean',
        'status' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function stockLocations()
    {
        return $this->hasMany(StockLocation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }
}
