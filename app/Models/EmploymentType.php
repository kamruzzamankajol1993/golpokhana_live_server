<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_hourly' => 'boolean',
        'status' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
