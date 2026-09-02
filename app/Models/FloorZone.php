<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FloorZone extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function tables()
    {
        return $this->hasMany(Table::class);
    }
}
