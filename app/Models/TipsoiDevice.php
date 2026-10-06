<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipsoiDevice extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'has_enrollment_feature' => 'boolean',
        'is_mqtt_enabled' => 'boolean',
        'mqtt_allow_batch_rfid' => 'boolean',
        'connected' => 'boolean',
        'data_dump_requested' => 'boolean',
        'last_communication_at' => 'datetime',
        'synced_at' => 'datetime',
        'raw_payload' => 'array',
    ];
}
