<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflinePosDevice extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'device_name',
        'device_uuid',
        'device_key',
        'status',
        'last_seen_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

}
