<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchNumberSequence extends Model
{
    protected $guarded = [];

    protected $casts = [
        'next_number' => 'integer',
        'padding' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
