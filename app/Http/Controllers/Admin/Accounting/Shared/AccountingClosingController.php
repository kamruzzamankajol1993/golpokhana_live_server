<?php
namespace App\Http\Controllers\Admin\Accounting\Shared;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingCloseService,AccountingMoney,AccountingReportService,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

abstract class AccountingClosingController extends Controller
{
    use LogsAccountingExceptions;

    public function __construct(protected AccountingCloseService $closing, protected AccountingReportService $reports,
        protected JournalPostingService $posting) {}

    protected function can(string $p): void { abort_unless(auth()->user()?->can($p),403); }
    protected function units(mixed $s): int { return AccountingMoney::units((string)$s); }













    protected function budgetActual(object $line): int
    {
        $q=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
            ->where('j.status','POSTED')->where('j.origin','!=','CLOSING')->where('l.account_id',$line->account_id)
            ->whereBetween('j.entry_date',[$line->starts_on,$line->ends_on]);
        if ($line->cost_center_id) {
            $q->leftJoin('cost_center_allocations as c','c.journal_line_id','=','l.id')
                ->where(function($w) use($line) {
                    $w->where('c.cost_center_id',$line->cost_center_id)
                        ->orWhere(function($inner) use($line) {
                            $inner->whereNull('c.id')->where('l.cost_center_id',$line->cost_center_id);
                        });
                });
            $raw=$q->selectRaw('COALESCE(SUM((CASE WHEN c.id IS NOT NULL THEN c.percentage/100 ELSE 1 END) * (CASE WHEN ? = ? THEN l.credit-l.debit ELSE l.debit-l.credit END)),0) as actual',[$line->type,'income'])->first();
        } else {
            $raw=$q->selectRaw('COALESCE(SUM(CASE WHEN ? = ? THEN l.credit-l.debit ELSE l.debit-l.credit END),0) as actual',[$line->type,'income'])->first();
        }
        // SQL weighted aggregates may have up to 8 decimal places; four-decimal BDT rounding is required.
        return $this->signedUnits((string)$raw->actual);
    }

    protected function signedUnits(string $s): int
    {
        $negative=str_starts_with($s,'-');$s=ltrim($s,'+-');
        // Financial aggregates are decimal strings. Round half up to 4 decimal places, never via float.
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/',$s,$m)) return 0;
        $fraction=str_pad($m[2]??'',5,'0');
        $v=((int)$m[1])*10000+(int)substr($fraction,0,4)+(int)($fraction[4]>='5');
        return $negative?-$v:$v;
    }













}
