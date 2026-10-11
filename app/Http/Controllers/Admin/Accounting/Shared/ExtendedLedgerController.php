<?php
namespace App\Http\Controllers\Admin\Accounting\Shared;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingEventService,AccountingMoney,JournalPostingService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 4: company-level registers. Only POSTED journals affect financial balances. */
abstract class ExtendedLedgerController extends Controller
{
    use LogsAccountingExceptions;

    public function __construct(protected JournalPostingService $posting, protected AccountingEventService $events) {}
    protected function gate(string $p='accounting-view'): void { abort_unless(auth()->user()?->can($p),403); }
    protected function u(mixed $value): int { return AccountingMoney::units((string)($value??'0')); }
    protected function f(int $value): string { return AccountingMoney::format($value); }
    protected function account(int $id, ?string $type=null): object
    {
        $a=DB::table('chart_of_accounts')->find($id);
        if (!$a || !$a->is_active || $a->is_header || ($type && $a->type!==$type))
            throw ValidationException::withMessages(['account'=>'Select an active non-header '.($type?:'').' account.']);
        return $a;
    }
    protected function journal(string $code): int
    {
        $id=DB::table('accounting_journals')->where('code',$code)->where('is_active',true)->value('id');
        if (!$id) throw ValidationException::withMessages(['journal'=>'Required journal is not configured: '.$code]);
        return (int)$id;
    }
    protected function line(int $id,int $dr,int $cr,string $memo=''): array
    { return ['account_id'=>$id,'debit'=>$this->f($dr),'credit'=>$this->f($cr),'memo'=>$memo]; }
    protected function draft(string $date,string $ref,string $memo,array $lines,string $type='ADJ'): int
    { return $this->posting->createDraft(['entry_date'=>$date,'journal_id'=>$this->journal($type),
        'reference'=>$ref,'narration'=>$memo,'created_by'=>auth()->id()],$lines); }
    protected function posted(int $id): object
    {
        $j=DB::table('journal_entries')->find($id);
        if (!$j || $j->status!=='POSTED') throw ValidationException::withMessages(['journal'=>'A POSTED source journal is required.']);
        return $j;
    }


















}
