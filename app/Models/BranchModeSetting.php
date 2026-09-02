<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchModeSetting extends Model
{
    use HasFactory;

    public const MODE_SINGLE = 'single';
    public const MODE_MULTIPLE = 'multiple';

    protected $guarded = [];

    protected $casts = [
        'multi_branch_activated_at' => 'datetime',
        'multi_branch_locked_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['mode' => self::MODE_MULTIPLE, 'multi_branch_activated_at' => now()]
        );
    }

    public function isSingle(): bool
    {
        return $this->mode === self::MODE_SINGLE;
    }

    public function isMultiple(): bool
    {
        return $this->mode === self::MODE_MULTIPLE;
    }
}
