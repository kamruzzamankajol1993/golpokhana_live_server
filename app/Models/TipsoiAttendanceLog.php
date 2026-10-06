<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipsoiAttendanceLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sync_time' => 'datetime',
        'logged_time' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
