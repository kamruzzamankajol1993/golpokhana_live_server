<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Occasion;
use Exception;

class OccasionController extends Controller
{
    // AJAX Table & Pagination Load
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $occasions = Occasion::orderBy('id', 'desc')->paginate(5);
            $html = view('admin.table_booking.modals.occasion_table', compact('occasions'))->render();
            return response()->json(['html' => $html]);
        }
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            Occasion::create(['name' => $request->name, 'status' => $request->status ?? 'Active']);
            $occasions = Occasion::where('status', 'Active')->get(['id', 'name']); // লেটেস্ট ডাটা ফেচ
            return response()->json(['status' => 'success', 'message' => 'Occasion added successfully!', 'occasions' => $occasions]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to add occasion!']);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            Occasion::findOrFail($id)->update(['name' => $request->name, 'status' => $request->status]);
            $occasions = Occasion::where('status', 'Active')->get(['id', 'name']); // লেটেস্ট ডাটা ফেচ
            return response()->json(['status' => 'success', 'message' => 'Occasion updated successfully!', 'occasions' => $occasions]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update occasion!']);
        }
    }

    public function destroy($id)
    {
        try {
            Occasion::findOrFail($id)->delete();
            $occasions = Occasion::where('status', 'Active')->get(['id', 'name']); // লেটেস্ট ডাটা ফেচ
            return response()->json(['status' => 'success', 'message' => 'Occasion deleted successfully!', 'occasions' => $occasions]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to delete occasion!']);
        }
    }
}
