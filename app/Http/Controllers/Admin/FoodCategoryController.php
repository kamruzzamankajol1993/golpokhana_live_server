<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FoodCategory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Laravel\Facades\Image; // Intervention Image Version 3 Facade
use Exception;

class FoodCategoryController extends Controller
{
   public function index(Request $request)
    {
        // subcategories এবং foodItems এর কাউন্ট একসাথেই আনা হলো
        $query = FoodCategory::with('parent')->withCount(['subcategories', 'foodItems']);

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->status !== null && $request->status !== '') {
            $status = $request->status == 'active' ? 1 : 0;
            $query->where('status', $status);
        }

        $categories = $query->orderBy('sort_order', 'asc')->paginate(10);
        $parentCategories = FoodCategory::whereNull('parent_category_id')->get();

        if ($request->ajax()) {
            return view('admin.food_category.category_table', compact('categories'))->render();
        }

        return view('admin.food_category.index', compact('categories', 'parentCategories'));
    }

    // Inline Status Update (AJAX)
    public function updateStatus(Request $request, $id)
    {
        try {
            $category = FoodCategory::findOrFail($id);
            $category->status = $request->status; // 1 or 0
            $category->save();

            return response()->json(['status' => 'success', 'message' => 'Status updated successfully!']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update status!']);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        DB::beginTransaction();
        try {
            $imageName = null;
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $imageName = time() . '.' . $imageFile->getClientOriginalExtension();
                $path = public_path('uploads/categories/' . $imageName);

                // Intervention Image v3: read() এবং cover() ব্যবহার করা হয়েছে (100x100)
                Image::read($imageFile->getRealPath())->cover(100, 100)->save($path);
            }

            FoodCategory::create([
                'name' => $request->name,
                'parent_category_id' => $request->parent_category_id,
                'image' => $imageName,
                'sort_order' => $request->sort_order ?? 0,
                'status' => $request->has('is_active') ? 1 : 0,
            ]);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Category saved successfully!']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to save category! ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        DB::beginTransaction();
        try {
            $category = FoodCategory::findOrFail($id);
            $imageName = $category->image;

            if ($request->hasFile('image')) {
                // পুরানো ইমেজ ডিলিট করা
                if ($imageName && File::exists(public_path('uploads/categories/' . $imageName))) {
                    File::delete(public_path('uploads/categories/' . $imageName));
                }

                $imageFile = $request->file('image');
                $imageName = time() . '.' . $imageFile->getClientOriginalExtension();
                $path = public_path('uploads/categories/' . $imageName);

                // Intervention Image v3: read() এবং cover() ব্যবহার করা হয়েছে (100x100)
                Image::read($imageFile->getRealPath())->cover(100, 100)->save($path);
            }

            $category->update([
                'name' => $request->name,
                'parent_category_id' => $request->parent_category_id,
                'image' => $imageName,
                'sort_order' => $request->sort_order ?? 0,
                'status' => $request->has('is_active') ? 1 : 0,
            ]);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Category updated successfully!']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to update category! ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $category = FoodCategory::findOrFail($id);

            // ডাটাবেস থেকে ডিলিট হওয়ার আগে ফোল্ডার থেকে ইমেজ মুছে ফেলা
            if ($category->image && File::exists(public_path('uploads/categories/' . $category->image))) {
                File::delete(public_path('uploads/categories/' . $category->image));
            }

            $category->delete();
            DB::commit();

            // নরমাল সাবমিটের পর পেজ রিডাইরেক্ট হবে
            return back()->with('success', 'Category deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete category! ' . $e->getMessage());
        }
    }

     public function getSubcategories($id)
{
    $subcategories = FoodCategory::where('parent_category_id', $id)->where('status', 1)->get();
    return response()->json($subcategories);
}
}
