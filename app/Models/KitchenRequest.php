<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenRequest extends Model
{
    use HasFactory, BelongsToBranch;

    public const TYPE_FOOD = 'FOOD';
    public const TYPE_INGREDIENT = 'INGREDIENT';
    public const TYPE_MIXED = 'MIXED';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_PARTIALLY_ISSUED = 'PARTIALLY_ISSUED';
    public const STATUS_FULLY_ISSUED = 'FULLY_ISSUED';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $guarded = [];

    protected $casts = [
        'request_date' => 'date',
        'submitted_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public static function types(): array
    {
        return [self::TYPE_FOOD, self::TYPE_INGREDIENT, self::TYPE_MIXED];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_PARTIALLY_ISSUED,
            self::STATUS_FULLY_ISSUED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED], true);
    }

    public function canIssue(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_PARTIALLY_ISSUED], true);
    }

    public function foodItems()
    {
        return $this->hasMany(KitchenRequestFoodItem::class);
    }

    public function ingredientItems()
    {
        return $this->hasMany(KitchenRequestIngredientItem::class);
    }

    public function transfers()
    {
        return $this->hasMany(StockTransfer::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
