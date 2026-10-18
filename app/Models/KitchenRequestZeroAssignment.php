<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenRequestZeroAssignment extends Model
{
    protected $guarded = [];
    protected $casts = ['items' => 'array'];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function kitchenRequest()
    {
        return $this->belongsTo(KitchenRequest::class);
    }
}
