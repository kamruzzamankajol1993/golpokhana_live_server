<?php
namespace App\Http\Controllers\Admin\Accounting\Shared;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingMoney,JournalPostingService};
use App\Models\{Purchase,StockMovement};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit, audited financial corrections; never edits POS/Purchase originals. */
abstract class CreditNoteController extends Controller
{
    use LogsAccountingExceptions;

    public function __construct(protected JournalPostingService $posting) {}
    protected function can(): void { abort_unless(auth()->user()?->can('accounting-journal-create'),403); }
    protected function units(mixed $x): int { return AccountingMoney::units((string)$x); }
    protected function amount(int $x): string { return AccountingMoney::format($x); }
    protected function ac(string $code): int
    {
        $account=DB::table('chart_of_accounts')->where('code',$code)->where('is_active',true)->where('is_header',false)->value('id');
        if (!$account) throw ValidationException::withMessages(['account'=>'Account '.$code.' not available.']);
        return (int)$account;
    }
    protected function journal(string $code): int
    {
        $id=DB::table('accounting_journals')->where('code',$code)->where('is_active',true)->value('id');
        if (!$id) throw ValidationException::withMessages(['journal'=>'Journal '.$code.' is disabled.']);
        return (int)$id;
    }
    protected function line(string $code,int $debit,int $credit,string $memo): array
    {
        return ['account_id'=>$this->ac($code),'debit'=>$this->amount($debit),
            'credit'=>$this->amount($credit),'memo'=>$memo,'allow_control'=>true];
    }

    protected function paidFrom(string $method): string
    {
        return match($method) {'Cash'=>'1100','Bank'=>'1120','MFS'=>'1150',
            default=>throw ValidationException::withMessages(['payment_method'=>'Unsupported refund payment account.'])};
    }



}
