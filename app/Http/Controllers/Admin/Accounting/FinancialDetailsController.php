<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Exports\AccountingFinancialReportExport;
use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingMoney;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/** Three report destinations with the same POSTED-only source-linked journal policy. */
final class FinancialDetailsController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.accounting.financial-details.index', $this->reportData($request, false));
    }

    public function excel(Request $request)
    {
        $data = $this->reportData($request, true);
        $exportRows = $this->exportRows($data);
        return Excel::download(
            new AccountingFinancialReportExport($exportRows, $data['type']),
            'accounting-' . $data['type'] . '-' . $data['to'] . '.xlsx'
        );
    }

    public function pdf(Request $request)
    {
        $data = $this->reportData($request, true);
        $exportRows = $this->exportRows($data);
        $pdfRows = array_map(static fn ($row) => array_map('strval', $row), $exportRows);
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
        $pdf->SetTitle('Accounting ' . ucwords(str_replace('-', ' ', $data['type'])));
        $pdf->SetFooter('POSTED source-linked journals only | Generated: ' . now()->format('d M Y H:i') . ' | Page {PAGENO}/{nbpg}');
        $pdf->WriteHTML(view('admin.accounting.financial-details.pdf', [
            'pdfRows' => $pdfRows, 'type' => $data['type'], 'from' => $data['from'],
            'to' => $data['to'],
        ])->render());
        $filename = 'accounting-' . $data['type'] . '-' . $data['to'] . '.pdf';
        return response($pdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function reportData(Request $request, bool $export): array
    {
        abort_unless(auth()->user()?->can('accounting-financial-reports'), 403);
        $data = $request->validate([
            'type' => 'nullable|in:customer-ledger,vendor-ledger,invoice-analysis',
            'party_id' => 'nullable|integer|min:1',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:100',
        ]);
        $type = $data['type'] ?? 'customer-ledger';
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        if ($from > $to || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 740) {
            throw ValidationException::withMessages(['dates' => 'Select up to 24 months in chronological order.']);
        }
        $partyId = (int) ($data['party_id'] ?? 0);
        $search = trim((string) ($data['search'] ?? ''));
        $isVendor = $type === 'vendor-ledger';
        $parties = $type === 'invoice-analysis' ? collect() : DB::table($isVendor ? 'vendors' : 'customers')
            ->select('id', 'name')->orderBy('name')->get();

        if ($export && $type !== 'invoice-analysis' && !$partyId) {
            throw ValidationException::withMessages(['party_id' => 'Select a customer/vendor before exporting their ledger.']);
        }

        $rows = null;
        $opening = 0;
        $ending = 0;
        if ($type === 'invoice-analysis') {
            $query = DB::table('accounting_events as e')
                ->join('journal_entries as j', 'j.accounting_event_id', '=', 'e.id')
                ->where('j.status', 'POSTED')->where('e.source_type', 'orders')
                ->whereIn('e.event_type', ['SALES_COMPLETED', 'SALES_CORRECTION'])
                ->whereBetween('j.entry_date', [$from, $to]);
            $this->applySearch($query, $search);
            $query->select('e.id', 'e.source_id', 'e.event_type', 'e.source_version', 'e.snapshot',
                'j.id as journal_id', 'j.number', 'j.entry_date')
                ->orderBy('j.entry_date')->orderBy('j.id');
            $rows = $this->fetchRows($query, $export, 25);
            $transform = static function ($r) {
                $s = json_decode((string) $r->snapshot, true) ?: [];
                $r->amounts = $s['source']['full_amounts'] ?? $s['amounts'] ?? [];
                $r->delta = $s['amounts'] ?? [];
                $r->order_number = $s['source']['order_number'] ?? ('Order #' . $r->source_id);
                $r->grand = $s['source']['saved_grand_total'] ?? ($s['source']['full_amounts']['saved_grand_total'] ?? null);
                return $r;
            };
            if ($export) $rows->transform($transform);
            else $rows->getCollection()->transform($transform);
        } elseif ($partyId) {
            if (!$parties->contains('id', $partyId)) {
                throw ValidationException::withMessages(['party_id' => 'Choose a valid customer/vendor.']);
            }
            $sourceIds = $isVendor
                ? ['purchases' => DB::table('purchases')->where('vendor_id', $partyId)->select('id'),
                   'vendor_payments' => DB::table('vendor_payments')->where('vendor_id', $partyId)->select('id')]
                : ['orders' => DB::table('orders')->where('customer_id', $partyId)->select('id'),
                   'order_due_payments' => DB::table('order_due_payments')->whereIn('order_id',
                       DB::table('orders')->where('customer_id', $partyId)->select('id'))->select('id')];
            $base = DB::table('journal_entries as j')
                ->join('accounting_events as e', 'e.id', '=', 'j.accounting_event_id')
                ->join('journal_lines as l', 'l.journal_entry_id', '=', 'j.id')
                ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
                ->where('j.status', 'POSTED')->where('a.code', $isVendor ? '2100' : '1200')
                ->where(function ($q) use ($sourceIds) {
                    foreach ($sourceIds as $source => $ids) {
                        $q->orWhere(function ($q2) use ($source, $ids) {
                            $q2->where('e.source_type', $source)->whereIn('e.source_id', $ids);
                        });
                    }
                })
                ->whereNotIn('j.id', DB::table('journal_entries')
                    ->where('status', 'POSTED')->whereNotNull('reversal_of_id')->select('reversal_of_id'));

            // The financial balance is not changed by a display-only text search.
            foreach ((clone $base)->where('j.entry_date', '<', $from)->get(['l.debit', 'l.credit']) as $r) {
                $opening += AccountingMoney::units((string) ($isVendor ? $r->credit : $r->debit))
                    - AccountingMoney::units((string) ($isVendor ? $r->debit : $r->credit));
            }
            $ending = $opening;
            foreach ((clone $base)->whereBetween('j.entry_date', [$from, $to])->get(['l.debit', 'l.credit']) as $r) {
                $ending += AccountingMoney::units((string) ($isVendor ? $r->credit : $r->debit))
                    - AccountingMoney::units((string) ($isVendor ? $r->debit : $r->credit));
            }
            $query = (clone $base)->whereBetween('j.entry_date', [$from, $to]);
            $this->applySearch($query, $search);
            $query->select('j.id as journal_id', 'j.number', 'j.entry_date', 'e.source_type', 'e.source_id',
                'e.event_type', 'l.debit', 'l.credit')
                ->orderBy('j.entry_date')->orderBy('j.id')->orderBy('l.id');
            $rows = $this->fetchRows($query, $export, 30);
        }
        $selectedParty = $type === 'invoice-analysis' ? null : $parties->firstWhere('id', $partyId);
        return compact('type', 'from', 'to', 'partyId', 'parties', 'selectedParty',
            'rows', 'opening', 'ending', 'isVendor', 'search');
    }

    private function applySearch($query, string $search): void
    {
        if ($search === '') return;
        $query->where(function ($q) use ($search) {
            $q->where('j.number', 'like', '%' . $search . '%')
                ->orWhere('e.event_type', 'like', '%' . $search . '%');
            if (ctype_digit($search)) $q->orWhere('e.source_id', (int) $search);
        });
    }

    private function fetchRows($query, bool $export, int $pageSize)
    {
        if (!$export) return $query->paginate($pageSize)->withQueryString();
        // Prevent an unbounded PDF/XLSX allocation on large POS datasets.
        $rows = $query->limit(5001)->get();
        if ($rows->count() > 5000) {
            throw ValidationException::withMessages(['export' => 'More than 5,000 records. Narrow the date/search filter before exporting.']);
        }
        return $rows;
    }

    private function exportRows(array $data): array
    {
        $type = $data['type'];
        $rows = [
            ['RESTAURANT ACCOUNTING — ' . strtoupper(str_replace('-', ' ', $type))],
            ['Reporting period', $data['from'] . ' to ' . $data['to'], 'BDT | POSTED source-linked journals only'],
            ['Search', $data['search'] ?: 'All matching records'],
            [],
        ];
        if ($type === 'invoice-analysis') {
            $rows[] = ['Date', 'Order', 'Event / Version', 'Sales', 'VAT', 'Discount', 'AR Due', 'Posted Journal'];
            foreach ($data['rows'] as $r) {
                $amounts = $r->amounts;
                $rows[] = [$r->entry_date, $r->order_number, $r->event_type . ' v' . $r->source_version,
                    $this->money($amounts['sales_gross'] ?? 0), $this->money($amounts['vat'] ?? 0),
                    $this->money($amounts['discount_total'] ?? 0), $this->money($amounts['ar_due'] ?? 0),
                    $r->number ?: '#' . $r->journal_id];
            }
        } else {
            $rows[] = ['Party', ($data['selectedParty']->name ?? '') . ' (#' . $data['partyId'] . ')'];
            $rows[] = ['Opening balance', $this->money($data['opening'] / 10000)];
            $rows[] = ['Date', 'Source', 'Transaction', 'Debit', 'Credit', 'Posted Journal'];
            foreach ($data['rows'] as $r) {
                $rows[] = [$r->entry_date, $r->source_type . ' #' . $r->source_id, $r->event_type,
                    $this->money($r->debit), $this->money($r->credit), $r->number ?: '#' . $r->journal_id];
            }
            $rows[] = ['Closing balance', $this->money($data['ending'] / 10000)];
        }
        // Stop spreadsheet formula injection from party names and dynamic references.
        return array_map(static fn (array $row) => array_map(static function ($value) {
            $value = (string) ($value ?? '');
            return preg_match('/^[=+@\t\r]/u', $value) || preg_match('/^-(?![0-9]+(?:\.[0-9]+)?$)/u', $value)
                ? "'" . $value : $value;
        }, $row), $rows);
    }

    private function money($value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
