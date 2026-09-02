<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class InventoryAdjustment extends Model
{
    use HasFactory, BelongsToBranch;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';

    protected $guarded = [];
    protected $casts = ['posted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (InventoryAdjustment $adjustment) {
            if ($adjustment->getOriginal('status') === self::STATUS_POSTED) {
                throw new LogicException('Posted inventory adjustments are immutable.');
            }
        });
        static::deleting(function (InventoryAdjustment $adjustment) {
            if ($adjustment->status === self::STATUS_POSTED) {
                throw new LogicException('Posted inventory adjustments cannot be deleted.');
            }
        });
    }

    public function items() { return $this->hasMany(InventoryAdjustmentItem::class); }
    public function location() { return $this->belongsTo(StockLocation::class, 'location_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
