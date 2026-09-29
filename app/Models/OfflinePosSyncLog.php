<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflinePosSyncLog extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'device_id',
        'type',
        'status',
        'message',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(OfflinePosDevice::class, 'device_id');
    }
}
