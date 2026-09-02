<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryException extends Model
{
    use HasFactory, BelongsToBranch;

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const NEGATIVE_KITCHEN_STOCK = 'NEGATIVE_KITCHEN_STOCK';
    public const MISSING_RECIPE = 'MISSING_RECIPE';

    protected $guarded = [];
    protected $casts = [
        'shortage_base' => 'decimal:8',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function ingredient() { return $this->belongsTo(Ingredient::class); }
    public function location() { return $this->belongsTo(StockLocation::class, 'location_id'); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
}
