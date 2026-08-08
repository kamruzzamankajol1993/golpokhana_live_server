<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Allergen;
use App\Models\CourseType;
use Exception;

class AllergenController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $allergens = Allergen::orderBy('id', 'desc')->paginate(5, ['*'], 'allergen_page');
            return view('admin.food_attributes.allergen_table', compact('allergens'))->render();
        }

        // Initial Load (উভয় টেবিলের ডাটা একসাথে পাঠানো হলো)
        $allergens = Allergen::orderBy('id', 'desc')->paginate(5, ['*'], 'allergen_page');
        $courseTypes = CourseType::orderBy('id', 'desc')->paginate(5, ['*'], 'course_page');

        return view('admin.food_attributes.index', compact('allergens', 'courseTypes'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            Allergen::create(['name' => $request->name, 'status' => $request->has('is_active') ? 1 : 0]);
            return response()->json(['status' => 'success', 'message' => 'Allergen added successfully!']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to add!']);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            Allergen::findOrFail($id)->update(['name' => $request->name, 'status' => $request->has('is_active') ? 1 : 0]);
            return response()->json(['status' => 'success', 'message' => 'Allergen updated!']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update!']);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $allergen = Allergen::findOrFail($id);
            $allergen->update(['status' => $request->status]);
            return response()->json(['status' => 'success', 'message' => 'Status updated!']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update status!']);
        }
    }

    public function destroy($id)
    {
        try {
            Allergen::findOrFail($id)->delete();
            return back()->with('success', 'Allergen deleted successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete!');
        }
    }
}
