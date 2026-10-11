<?php
namespace App\Http\Controllers\Admin\Accounting\FinancialReports;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingReportService,AccountingMoney};
use Carbon\Carbon;
use App\Exports\AccountingFinancialReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** FinancialReports accounting module. A shared base provides posting dependencies and invariant checks. */
final class FinancialReportsController extends \App\Http\Controllers\Admin\Accounting\Shared\AccountingReportsController
{
    public function index(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->access();
        [$type,$from,$to,$account]=$this->params($r);
        $report=$this->build($type,$from,$to,$account);
        $accounts=DB::table('chart_of_accounts')->where('is_header',false)->orderBy('code')->get();
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        $cutover=DB::table('accounting_cutovers')->where('status','ACTIVE')->first();
        $exceptions=DB::table('accounting_events')->where('status','!=','POSTED')->count();
        return view('admin.accounting.financial-reports.index',compact('type','from','to','account','report','accounts','settings','cutover','exceptions'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    /** Dedicated report URLs — all preserve the shared POSTED-only reporting service. */
    private function reportOfType(Request $r, string $type)
    {
        $r->merge(['type' => $type]);
        return $this->index($r);
    }

    public function balanceSheet(Request $r) {
        try {
            // Preserve the original accounting validation, permission and transaction flow.
 return $this->reportOfType($r, 'balance-sheet'); 
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function profitLoss(Request $r) {
        try {
            // Preserve the original accounting validation, permission and transaction flow.
 return $this->reportOfType($r, 'profit-loss'); 
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function generalLedger(Request $r) {
        try {
            // Preserve the original accounting validation, permission and transaction flow.
 return $this->reportOfType($r, 'general-ledger'); 
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function cashFlow(Request $r) {
        try {
            // Preserve the original accounting validation, permission and transaction flow.
 return $this->reportOfType($r, 'cash-flow'); 
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function trialBalance(Request $r) {
        try {
            // Preserve the original accounting validation, permission and transaction flow.
 return $this->reportOfType($r, 'trial-balance'); 
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function taxActivity(Request $r) {
        try {
            // Preserve the original accounting validation, permission and transaction flow.
 return $this->reportOfType($r, 'tax'); 
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function agingReport(Request $r) {
        try {
            // Preserve the original accounting validation, permission and transaction flow.
 return $this->reportOfType($r, 'aging'); 
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function pdf(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->access();
        [$type, $from, $to, $account] = $this->params($r);
        $report = $this->build($type, $from, $to, $account);
        $rows = $this->exportRows($type, $from, $to, $report);
        $filename = 'accounting-' . $type . '-' . $to . '.pdf';
        $tempDir = storage_path('app/mpdf-accounting');
        if (!is_dir($tempDir) && !@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            abort(500, 'Unable to prepare PDF temporary storage.');
        }
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => $tempDir,
            'margin_left' => 11, 'margin_right' => 11,
            'margin_top' => 15, 'margin_bottom' => 16,
            'default_font' => 'dejavusans',
        ]);
        $pdf->SetTitle('Accounting ' . ucwords(str_replace('-', ' ', $type)));
        $pdf->SetAuthor('Restaurant Accounting');
        $pdf->SetFooter('POSTED GL only  |  Generated: '. now()->format('d M Y H:i') . '  |  Page {PAGENO}/{nbpg}');
        $pdf->WriteHTML(view('admin.accounting.financial-reports.pdf', compact('rows', 'type', 'from', 'to'))->render());
        return response($pdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function export(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->access();
        [$type, $from, $to, $account] = $this->params($r);
        $report = $this->build($type, $from, $to, $account);
        $rows = $this->exportRows($type, $from, $to, $report);
        return Excel::download(new AccountingFinancialReportExport($rows, $type),
            'accounting-' . $type . '-' . $to . '.xlsx');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    private function exportRows(string $type, string $from, string $to, array $report): array
    {
        $data = [
            ['RESTAURANT ACCOUNTING — '.strtoupper(str_replace('-', ' ', $type))],
            ['Reporting period', $from.' to '.$to, 'Currency: BDT / 4 decimal places'],
            ['POSTED entries only; pending and draft journals are excluded.'],
            [],
        ];
        if ($type === 'profit-loss') {
            foreach ($report['data'] as $section => $items) {
                $data[] = [strtoupper(str_replace('_', ' ', $section)), 'Code', 'Account', 'Amount (BDT)'];
                foreach ($items as $row) $data[] = ['', $row['code'], $row['name'], $this->service->fmt($row['amount'])];
                $data[] = [ucwords(str_replace('_',' ',$section)).' TOTAL', '', '', $this->service->fmt($report['sums'][$section])];
            }
            foreach (['netSales'=>'Net sales', 'gross'=>'Gross profit', 'profit'=>'Net profit / (loss)'] as $k=>$label)
                $data[] = [$label,'','',$this->service->fmt($report[$k])];
        } elseif ($type === 'balance-sheet') {
            foreach ($report['groups'] as $section => $items) {
                $data[] = [strtoupper($section), 'Code', 'Account', 'Amount (BDT)'];
                foreach ($items as $row) $data[] = ['', $row['code'], $row['name'], $this->service->fmt($row['amount'])];
                $data[] = [ucwords($section).' TOTAL', '', '', $this->service->fmt($report['sum'][$section])];
            }
            $data[] = ['Untransferred earnings','','',$this->service->fmt($report['earnings'])];
            $data[] = ['Liabilities + equity + earnings','','',$this->service->fmt($report['liabilitiesAndEquity'])];
            $data[] = ['Balance Sheet difference','','',$this->service->fmt($report['difference'])];
        } elseif ($type === 'trial-balance') {
            $data[] = ['Code','Account','Debit balance','Credit balance'];
            foreach ($report['rows'] as $row) $data[] = [$row['code'],$row['name'],$this->service->fmt($row['debit_balance']),$this->service->fmt($row['credit_balance'])];
            $data[] = ['TOTAL','',$this->service->fmt($report['debits']),$this->service->fmt($report['credits'])];
        } elseif ($type === 'cash-flow') {
            $data[] = ['Date','Journal','Activity','Cash change (BDT)','Memo'];
            foreach ($report['rows'] as $row) $data[] = [$row['date'],$row['number'],$row['activity'],$this->service->fmt($row['amount']),$row['narration']];
            foreach (['opening'=>'Opening cash/bank', 'movement'=>'Net cash movement', 'closing'=>'Closing cash/bank'] as $k=>$label)
                $data[] = [$label,'','',$this->service->fmt($report[$k])];
            $data[] = ['Cash flow review warning',$report['warning']];
        } elseif ($type === 'tax') {
            $data[] = ['Tax type','Account','Debit','Credit','Liability movement'];
            foreach ($report['rows'] as $row) $data[] = [$row['type'],$row['code'].' - '.$row['name'],$this->service->fmt($row['debit']),$this->service->fmt($row['credit']),$this->service->fmt($row['movement'])];
            foreach ($report['withholdings'] as $row) $data[] = ['Posted withholding '.$row->tax_type,'Count '.$row->count,$row->amount];
        } elseif ($type === 'aging') {
            foreach (['AR','AP'] as $side) {
                $data[] = [$side,'Source ID','Party ID','Due date','Outstanding','Aging bucket'];
                foreach ($report[$side]['rows'] as $row)
                    $data[] = ['', $row['source_id'], $row['party_id'], $row['due'], $this->service->fmt($row['outstanding']), $row['bucket']];
                $data[] = [$side.' TOTAL','','','',$this->service->fmt($report[$side]['total'])];
            }
        } elseif ($type === 'general-ledger') {
            $data[] = ['Date','Journal','Memo','Debit','Credit','Running D-C'];
            if ($report['account']) {
                $data[] = ['Account: '.$report['account']->code.' - '.$report['account']->name];
                $data[] = ['Opening debit-minus-credit','','','','',$this->service->fmt($report['opening'])];
                foreach ($report['rows'] as $row)
                    $data[] = [$row['date'],$row['number'],$row['narration'],$this->service->fmt($row['dr']),$this->service->fmt($row['cr']),$this->service->fmt($row['running'])];
                $data[] = ['Ending debit-minus-credit','','','','',$this->service->fmt($report['running'])];
            } else {
                $data[] = ['Select a GL account before exporting ledger detail.'];
            }
        }
        // Escape user-controlled text so Excel cannot interpret a name/reference as a formula.
        return array_map(static fn (array $row) => array_map(static function ($v) {
            $v = (string) ($v ?? '');
            return preg_match('/^[=+@\t\r]/u', $v) || preg_match('/^-(?![0-9]+(?:\.[0-9]+)?$)/u', $v)
                ? "'" . $v : $v;
        }, $row), $data);
    }
}
