<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Table;
use App\Models\Zone;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TableController extends Controller
{
  public function index()
    {
        $zones = Zone::all();

        // টেবিলের সাথে রানিং অর্ডার, KOT এবং ওয়েটারের ডাটা নিয়ে আসা হচ্ছে
        $tables = Table::with(['zone', 'orders' => function($query) {
            $query->whereIn('status', ['Pending', 'Processing'])
                  ->with(['kots.orderDetails', 'waiter']); // Eager load KOTs and Waiter
        }])->orderBy('table_number', 'asc')->get();

        $tables->map(function ($table) {
            if ($table->orders->count() > 0) {
                $table->dynamic_status = 'occupied';
            } else {
                $table->dynamic_status = strtolower($table->initial_status);
            }
            return $table;
        });

        $totalTables = $tables->count();
        $occupiedCount = $tables->where('dynamic_status', 'occupied')->count();
        $availableCount = $tables->where('dynamic_status', 'available')->count();
        $reservedCount = $tables->where('dynamic_status', 'reserved')->count();

        return view('admin.table.index', compact(
            'tables', 'zones', 'totalTables', 'occupiedCount', 'availableCount', 'reservedCount'
        ));
    }
    public function store(Request $request)
    {
        $request->validate([
            'table_number' => 'required|string|unique:tables,table_number',
            'seating_capacity' => 'required|integer|min:1',
            'zone_id' => 'required|exists:zones,id',
            'initial_status' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            Table::create([
                'table_number' => $request->table_number,
                'seating_capacity' => $request->seating_capacity,
                'zone_id' => $request->zone_id,
                'initial_status' => strtolower($request->initial_status),
                'notes' => $request->notes,
            ]);

            DB::commit();
            return back()->with('success', 'Table added successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Creation Failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to add table! Please check logs.');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'table_number' => 'required|string|unique:tables,table_number,' . $id,
            'seating_capacity' => 'required|integer|min:1',
            'zone_id' => 'required|exists:zones,id',
            'initial_status' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $table = Table::findOrFail($id);
            $table->update([
                'table_number' => $request->table_number,
                'seating_capacity' => $request->seating_capacity,
                'zone_id' => $request->zone_id,
                'initial_status' => strtolower($request->initial_status),
                'notes' => $request->notes,
            ]);

            DB::commit();
            return back()->with('success', 'Table updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Update Failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update table!');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            Table::findOrFail($id)->delete();
            DB::commit();
            return back()->with('success', 'Table deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Deletion Failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete table!');
        }
    }
}
