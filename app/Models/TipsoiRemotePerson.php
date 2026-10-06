<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipsoiRemotePerson extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tipsoi_person_id' => 'integer',
        'total_fingerprints' => 'integer',
        'remote_updated_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
