<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLocation extends Model
{
    use HasFactory, BelongsToBranch;

    public const TYPE_MAIN = 'MAIN';
    public const TYPE_KITCHEN = 'KITCHEN';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function balances()
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function outgoingMovements()
    {
        return $this->hasMany(StockMovement::class, 'source_location_id');
    }

    public function incomingMovements()
    {
        return $this->hasMany(StockMovement::class, 'destination_location_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
