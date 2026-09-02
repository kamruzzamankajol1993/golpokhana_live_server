<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class MenuItemRecipe extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'yield_quantity' => 'decimal:8',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (MenuItemRecipe $recipe) {
            if (!$recipe->getOriginal('is_active')) {
                throw new LogicException('Historical recipe versions are immutable. Create a new version instead.');
            }

            $dirty = array_keys($recipe->getDirty());
            $notLifecycle = array_diff($dirty, ['is_active']);
            if ($notLifecycle !== []) {
                throw new LogicException('Recipe content cannot be edited in place. Create a new recipe version instead.');
            }
        });

        static::deleting(function () {
            throw new LogicException('Recipe versions cannot be deleted. Preserve them as history.');
        });
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class, 'menu_item_id');
    }

    public function items()
    {
        return $this->hasMany(MenuItemRecipeItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
