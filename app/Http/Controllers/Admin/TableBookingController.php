<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TableBooking;
use App\Models\Table;
use App\Models\Customer;
use App\Models\Occasion;
use App\Models\Zone;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TableBookingController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today('Asia/Dhaka');
        $tomorrow = Carbon::tomorrow('Asia/Dhaka');

        // Stats (এগুলো স্ট্যাটিকালি লোড হবে)
        $todayBookings = TableBooking::whereDate('booking_date', $today)->count();
        // আগামীকালের বুকিং অথবা যাদের স্ট্যাটাস 'upcoming' তাদের কাউন্ট
        $upcomingBookings = TableBooking::whereDate('booking_date', $tomorrow)
                            ->orWhere('status', 'upcoming')
                            ->count();
        $confirmedBookings = TableBooking::where('status', 'confirmed')->count();
        $cancelledBookings = TableBooking::where('status', 'cancelled')->count();

        // Query for Table
        $query = TableBooking::with(['customer', 'table.zone', 'occasion']);

        // Search Logic
        if ($request->search) {
            $query->whereHas('customer', function($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('phone', 'like', '%'.$request->search.'%');
            })->orWhere('id', 'like', '%'.str_replace('#BK-', '', $request->search).'%');
        }

        // Filters
        if ($request->date) {
            if ($request->date == 'today') $query->whereDate('booking_date', $today);
            elseif ($request->date == 'tomorrow') $query->whereDate('booking_date', $tomorrow);
            elseif ($request->date == 'week') $query->whereBetween('booking_date', [now()->startOfWeek(), now()->endOfWeek()]);
            elseif ($request->date == 'month') $query->whereMonth('booking_date', now()->month)->whereYear('booking_date', now()->year); // This Month অ্যাড করা হলো
        }
        if ($request->occasion_id) $query->where('occasion_id', $request->occasion_id);
        if ($request->status) $query->where('status', $request->status);

        $bookings = $query->orderBy('booking_date', 'desc')->paginate(10);

        // Ajax Response
        if ($request->ajax()) {
            return view('admin.table_booking.booking_table', compact('bookings'))->render();
        }

        $customers = Customer::orderBy('name', 'asc')->get();
        $occasions = Occasion::where('status', 'Active')->get();
        $zonesWithTables = Zone::with('tables')->get();

        return view('admin.table_booking.index', compact(
            'bookings', 'customers', 'occasions', 'zonesWithTables',
            'todayBookings', 'upcomingBookings', 'confirmedBookings', 'cancelledBookings'
        ));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $occasions = Occasion::where('status','Active')->get();
        $zonesWithTables = Zone::with('tables')->get();
        return view('admin.table_booking.pages.create', compact('customers','occasions','zonesWithTables'));
    }

    public function edit($id)
    {
        $booking = TableBooking::with(['customer','table'])->findOrFail($id);
        $customers = Customer::orderBy('name')->get();
        $occasions = Occasion::where('status','Active')->get();
        $zonesWithTables = Zone::with('tables')->get();
        return view('admin.table_booking.pages.edit', compact('booking','customers','occasions','zonesWithTables'));
    }

    public function show($id)
    {
        $booking = TableBooking::with(['customer','table.zone','occasion'])->findOrFail($id);
        return view('admin.table_booking.pages.show', compact('booking'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'table_id' => 'required|exists:tables,id',
            'number_of_guests' => 'required|integer|min:1',
            'booking_date' => 'required|date',
            'booking_start_time' => 'required|date_format:H:i',
            'booking_end_time' => 'required|date_format:H:i|different:booking_start_time',
            'advance_amount' => 'nullable|numeric|min:0',
            'advance_payment_method' => 'nullable|in:Cash,Card,MFS',
            'advance_payment_reference' => 'nullable|string|max:255',
        ]);

        if (in_array($request->advance_payment_method, ['Card','MFS']) && empty($request->advance_payment_reference)) {
            return back()->withErrors(['advance_payment_reference' => 'Reference number is required for Bank / Card or MFS payment.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $customerId = null;

            // যদি নতুন কাস্টমার সিলেক্ট করে
            if ($request->is_new_customer == 1) {
                $request->validate([
                    'name' => 'required|string|max:255',
                    'phone' => 'required|string|max:20',
                ]);

                // ফোন নম্বর দিয়ে চেক করা, থাকলে সেটা নিবে, না থাকলে ক্রিয়েট করবে
                $customer = Customer::firstOrCreate(
                    ['phone' => $request->phone],
                    [
                        'name' => $request->name,
                        'email' => $request->email,
                        'points' => 0
                    ]
                );
                $customerId = $customer->id;
            } else {
                // পুরোনো কাস্টমার হলে সিলেক্ট করা আইডি নিবে
                $request->validate(['customer_id' => 'required|exists:customers,id']);
                $customerId = $request->customer_id;
            }

            // বুকিং সেভ করা
            $booking =TableBooking::create([
                'customer_id' => $customerId,
                'table_id' => $request->table_id,
                'is_new_customer' => $request->is_new_customer ? 1 : 0,
                'number_of_guests' => $request->number_of_guests,
                'booking_date' => $request->booking_date,
                // Keep booking_time synced for backward compatibility.
                'booking_time' => $request->booking_start_time,
                'booking_start_time' => $request->booking_start_time,
                'booking_end_time' => $request->booking_end_time,
                'occasion_id' => $request->occasion_id,
                'special_request' => $request->special_request,
                'advance_amount' => $request->advance_amount ?? 0,
                'advance_payment_method' => $request->advance_payment_method,
                'advance_payment_reference' => $request->advance_payment_reference,
                'status' => $request->status ?? 'upcoming',
            ]);

            // ডাইনামিক বুকিং আইডি সেভ করা
            $booking->update([
                'booking_id' => '#BK-' . (1000 + $booking->id)
            ]);

            DB::commit();
            return back()->with('success', 'Booking created successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Booking Create Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to create booking! Please check logs.');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'table_id' => 'required|exists:tables,id',
            'number_of_guests' => 'required|integer|min:1',
            'booking_date' => 'required|date',
            'booking_start_time' => 'required|date_format:H:i',
            'booking_end_time' => 'required|date_format:H:i|different:booking_start_time',
            'advance_amount' => 'nullable|numeric|min:0',
            'advance_payment_method' => 'nullable|in:Cash,Card,MFS',
            'advance_payment_reference' => 'nullable|string|max:255',
        ]);

        if (in_array($request->advance_payment_method, ['Card','MFS']) && empty($request->advance_payment_reference)) {
            return back()->withErrors(['advance_payment_reference' => 'Reference number is required for Bank / Card or MFS payment.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $booking = TableBooking::findOrFail($id);
            $customerId = $booking->customer_id;

            // এডিট করার সময়ও যদি নতুন কাস্টমার হিসেবে ডাটা দেয়
            if ($request->is_new_customer == 1) {
                $customer = Customer::firstOrCreate(
                    ['phone' => $request->phone],
                    ['name' => $request->name, 'email' => $request->email, 'points' => 0]
                );
                $customerId = $customer->id;
            } elseif ($request->has('customer_id') && $request->customer_id != null) {
                $customerId = $request->customer_id;
            }

            $booking->update([
                'customer_id' => $customerId,
                'table_id' => $request->table_id,
                'is_new_customer' => $request->is_new_customer ? 1 : 0,
                'number_of_guests' => $request->number_of_guests,
                'booking_date' => $request->booking_date,
                // Keep booking_time synced for backward compatibility.
                'booking_time' => $request->booking_start_time,
                'booking_start_time' => $request->booking_start_time,
                'booking_end_time' => $request->booking_end_time,
                'occasion_id' => $request->occasion_id,
                'special_request' => $request->special_request,
                'advance_amount' => $request->advance_amount ?? 0,
                'advance_payment_method' => $request->advance_payment_method,
                'advance_payment_reference' => $request->advance_payment_reference,
                'status' => $request->status,
            ]);

            $this->releaseTableIfCompletedOrCancelled($booking->fresh());

            DB::commit();
            return back()->with('success', 'Booking updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Booking Update Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update booking!');
        }
    }

    private function releaseTableIfCompletedOrCancelled($booking)
    {
        if (in_array(strtolower((string) $booking->status), ['completed', 'cancelled'])) {
            Table::where('id', $booking->table_id)->update(['initial_status' => 'Available']);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            TableBooking::findOrFail($id)->delete();
            DB::commit();
            return back()->with('success', 'Booking deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Booking Delete Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete booking!');
        }
    }
}
