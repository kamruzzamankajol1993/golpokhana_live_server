<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Zone;
use Exception;

class ZoneController extends Controller
{
    // Ajax ভিত্তিক টেবিল লোড
   public function index(Request $request)
{
    // ড্রপডাউনের জন্য রিকোয়েস্ট আসলে শুধুমাত্র একটিভ ডাটা পাঠাবে
    if ($request->has('dropdown')) {
        return response()->json(Zone::where('status', 1)->get());
    }

    $zones = Zone::orderBy('id', 'desc')->paginate(5);
    if ($request->ajax()) {
        return view('admin.waiter.zone_table', compact('zones'))->render();
    }
    return response()->json(['error' => 'Invalid request']);
}

    // Ajax ভিত্তিক Create
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            Zone::create([
                'name' => $request->name,
                'status' => $request->has('status') ? 1 : 0,
            ]);
            return response()->json(['success' => true, 'message' => 'Zone created successfully!']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Ajax ভিত্তিক Edit Data Fetch
    public function edit($id)
    {
        $zone = Zone::findOrFail($id);
        return response()->json($zone);
    }

    // Ajax ভিত্তিক Update
    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        try {
            $zone = Zone::findOrFail($id);
            $zone->update([
                'name' => $request->name,
                'status' => $request->has('status') ? 1 : 0,
            ]);
            return response()->json(['success' => true, 'message' => 'Zone updated successfully!']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Normal Delete (উইথ সুইট এলার্ট এর জন্য ব্যাক হবে)
    public function destroy($id)
    {
        try {
            Zone::findOrFail($id)->delete();
            return back()->with('success', 'Zone deleted successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete zone!');
        }
    }
}
