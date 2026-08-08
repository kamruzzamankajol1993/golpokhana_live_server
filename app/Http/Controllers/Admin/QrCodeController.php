<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Zone;
use App\Models\Table;
use App\Models\RestaurantSetting;
use Mpdf\Mpdf;

class QrCodeController extends Controller
{
    // মেইন পেজ লোড করা
    public function index()
    {
        $zones = Zone::all();
        $restaurant = RestaurantSetting::first();
        return view('admin.qrcode.index', compact('zones', 'restaurant'));
    }

    // AJAX এর মাধ্যমে জোন অনুযায়ী টেবিল আনা
    public function getTables($zone_id)
    {
        $tables = Table::where('zone_id', $zone_id)->orderBy('table_number', 'asc')->get();
        return response()->json(['status' => 'success', 'tables' => $tables]);
    }

    // PDF জেনারেট করা
    public function generatePdf(Request $request)
    {
        $request->validate([
            'table_ids' => 'required|array|min:1'
        ], [
            'table_ids.required' => 'Please select at least one table to generate QR codes.'
        ]);

        $tables = Table::whereIn('id', $request->table_ids)->with('zone')->get();
        $restaurant = RestaurantSetting::first();

        // ওয়েবসাইটের লিংক রেডি করা (শেষে স্লাশ থাকলে রিমুভ করে দেওয়া)
        $baseUrl = rtrim($restaurant->website ?? url('/'), '/');

        // পিডিএফ ভিউ রেন্ডার করা
        $html = view('admin.qrcode.pdf', compact('tables', 'restaurant', 'baseUrl'))->render();

        // mPDF সেটআপ
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12
        ]);

        $mpdf->SetTitle('Table QR Codes');
        $mpdf->WriteHTML($html);

        $fileName = 'Table_QRCodes_' . now()->format('Y_m_d') . '.pdf';

        // নতুন ট্যাবে পিডিএফ ওপেন করানো
        $pdfContent = $mpdf->Output($fileName, 'S');
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"'
        ]);
    }
}
