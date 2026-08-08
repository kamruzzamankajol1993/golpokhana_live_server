<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FoodCategory extends Model
{
    use HasFactory;

    protected $guarded = [];

    // মডেল ইভেন্টের মাধ্যমে অটোমেটিক ইউনিক স্লাগ জেনারেশন
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            $category->slug = static::generateUniqueSlug($category->name);
        });

        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->slug = static::generateUniqueSlug($category->name);
            }
        });
    }

    // ইউনিক স্লাগ তৈরির লজিক
    private static function generateUniqueSlug($name)
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'LIKE', "{$slug}%")->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }

    // রিলেশনশিপ: প্যারেন্ট ক্যাটাগরি
    public function parent()
    {
        return $this->belongsTo(FoodCategory::class, 'parent_category_id');
    }

    // রিলেশনশিপ: সাব-ক্যাটাগরি
    public function subcategories()
    {
        return $this->hasMany(FoodCategory::class, 'parent_category_id');
    }

    public function foodItems()
    {
        return $this->hasMany(FoodItem::class, 'food_category_id');
    }
}
