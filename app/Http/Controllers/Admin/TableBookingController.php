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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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
        $query = TableBooking::with(['customer', 'table.zone', 'tables', 'occasion']);

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
        $booking = TableBooking::with(['customer','table','tables'])->findOrFail($id);
        $customers = Customer::orderBy('name')->get();
        $occasions = Occasion::where('status','Active')->get();
        $zonesWithTables = Zone::with('tables')->get();
        return view('admin.table_booking.pages.edit', compact('booking','customers','occasions','zonesWithTables'));
    }

    public function show($id)
    {
        $booking = TableBooking::with(['customer','table.zone','tables','occasion'])->findOrFail($id);
        return view('admin.table_booking.pages.show', compact('booking'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'table_id' => 'nullable|required_without:table_ids|exists:tables,id',
            'table_ids' => 'nullable|array|min:1',
            'table_ids.*' => 'integer|exists:tables,id',
            'number_of_guests' => 'required|integer|min:1',
            'booking_date' => 'required|date',
            'booking_start_time' => 'required|date_format:H:i',
            'booking_end_time' => 'required|date_format:H:i|different:booking_start_time',
            'advance_amount' => 'nullable|numeric|min:0',
            'advance_payment_method' => 'nullable|in:Cash,Card,MFS,Split',
            'advance_payment_reference' => 'nullable|string|max:255',
            'advance_card_provider' => 'nullable|string|max:100',
            'advance_mfs_provider' => 'nullable|string|max:100',
            'advance_paid_in_cash' => 'nullable|numeric|min:0',
            'advance_paid_in_card' => 'nullable|numeric|min:0',
            'advance_paid_in_mfs' => 'nullable|numeric|min:0',
            'advance_split_card_reference' => 'nullable|string|max:255',
            'advance_split_mfs_reference' => 'nullable|string|max:255',
        ]);

        $paymentData = $this->validatedAdvancePayment($request);

        DB::beginTransaction();
        try {
            $customerId = null;

            // যদি নতুন কাস্টমার সিলেক্ট করে
            if ($request->is_new_customer == 1) {
                $request->validate([
                    'name' => 'required|string|max:255',
                    'phone' => 'required|string|max:20',
                    'address' => 'nullable|string|max:1000',
                ]);

                // ফোন নম্বর দিয়ে চেক করা, থাকলে সেটা নিবে, না থাকলে ক্রিয়েট করবে
                $customer = Customer::firstOrCreate(
                    ['phone' => $request->phone],
                    [
                        'name' => $request->name,
                        'email' => $request->email,
                        'address' => $request->address,
                        'points' => 0
                    ]
                );
                $customerId = $customer->id;
            } else {
                // পুরোনো কাস্টমার হলে সিলেক্ট করা আইডি নিবে
                $request->validate(['customer_id' => 'required|exists:customers,id']);
                $customerId = $request->customer_id;
            }

            $tableIds = collect($request->input('table_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
            if ($tableIds->isEmpty() && $request->table_id) $tableIds->push((int) $request->table_id);
            $primaryTableId = (int) $tableIds->first();

            // বুকিং সেভ করা
            $booking =TableBooking::create([
                'customer_id' => $customerId,
                'table_id' => $primaryTableId,
                'is_new_customer' => $request->is_new_customer ? 1 : 0,
                'number_of_guests' => $request->number_of_guests,
                'booking_date' => $request->booking_date,
                // Keep booking_time synced for backward compatibility.
                'booking_time' => $request->booking_start_time,
                'booking_start_time' => $request->booking_start_time,
                'booking_end_time' => $request->booking_end_time,
                'occasion_id' => $request->occasion_id,
                'special_request' => $request->special_request,
                'advance_amount' => $paymentData['advance_amount'],
                'advance_payment_method' => $paymentData['advance_payment_method'],
                'advance_payment_reference' => $paymentData['advance_payment_reference'],
                'advance_card_provider' => $paymentData['advance_card_provider'],
                'advance_mfs_provider' => $paymentData['advance_mfs_provider'],
                'advance_paid_in_cash' => $paymentData['advance_paid_in_cash'],
                'advance_paid_in_card' => $paymentData['advance_paid_in_card'],
                'advance_paid_in_mfs' => $paymentData['advance_paid_in_mfs'],
                'advance_split_card_reference' => $paymentData['advance_split_card_reference'],
                'advance_split_mfs_reference' => $paymentData['advance_split_mfs_reference'],
                'status' => $request->status ?? 'upcoming',
            ]);

            // ডাইনামিক বুকিং আইডি সেভ করা
            $booking->update([
                'booking_id' => '#BK-' . (1000 + $booking->id)
            ]);
            if (Schema::hasTable('table_booking_tables')) {
                $booking->tables()->sync($tableIds->all());
            }

            DB::commit();
            return back()->with('success', 'Booking created successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Booking Create Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to create booking! Please check logs.');
        }
    }

    public function createCustomerAjax(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable|string|max:1000',
        ]);

        $customer = Customer::firstOrCreate(
            ['phone' => $request->phone],
            [
                'name' => $request->name,
                'email' => $request->email,
                'address' => $request->address,
                'points' => 0
            ]
        );

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'text' => $customer->name.' ('.$customer->phone.')'
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'table_id' => 'nullable|required_without:table_ids|exists:tables,id',
            'table_ids' => 'nullable|array|min:1',
            'table_ids.*' => 'integer|exists:tables,id',
            'number_of_guests' => 'required|integer|min:1',
            'booking_date' => 'required|date',
            'booking_start_time' => 'required|date_format:H:i',
            'booking_end_time' => 'required|date_format:H:i|different:booking_start_time',
            'advance_amount' => 'nullable|numeric|min:0',
            'advance_payment_method' => 'nullable|in:Cash,Card,MFS,Split',
            'advance_payment_reference' => 'nullable|string|max:255',
            'advance_card_provider' => 'nullable|string|max:100',
            'advance_mfs_provider' => 'nullable|string|max:100',
            'advance_paid_in_cash' => 'nullable|numeric|min:0',
            'advance_paid_in_card' => 'nullable|numeric|min:0',
            'advance_paid_in_mfs' => 'nullable|numeric|min:0',
            'advance_split_card_reference' => 'nullable|string|max:255',
            'advance_split_mfs_reference' => 'nullable|string|max:255',
        ]);

        $booking = TableBooking::findOrFail($id);
        $paymentKeys = [
            'advance_amount',
            'advance_payment_method',
            'advance_payment_reference',
            'advance_card_provider',
            'advance_mfs_provider',
            'advance_paid_in_cash',
            'advance_paid_in_card',
            'advance_paid_in_mfs',
            'advance_split_card_reference',
            'advance_split_mfs_reference',
        ];

        // Status-only actions (for example Complete/Cancel) do not submit the
        // payment form. Preserve the existing advance payment data in that case.
        $paymentData = $request->hasAny($paymentKeys)
            ? $this->validatedAdvancePayment($request)
            : [
                'advance_amount' => (float) ($booking->advance_amount ?? 0),
                'advance_payment_method' => $booking->advance_payment_method,
                'advance_payment_reference' => $booking->advance_payment_reference,
                'advance_card_provider' => $booking->advance_card_provider,
                'advance_mfs_provider' => $booking->advance_mfs_provider,
                'advance_paid_in_cash' => (float) ($booking->advance_paid_in_cash ?? 0),
                'advance_paid_in_card' => (float) ($booking->advance_paid_in_card ?? 0),
                'advance_paid_in_mfs' => (float) ($booking->advance_paid_in_mfs ?? 0),
                'advance_split_card_reference' => $booking->advance_split_card_reference,
                'advance_split_mfs_reference' => $booking->advance_split_mfs_reference,
            ];

        DB::beginTransaction();
        try {
            $customerId = $booking->customer_id;

            // এডিট করার সময়ও যদি নতুন কাস্টমার হিসেবে ডাটা দেয়
            if ($request->is_new_customer == 1) {
                $request->validate([
                    'name' => 'required|string|max:255',
                    'phone' => 'required|string|max:20',
                    'email' => 'nullable|email',
                    'address' => 'nullable|string|max:1000',
                ]);

                $customer = Customer::firstOrCreate(
                    ['phone' => $request->phone],
                    ['name' => $request->name, 'email' => $request->email,
                        'address' => $request->address, 'points' => 0]
                );
                $customerId = $customer->id;
            } elseif ($request->has('customer_id') && $request->customer_id != null) {
                $customerId = $request->customer_id;
            }

            $tableIds = collect($request->input('table_ids', []))->map(fn ($tableId) => (int) $tableId)->filter()->unique()->values();
            if ($tableIds->isEmpty() && $request->table_id) $tableIds->push((int) $request->table_id);
            $primaryTableId = (int) $tableIds->first();

            $booking->update([
                'customer_id' => $customerId,
                'table_id' => $primaryTableId,
                'is_new_customer' => $request->is_new_customer ? 1 : 0,
                'number_of_guests' => $request->number_of_guests,
                'booking_date' => $request->booking_date,
                // Keep booking_time synced for backward compatibility.
                'booking_time' => $request->booking_start_time,
                'booking_start_time' => $request->booking_start_time,
                'booking_end_time' => $request->booking_end_time,
                'occasion_id' => $request->occasion_id,
                'special_request' => $request->special_request,
                'advance_amount' => $paymentData['advance_amount'],
                'advance_payment_method' => $paymentData['advance_payment_method'],
                'advance_payment_reference' => $paymentData['advance_payment_reference'],
                'advance_card_provider' => $paymentData['advance_card_provider'],
                'advance_mfs_provider' => $paymentData['advance_mfs_provider'],
                'advance_paid_in_cash' => $paymentData['advance_paid_in_cash'],
                'advance_paid_in_card' => $paymentData['advance_paid_in_card'],
                'advance_paid_in_mfs' => $paymentData['advance_paid_in_mfs'],
                'advance_split_card_reference' => $paymentData['advance_split_card_reference'],
                'advance_split_mfs_reference' => $paymentData['advance_split_mfs_reference'],
                'status' => $request->status,
            ]);

            if (Schema::hasTable('table_booking_tables')) {
                $booking->tables()->sync($tableIds->all());
            }
            $this->releaseTableIfCompletedOrCancelled($booking->fresh('tables'));

            DB::commit();
            return back()->with('success', 'Booking updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Booking Update Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update booking!');
        }
    }

    private function validatedAdvancePayment(Request $request): array
    {
        $method = $request->filled('advance_payment_method') ? (string) $request->advance_payment_method : null;
        $advanceAmount = max(0, round((float) ($request->advance_amount ?? 0), 2));
        $cash = max(0, round((float) ($request->advance_paid_in_cash ?? 0), 2));
        $card = max(0, round((float) ($request->advance_paid_in_card ?? 0), 2));
        $mfs = max(0, round((float) ($request->advance_paid_in_mfs ?? 0), 2));
        $cardProvider = trim((string) ($request->advance_card_provider ?? ''));
        $mfsProvider = trim((string) ($request->advance_mfs_provider ?? ''));
        $normalReference = trim((string) ($request->advance_payment_reference ?? ''));
        $splitCardReference = trim((string) ($request->advance_split_card_reference ?? ''));
        $splitMfsReference = trim((string) ($request->advance_split_mfs_reference ?? ''));

        if ($advanceAmount > 0 && !$method) {
            throw ValidationException::withMessages(['advance_payment_method' => 'Payment method is required when an advance amount is entered.']);
        }

        if ($method === 'Card') {
            if ($cardProvider === '') {
                throw ValidationException::withMessages(['advance_card_provider' => 'Card provider is required.']);
            }
            if ($normalReference === '') {
                throw ValidationException::withMessages(['advance_payment_reference' => 'Bank / Card reference number is required.']);
            }
        }

        if ($method === 'MFS') {
            if ($mfsProvider === '') {
                throw ValidationException::withMessages(['advance_mfs_provider' => 'MFS provider is required.']);
            }
            if ($normalReference === '') {
                throw ValidationException::withMessages(['advance_payment_reference' => 'MFS reference number is required.']);
            }
        }

        if ($method === 'Split') {
            $splitTotal = round($cash + $card + $mfs, 2);
            $usedMethods = collect([$cash, $card, $mfs])->filter(fn ($amount) => $amount > 0)->count();

            if ($usedMethods < 2) {
                throw ValidationException::withMessages(['advance_payment_method' => 'Split payment requires at least two payment methods.']);
            }
            if (abs($splitTotal - $advanceAmount) > 0.01) {
                throw ValidationException::withMessages(['advance_amount' => 'Split payment amounts must equal the advance amount.']);
            }
            if ($card > 0 && $cardProvider === '') {
                throw ValidationException::withMessages(['advance_card_provider' => 'Card provider is required for the split card amount.']);
            }
            if ($card > 0 && $splitCardReference === '') {
                throw ValidationException::withMessages(['advance_split_card_reference' => 'Card reference number is required for the split card amount.']);
            }
            if ($mfs > 0 && $mfsProvider === '') {
                throw ValidationException::withMessages(['advance_mfs_provider' => 'MFS provider is required for the split MFS amount.']);
            }
            if ($mfs > 0 && $splitMfsReference === '') {
                throw ValidationException::withMessages(['advance_split_mfs_reference' => 'MFS reference number is required for the split MFS amount.']);
            }
        } else {
            $cash = $method === 'Cash' ? $advanceAmount : 0;
            $card = $method === 'Card' ? $advanceAmount : 0;
            $mfs = $method === 'MFS' ? $advanceAmount : 0;
            $splitCardReference = '';
            $splitMfsReference = '';
        }

        if ($method !== 'Card' && !($method === 'Split' && $card > 0)) {
            $cardProvider = '';
        }
        if ($method !== 'MFS' && !($method === 'Split' && $mfs > 0)) {
            $mfsProvider = '';
        }
        if (!in_array($method, ['Card', 'MFS'], true)) {
            $normalReference = '';
        }

        return [
            'advance_amount' => $advanceAmount,
            'advance_payment_method' => $method,
            'advance_payment_reference' => $normalReference !== '' ? $normalReference : null,
            'advance_card_provider' => $cardProvider !== '' ? $cardProvider : null,
            'advance_mfs_provider' => $mfsProvider !== '' ? $mfsProvider : null,
            'advance_paid_in_cash' => $cash,
            'advance_paid_in_card' => $card,
            'advance_paid_in_mfs' => $mfs,
            'advance_split_card_reference' => $splitCardReference !== '' ? $splitCardReference : null,
            'advance_split_mfs_reference' => $splitMfsReference !== '' ? $splitMfsReference : null,
        ];
    }

    private function releaseTableIfCompletedOrCancelled($booking)
    {
        if (in_array(strtolower((string) $booking->status), ['completed', 'cancelled', 'no-show'])) {
            $tableIds = collect([$booking->table_id]);
            if (Schema::hasTable('table_booking_tables')) {
                $tableIds = $tableIds->merge($booking->tables->pluck('id'));
            }
            Table::whereIn('id', $tableIds->filter()->unique()->all())->update(['initial_status' => 'Available']);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $booking = TableBooking::with('tables')->findOrFail($id);
            $tableIds = $booking->tables->pluck('id')->push($booking->table_id)->filter()->unique()->values()->all();
            $booking->delete();
            foreach ($tableIds as $tableId) {
                $hasActiveOrder = \App\Models\Order::where('table_id', $tableId)
                    ->whereIn('status', ['Pending', 'Waiter_Hold', 'QR_Pending', 'QR_Hold', 'Cooking', 'Ready'])
                    ->exists();
                if (!$hasActiveOrder) {
                    Table::whereKey($tableId)->update(['initial_status' => 'Available']);
                }
            }
            DB::commit();
            return back()->with('success', 'Booking deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Table Booking Delete Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete booking!');
        }
    }
}
