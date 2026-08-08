<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CourseType;
use Exception;

class CourseTypeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $courseTypes = CourseType::orderBy('id', 'desc')->paginate(5, ['*'], 'course_page');
            return view('admin.food_attributes.course_type_table', compact('courseTypes'))->render();
        }
        return redirect()->route('allergen.index');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            CourseType::create(['name' => $request->name, 'status' => $request->has('is_active') ? 1 : 0]);
            return response()->json(['status' => 'success', 'message' => 'Course Type added successfully!']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to add!']);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            CourseType::findOrFail($id)->update(['name' => $request->name, 'status' => $request->has('is_active') ? 1 : 0]);
            return response()->json(['status' => 'success', 'message' => 'Course Type updated!']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update!']);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $course = CourseType::findOrFail($id);
            $course->update(['status' => $request->status]);
            return response()->json(['status' => 'success', 'message' => 'Status updated!']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update status!']);
        }
    }

    public function destroy($id)
    {
        try {
            CourseType::findOrFail($id)->delete();
            return back()->with('success', 'Course Type deleted successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete!');
        }
    }
}
