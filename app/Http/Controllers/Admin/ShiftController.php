<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shift;
use Exception;

class ShiftController extends Controller
{
    // Ajax ভিত্তিক টেবিল লোড
  public function index(Request $request)
{
    if ($request->has('dropdown')) {
        return response()->json(Shift::where('status', 1)->get());
    }

    $shifts = Shift::orderBy('id', 'desc')->paginate(5);
    if ($request->ajax()) {
        return view('admin.waiter.shift_table', compact('shifts'))->render();
    }
    return response()->json(['error' => 'Invalid request']);
}

    // Ajax ভিত্তিক Create
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            Shift::create([
                'name' => $request->name,
                'status' => $request->has('status') ? 1 : 0,
            ]);
            return response()->json(['success' => true, 'message' => 'Shift created successfully!']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Ajax ভিত্তিক Edit Data Fetch
    public function edit($id)
    {
        $shift = Shift::findOrFail($id);
        return response()->json($shift);
    }

    // Ajax ভিত্তিক Update
    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            $shift = Shift::findOrFail($id);
            $shift->update([
                'name' => $request->name,
                'status' => $request->has('status') ? 1 : 0,
            ]);
            return response()->json(['success' => true, 'message' => 'Shift updated successfully!']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Normal Delete
    public function destroy($id)
    {
        try {
            Shift::findOrFail($id)->delete();
            return back()->with('success', 'Shift deleted successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete shift!');
        }
    }
}
