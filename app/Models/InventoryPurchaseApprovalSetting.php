<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryPurchaseApprovalSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sequential_approval' => 'boolean',
        'minimum_approvers' => 'integer',
    ];
}
