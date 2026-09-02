<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class InventoryWastage extends Model
{
    use HasFactory, BelongsToBranch;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';

    public const REASON_SPILLAGE = 'SPILLAGE';
    public const REASON_EXPIRED = 'EXPIRED';
    public const REASON_DAMAGED = 'DAMAGED';
    public const REASON_OVERCOOKED = 'OVERCOOKED';
    public const REASON_OTHER = 'OTHER';

    protected $guarded = [];
    protected $casts = ['posted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (InventoryWastage $wastage) {
            if ($wastage->getOriginal('status') === self::STATUS_POSTED) {
                throw new LogicException('Posted wastage records are immutable. Use an adjustment/reversal for corrections.');
            }
        });
        static::deleting(function (InventoryWastage $wastage) {
            if ($wastage->status === self::STATUS_POSTED) {
                throw new LogicException('Posted wastage records cannot be deleted.');
            }
        });
    }

    public static function reasons(): array
    {
        return [self::REASON_SPILLAGE, self::REASON_EXPIRED, self::REASON_DAMAGED, self::REASON_OVERCOOKED, self::REASON_OTHER];
    }

    public function items() { return $this->hasMany(InventoryWastageItem::class); }
    public function location() { return $this->belongsTo(StockLocation::class, 'location_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
