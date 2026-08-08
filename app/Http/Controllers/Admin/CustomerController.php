<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\PointHistory;
use Exception;
use App\Exports\CustomersExport;
use Maatwebsite\Excel\Facades\Excel;

// সরাসরি mPDF ক্লাস ইমপোর্ট করুন
use Mpdf\Mpdf;
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $totalCustomers = Customer::count();
        $loyaltyMembers = Customer::where('points', '>', 5)->count();

        // ডাইনামিক রিপিট ভিজিটর: যাদের ১ এর বেশি অর্ডার আছে
        $repeatVisitors = Customer::has('orders', '>', 1)->count();

        $totalPointsIssued = Customer::sum('points');

        // Ajax Request for Table
        if ($request->ajax()) {
            // withCount('orders') দিয়ে টোটাল অর্ডারের কাউন্ট আনা হচ্ছে
            $query = Customer::withCount('orders')->orderBy('id', 'desc');

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->search.'%')
                      ->orWhere('phone', 'like', '%'.$request->search.'%')
                      ->orWhere('email', 'like', '%'.$request->search.'%');
                });
            }

            if ($request->filter == 'Loyalty Members') {
                $query->where('points', '>', 5);
            } elseif ($request->filter == 'New (30 days)') {
                $query->where('created_at', '>=', now()->subDays(30));
            }

            $customers = $query->paginate(10);
            return view('admin.customer.table', compact('customers'))->render();
        }

        return view('admin.customer.index', compact(
            'totalCustomers', 'loyaltyMembers', 'repeatVisitors', 'totalPointsIssued'
        ));
    }

    // নতুন মেথড: কাস্টমার হিস্ট্রি লোড করার জন্য
    public function history($id)
    {
        $customer = Customer::with(['orders' => function($q) {
            $q->orderBy('id', 'desc');
        }])->findOrFail($id);

        $totalOrders = $customer->orders->count();
        $totalSpent = $customer->orders->sum('grand_total');

        return view('admin.customer.modals.history_content', compact('customer', 'totalOrders', 'totalSpent'))->render();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        try {
            Customer::create([
                'name'         => $request->name,
                'phone'        => $request->phone,
                'email'        => $request->email,
                'dob'          => $request->dob,
                'address'      => $request->address,
                'points'       => $request->points ?? 0,
                'total_orders' => 0, // অর্ডার টেবিল না হওয়া পর্যন্ত ০ থাকবে
            ]);

            if ($request->points > 0) {
        PointHistory::create([
            'customer_id' => $customer->id,
            'point'       => $request->points,
            'note'        => 'Initial Points',
        ]);
    }

            return back()->with('success', 'Customer added successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to add customer: ' . $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        try {
            $customer = Customer::findOrFail($id);

    // যদি পয়েন্ট কমানো বা বাড়ানো হয়
    if ($request->point_adjustment != 0) {
        PointHistory::create([
            'customer_id' => $customer->id,
            'point'       => $request->point_adjustment,
            'note'        => $request->point_note ?? 'Manual Adjustment',
        ]);

        // কাস্টমার টেবিলের পয়েন্ট আপডেট (সব হিস্ট্রির যোগফল)
        $customer->points = $customer->pointHistories()->sum('point');
    }
            $customer->update([
                'name'    => $request->name,
                'phone'   => $request->phone,
                'email'   => $request->email,
                'dob'     => $request->dob,
                'address' => $request->address,
            ]);

            return back()->with('success', 'Customer updated successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to update customer!');
        }
    }

    public function destroy($id)
    {
        try {
            Customer::findOrFail($id)->delete();
            return back()->with('success', 'Customer deleted successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete customer!');
        }
    }

    // PDF Download (Direct mPDF)
    public function exportPDF()
    {
        $customers = Customer::all();
        $title = 'Customer List';

        // ব্লেড ফাইলকে HTML স্ট্রিং এ কনভার্ট করা
        $html = view('admin.customer.pdf_template', compact('customers', 'title'))->render();

        try {
            // mPDF এর অবজেক্ট তৈরি
            $mpdf = new Mpdf([
                'default_font' => 'sans-serif',
                'format' => 'A4'
            ]);

            // HTML রাইট করা
            $mpdf->WriteHTML($html);

            // 'D' মানে হলো সরাসরি ডাউনলোড হওয়া। ব্রাউজারে ভিউ করতে চাইলে 'I' ব্যবহার করতে পারেন।
            return $mpdf->Output('customer-list.pdf', 'I');

        } catch (\Mpdf\MpdfException $e) {
            return back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    // Excel Download (Laravel Excel)
    public function exportExcel()
    {
        return Excel::download(new CustomersExport, 'customer-list.xlsx');
    }
}
