<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    // JSON কলামগুলোকে Array তে কাস্ট করা হলো
    protected $casts = [
        'allergens' => 'array',
        'active_days' => 'array',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_chefs_special' => 'boolean',
        'is_dine_in' => 'boolean',
        'is_takeaway' => 'boolean',
       'is_draft' => 'boolean', // নতুন যোগ করা হলো
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(FoodCategory::class, 'food_category_id');
    }

    public function subCategory()
    {
        return $this->belongsTo(FoodCategory::class, 'sub_category_id');
    }

    public function cuisineType()
    {
        return $this->belongsTo(CuisineType::class, 'cuisine_type_id');
    }

    public function courseType()
    {
        return $this->belongsTo(CourseType::class, 'course_type_id');
    }

    // Addons Relationship
    public function addons()
    {
        return $this->hasMany(FoodAddon::class, 'food_item_id');
    }

    // Gallery Images Relationship
    public function galleryImages()
    {
        return $this->hasMany(FoodImage::class, 'food_item_id');
    }
}
