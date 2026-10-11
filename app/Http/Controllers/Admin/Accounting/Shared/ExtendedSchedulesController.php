<?php
namespace App\Http\Controllers\Admin\Accounting\Shared;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingMoney,JournalPostingService,AccountingAuditService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit accountant scheduling: never silently auto-posts deferrals, asset depreciation or loan payments. */
abstract class ExtendedSchedulesController extends Controller
{
    use LogsAccountingExceptions;

    public function __construct(protected JournalPostingService $posting) {}
    protected function gate(string $name): void { abort_unless(auth()->user()?->can($name),403); }
    protected function u(mixed $n): int { return AccountingMoney::units((string)($n??'0')); }
    protected function f(int $u): string { return AccountingMoney::format($u); }
    protected function leaf(int $id,string $type): object
    {
        $a=DB::table('chart_of_accounts')->where('id',$id)->where('is_header',false)->where('is_active',true)->first();
        if (!$a || $a->type!==$type) throw ValidationException::withMessages(['account'=>'Account must be active '.$type.' posting account.']);
        return $a;
    }
    protected function posted(int $id): object
    {
        $entry=DB::table('journal_entries')->find($id);
        if (!$entry || $entry->status!=='POSTED') throw ValidationException::withMessages(['source_journal_entry_id'=>'Only POSTED source journals qualify.']);
        return $entry;
    }
    protected function journal(string $code): int
    {
        $id=DB::table('accounting_journals')->where('code',$code)->where('is_active',true)->value('id');
        if (!$id) throw ValidationException::withMessages(['journal'=>'Journal is not configured.']);
        return $id;
    }
    protected function line(int $account,int $dr,int $cr,string $memo): array
    { return ['account_id'=>$account,'debit'=>$this->f($dr),'credit'=>$this->f($cr),'memo'=>$memo]; }
    protected function draft(string $date,string $reference,string $memo,array $lines,string $journal='ADJ'): int
    {
        return $this->posting->createDraft(['entry_date'=>$date,'journal_id'=>$this->journal($journal),
            'reference'=>$reference,'narration'=>$memo,'created_by'=>auth()->id()],$lines);
    }
    protected function validateSourceValue(int $id,int $accountId,int $max,int $sign=1): void
    {
        $this->posted($id);
        $lines=DB::table('journal_lines')->where('journal_entry_id',$id)->where('account_id',$accountId)->get();
        $posted=0;
        foreach($lines as $l) $posted+= $this->u($l->debit)-$this->u($l->credit);
        if ($posted*$sign<$max) throw ValidationException::withMessages(['total_amount'=>'Source journal amount is insufficient for the proposed schedule.']);
    }












}
