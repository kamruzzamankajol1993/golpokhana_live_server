<?php

namespace App\Models;

use Carbon\Carbon;
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

    public function getLocalLoggedTimeAttribute(): ?Carbon
    {
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        $value = $payload['logged_time'] ?? null;

        if ($value) {
            try {
                return Carbon::parse((string) $value);
            } catch (\Throwable) {
            }
        }

        return $this->logged_time;
    }

    public function getLocalSyncTimeAttribute(): ?Carbon
    {
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        $value = $payload['sync_time'] ?? null;

        if ($value) {
            try {
                return Carbon::parse((string) $value);
            } catch (\Throwable) {
            }
        }

        return $this->sync_time;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
