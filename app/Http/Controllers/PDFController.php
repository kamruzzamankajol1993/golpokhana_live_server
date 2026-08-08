<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mpdf\Mpdf;
use Intervention\Image\Laravel\Facades\Image;
use App\Exports\TestExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Response;
class PDFController extends Controller
{

// Controller Method
public function testImage()
{
    $imagePath = public_path('test.jpg');

    if (!file_exists($imagePath)) {
        return "Public folder-e 'test.jpg' name-e ekta image rakhun.";
    }

    // Image read ebong edit kora
    $img = Image::read($imagePath);
    $img->resize(300, 200);
    $img->greyscale();

    // v3-te response() er bodole encode() kore Laravel Response diye return korte hoy
    $encoded = $img->encodeByExtension('jpg');

    return Response::make($encoded, 200, [
        'Content-Type' => 'image/jpeg',
    ]);
}

public function testExcel()
{
    return Excel::download(new TestExport, 'test-report.xlsx');
}
    public function generatePDF()
    {
        $mpdf = new Mpdf();

        // PDF Content
        $html = '
            <div style="text-align: center; font-family: sans-serif;">
                <h1 style="color: #333;">Laravel mPDF Test</h1>
                <p>Congratulations! mPDF is working perfectly in your project.</p>
                <p>Current Date: ' . date('d-m-Y H:i:s') . '</p>
            </div>
        ';

        $mpdf->WriteHTML($html);

        // Browser-e open korar jonno 'I', download korar jonno 'D'
        return $mpdf->Output('test-file.pdf', 'I');
    }
}
