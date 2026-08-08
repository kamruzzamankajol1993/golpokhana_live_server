<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosDeletedItemHistory extends Model
{
    protected $fillable = [
        'order_id',
        'order_detail_id',
        'order_kot_id',
        'food_id',
        'product_name',
        'unit_price',
        'addon_total',
        'deleted_quantity',
        'previous_quantity',
        'remaining_quantity',
        'subtotal_removed',
        'source',
        'cart_key',
        'cart_item_key',
        'order_type',
        'table_id',
        'addons',
        'note',
        'deleted_by',
        'reason',
    ];

    protected $casts = [
        'addons' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
