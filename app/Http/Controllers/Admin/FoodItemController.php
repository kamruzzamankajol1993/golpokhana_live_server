<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FoodItem;
use App\Models\FoodCategory;
use App\Models\CuisineType;
use App\Models\CourseType;
use App\Models\Allergen;
use App\Models\FoodAddon;
use App\Models\FoodImage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log; // Logging এর জন্য
use Intervention\Image\Laravel\Facades\Image;
use Exception;

class FoodItemController extends Controller
{
    /**
     * Display a listing of the Food Items.
     */
    public function index(Request $request)
    {
        try {
            $query = FoodItem::with(['category', 'cuisineType']);

            // Filters
            if ($request->search) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }
            if ($request->category) {
                $query->where('food_category_id', $request->category)->orWhere('sub_category_id', $request->category);
            }
            if ($request->cuisine) {
                $query->where('cuisine_type_id', $request->cuisine);
            }
            if ($request->availability) {
                $query->where('is_available', $request->availability === 'available' ? 1 : 0);
            }
            if ($request->featured) {
                $query->where('is_featured', $request->featured === 'featured' ? 1 : 0);
            }

            $foodItems = $query->orderBy('id', 'desc')->paginate(10);

            // Dropdown data for filters
            $categories = FoodCategory::whereNull('parent_category_id')->where('status', 1)->get();
            $cuisines = CuisineType::where('status', 1)->get();

            if ($request->ajax()) {
                return view('admin.food_item.food_table', compact('foodItems'))->render();
            }

            return view('admin.food_item.index', compact('foodItems', 'categories', 'cuisines'));
        } catch (Exception $e) {
            Log::error('Error loading Food Item Index: ' . $e->getMessage());
            return abort(500, 'Something went wrong while loading the page.');
        }
    }

    /**
     * Show the form for creating a new Food Item.
     */
    public function create()
    {
        try {
            $categories = FoodCategory::whereNull('parent_category_id')->where('status', 1)->get();
            $subCategories = FoodCategory::whereNotNull('parent_category_id')->where('status', 1)->get();
            $cuisines = CuisineType::where('status', 1)->get();
            $courseTypes = CourseType::where('status', 1)->get();
            $allergens = Allergen::where('status', 1)->get();

            return view('admin.food_item.create', compact('categories', 'subCategories', 'cuisines', 'courseTypes', 'allergens'));
        } catch (Exception $e) {
            Log::error('Error loading Add Food page: ' . $e->getMessage());
            return back()->with('error', 'Failed to load create page!');
        }
    }

    /**
     * Store a newly created Food Item.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'food_category_id' => 'required',
            'base_price' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            // Main Image Upload
            $mainImageName = null;
            if ($request->hasFile('main_image')) {
                $imageFile = $request->file('main_image');
                $mainImageName = time() . '_main.' . $imageFile->getClientOriginalExtension();
                $path = public_path('uploads/foods/' . $mainImageName);
                Image::read($imageFile->getRealPath())->cover(400, 400)->save($path);
            }

            // Insert into food_items table (User correction: using base_price and discount_price)
            $foodItem = FoodItem::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name) . '-' . time(),
                'short_description' => $request->short_description,
                'description' => $request->description,
                'food_category_id' => $request->food_category_id,
                'sub_category_id' => $request->sub_category_id,
                'cuisine_type_id' => $request->cuisine_type_id,
                'course_type_id' => $request->course_type_id,
                'spice_level' => $request->spice_level,
                'serving_size' => $request->serving_size,
                'base_price' => $request->base_price,
                'bengali_name' => $request->bengali_name,
                'discount_price' => $request->discount_price,
                'tax_rate' => $request->tax_rate,
                'preparation_time' => $request->preparation_time,
                'calories' => $request->calories,
                'is_draft' => $request->is_draft ?? 0,
                'allergens' => $request->allergens ?? [],
                'allergen_notes' => $request->allergen_notes,
                'main_image' => $mainImageName,
                'is_available' => $request->has('is_available') ? 1 : 0,
                'is_featured' => $request->has('is_featured') ? 1 : 0,
                'is_chefs_special' => $request->has('is_chefs_special') ? 1 : 0,
                'is_dine_in' => $request->has('is_dine_in') ? 1 : 0,
                'is_takeaway' => $request->has('is_takeaway') ? 1 : 0,
                'active_days' => $request->active_days ?? [],
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            // Store Addons
            if ($request->addon_name && is_array($request->addon_name)) {
                foreach ($request->addon_name as $key => $addonName) {
                    if (!empty($addonName)) {
                        FoodAddon::create([
                            'food_item_id' => $foodItem->id,
                            'name' => $addonName,
                            'price' => $request->addon_price[$key] ?? 0,
                        ]);
                    }
                }
            }

            // Store Gallery Images
            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $key => $galleryFile) {
                    $galName = time() . "_gal_{$key}." . $galleryFile->getClientOriginalExtension();
                    $path = public_path('uploads/foods/' . $galName);
                    Image::read($galleryFile->getRealPath())->cover(400, 400)->save($path);
                    FoodImage::create([
                        'food_item_id' => $foodItem->id,
                        'image' => $galName,
                    ]);
                }
            }

            DB::commit();
            Log::info('New Food Item Published Successfully: ' . $foodItem->name . ' (ID: ' . $foodItem->id . ')');

            return response()->json(['status' => 'success', 'message' => 'Food Item published successfully!']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to publish Food Item: ' . $e->getMessage() . ' at Line: ' . $e->getLine());
            return response()->json(['status' => 'error', 'message' => 'Failed to save! Please try again.']);
        }
    }

    /**
     * Show the form for editing the specified Food Item.
     */
    public function edit($id)
    {
        try {
            $foodItem = FoodItem::with(['addons', 'galleryImages'])->findOrFail($id);
            $categories = FoodCategory::whereNull('parent_category_id')->where('status', 1)->get();
            $subCategories = FoodCategory::whereNotNull('parent_category_id')->where('status', 1)->get();
            $cuisines = CuisineType::where('status', 1)->get();
            $courseTypes = CourseType::where('status', 1)->get();
            $allergens = Allergen::where('status', 1)->get();

            return view('admin.food_item.edit', compact('foodItem', 'categories', 'subCategories', 'cuisines', 'courseTypes', 'allergens'));
        } catch (Exception $e) {
            Log::error('Error loading Edit Food page for ID ('.$id.'): ' . $e->getMessage());
            return back()->with('error', 'Food item not found!');
        }
    }

    /**
     * Update the specified Food Item in database.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'food_category_id' => 'required',
            'base_price' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            $foodItem = FoodItem::findOrFail($id);
            $mainImageName = $foodItem->main_image;

            // Main Image Update
            if ($request->hasFile('main_image')) {
                if ($mainImageName && File::exists(public_path('uploads/foods/' . $mainImageName))) {
                    File::delete(public_path('uploads/foods/' . $mainImageName));
                }
                $imageFile = $request->file('main_image');
                $mainImageName = time() . '_main.' . $imageFile->getClientOriginalExtension();
                $path = public_path('uploads/foods/' . $mainImageName);
                Image::read($imageFile->getRealPath())->cover(400, 400)->save($path);
            }

            // Update item details
            $foodItem->update([
                'name' => $request->name,
                'slug' => $foodItem->name !== $request->name ? Str::slug($request->name) . '-' . time() : $foodItem->slug,
                'short_description' => $request->short_description,
                'description' => $request->description,
                'food_category_id' => $request->food_category_id,
                'sub_category_id' => $request->sub_category_id,
                'cuisine_type_id' => $request->cuisine_type_id,
                'course_type_id' => $request->course_type_id,
                'spice_level' => $request->spice_level,
                'serving_size' => $request->serving_size,
                'base_price' => $request->base_price,
                'bengali_name' => $request->bengali_name,
                'discount_price' => $request->discount_price,
                'tax_rate' => $request->tax_rate,
                'preparation_time' => $request->preparation_time,
                'calories' => $request->calories,
                'allergens' => $request->allergens ?? [],
                'allergen_notes' => $request->allergen_notes,
                'main_image' => $mainImageName,
                'is_available' => $request->has('is_available') ? 1 : 0,
                'is_featured' => $request->has('is_featured') ? 1 : 0,
                'is_chefs_special' => $request->has('is_chefs_special') ? 1 : 0,
                'is_dine_in' => $request->has('is_dine_in') ? 1 : 0,
                'is_takeaway' => $request->has('is_takeaway') ? 1 : 0,
                'is_draft' => $request->is_draft ?? 0,
                'active_days' => $request->active_days ?? [],
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            // Update Addons (Delete old ones and insert new)
            FoodAddon::where('food_item_id', $foodItem->id)->delete();
            if ($request->addon_name && is_array($request->addon_name)) {
                foreach ($request->addon_name as $key => $addonName) {
                    if (!empty($addonName)) {
                        FoodAddon::create([
                            'food_item_id' => $foodItem->id,
                            'name' => $addonName,
                            'price' => $request->addon_price[$key] ?? 0,
                        ]);
                    }
                }
            }

            // Update Gallery Images
            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $key => $galleryFile) {
                    $galName = time() . "_gal_{$key}." . $galleryFile->getClientOriginalExtension();
                    $path = public_path('uploads/foods/' . $galName);
                    Image::read($galleryFile->getRealPath())->cover(400, 400)->save($path);
                    FoodImage::create([
                        'food_item_id' => $foodItem->id,
                        'image' => $galName,
                    ]);
                }
            }

            DB::commit();
            Log::info('Food Item Updated Successfully: ' . $foodItem->name . ' (ID: ' . $foodItem->id . ')');

            return response()->json(['status' => 'success', 'message' => 'Food Item updated successfully!']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update Food Item (ID: '.$id.'): ' . $e->getMessage() . ' at Line: ' . $e->getLine());
            return response()->json(['status' => 'error', 'message' => 'Failed to update! Please try again.']);
        }
    }

    /**
     * Inline Status Update (AJAX)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $food = FoodItem::findOrFail($id);
            $field = $request->field;
            $food->$field = $request->status;
            $food->save();

            Log::info('Food Item Status Updated: ' . $food->name . ' - ' . $field . ' = ' . $request->status);
            return response()->json(['status' => 'success', 'message' => 'Status updated!']);
        } catch (Exception $e) {
            Log::error('Failed to update status for Food Item (ID: '.$id.'): ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to update status!']);
        }
    }
/**
     * Display the specified Food Item.
     */
    public function show($id)
    {
        try {
            $foodItem = FoodItem::with([
                'category',
                'subCategory',
                'cuisineType',
                'courseType',
                'addons',
                'galleryImages'
            ])->findOrFail($id);

            return view('admin.food_item.show', compact('foodItem'));
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error loading Food Item Show page for ID ('.$id.'): ' . $e->getMessage());
            return back()->with('error', 'Food item not found or something went wrong!');
        }
    }
    /**
     * Remove the specified Food Item.
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $food = FoodItem::findOrFail($id);

            // Unlink Main Image
            if ($food->main_image && File::exists(public_path('uploads/foods/' . $food->main_image))) {
                File::delete(public_path('uploads/foods/' . $food->main_image));
            }

            // Unlink Gallery Images
            foreach ($food->galleryImages as $img) {
                if (File::exists(public_path('uploads/foods/' . $img->image))) {
                    File::delete(public_path('uploads/foods/' . $img->image));
                }
            }

            $foodName = $food->name;
            $food->delete();

            DB::commit();
            Log::info('Food Item Deleted Successfully: ' . $foodName . ' (ID: ' . $id . ')');

            return back()->with('success', 'Food Item deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete Food Item (ID: '.$id.'): ' . $e->getMessage());
            return back()->with('error', 'Failed to delete! Please try again.');
        }
    }

   
}
