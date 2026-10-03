<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryPurchaseApprover extends Model
{
    protected $guarded = [];

    protected $casts = [
        'approval_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('approval_order');
    }
}
