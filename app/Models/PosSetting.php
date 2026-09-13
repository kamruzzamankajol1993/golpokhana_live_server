<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'auto_print_kitchen'      => 'boolean',
        'auto_print_invoice'      => 'boolean',
        'require_table_selection' => 'boolean',
        'show_out_of_stock'                => 'boolean',
        'given_money_manual_toggle_enabled' => 'boolean',
        'allow_payment_with_insufficient_given_money' => 'boolean',
        'show_honored_percentage_on_invoice' => 'boolean',
        'complimentary_note_required'          => 'boolean',
        'dine_in_waiter_required'              => 'boolean',
        'order_list_random_half_enabled'    => 'boolean',
        'random_half_order_button_visible'  => 'boolean',
        'random_order_hide_percentage'      => 'integer',
        'items_per_page'                    => 'integer',
    ];
}
