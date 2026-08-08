<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CuisineType;
use Illuminate\Support\Facades\DB;
use Exception;

class CuisineTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = CuisineType::withCount('foodItems');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('origin_country', 'like', '%' . $request->search . '%');
        }

        if ($request->status !== null && $request->status !== '') {
            $status = $request->status == 'active' ? 1 : 0;
            $query->where('status', $status);
        }

        $cuisines = $query->orderBy('id', 'desc')->paginate(10);

        if ($request->ajax()) {
            return view('admin.cuisine_type.cuisine_table', compact('cuisines'))->render();
        }

        return view('admin.cuisine_type.index', compact('cuisines'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'origin_country' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            CuisineType::create([
                'name' => $request->name,
                'origin_country' => $request->origin_country,
                'description' => $request->description,
                'status' => $request->has('is_active') ? 1 : 0,
            ]);
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Cuisine type saved successfully!']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to save cuisine type!']);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'origin_country' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $cuisine = CuisineType::findOrFail($id);
            $cuisine->update([
                'name' => $request->name,
                'origin_country' => $request->origin_country,
                'description' => $request->description,
                'status' => $request->has('is_active') ? 1 : 0,
            ]);
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Cuisine type updated successfully!']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to update cuisine type!']);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $cuisine = CuisineType::findOrFail($id);
            $cuisine->status = $request->status;
            $cuisine->save();
            return response()->json(['status' => 'success', 'message' => 'Status updated successfully!']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update status!']);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            CuisineType::findOrFail($id)->delete();
            DB::commit();
            return back()->with('success', 'Cuisine type deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete cuisine type!');
        }
    }
}
