<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CuisineType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cuisine) {
            $cuisine->slug = static::generateUniqueSlug($cuisine->name);
        });

        static::updating(function ($cuisine) {
            if ($cuisine->isDirty('name')) {
                $cuisine->slug = static::generateUniqueSlug($cuisine->name);
            }
        });
    }

    private static function generateUniqueSlug($name)
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'LIKE', "{$slug}%")->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }

    // ফুড আইটেম কাউন্ট করার জন্য রিলেশন
    public function foodItems()
    {
        return $this->hasMany(FoodItem::class, 'cuisine_type_id');
    }
}
