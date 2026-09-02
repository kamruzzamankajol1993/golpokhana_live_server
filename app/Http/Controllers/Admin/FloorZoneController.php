<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FloorZone;
use Illuminate\Http\Request;
use Exception;

class FloorZoneController extends Controller
{
    public function index(Request $request)
    {
        $query = FloorZone::orderByDesc('id');
        $floorZones = $query->paginate(10);

        if ($request->ajax()) {
            return view('admin.floor_zone.table', compact('floorZones'))->render();
        }

        return view('admin.floor_zone.index');
    }

    public function store(Request $request)
    {
        $request->validate(['name'=>'required|string|max:100']);
        try {
            FloorZone::create([
                'name'=>$request->name,
                'status'=>$request->has('status') ? 1 : 0,
            ]);
            return response()->json(['success'=>true,'message'=>'Floor / Zone created successfully']);
        } catch (Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    public function edit($id)
    {
        return response()->json(FloorZone::findOrFail($id));
    }

    public function update(Request $request,$id)
    {
        $request->validate(['name'=>'required|string|max:100']);
        try {
            FloorZone::findOrFail($id)->update([
                'name'=>$request->name,
                'status'=>$request->has('status') ? 1 : 0,
            ]);
            return response()->json(['success'=>true,'message'=>'Floor / Zone updated successfully']);
        } catch (Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        FloorZone::findOrFail($id)->delete();
        return response()->json(['success'=>true,'message'=>'Floor / Zone deleted successfully']);
    }
}
