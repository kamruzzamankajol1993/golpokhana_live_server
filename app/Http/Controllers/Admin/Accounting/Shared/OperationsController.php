<?php
namespace App\Http\Controllers\Admin\Accounting\Shared;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingEventService,AccountingInventoryCostService,AccountingMoney,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

abstract class OperationsController extends Controller
{
    use LogsAccountingExceptions;

    public function __construct(protected JournalPostingService $posting, protected AccountingEventService $events,
                                protected AccountingInventoryCostService $costing) {}
    protected function can(string $permission): void { abort_unless(auth()->user()?->can($permission),403); }
    protected function amount(mixed $raw): int { return AccountingMoney::units((string)$raw); }
    protected function account(string $code): int
    {
        $id=DB::table('chart_of_accounts')->where('code',$code)->where('is_active',true)->where('is_header',false)->value('id');
        if (!$id) throw ValidationException::withMessages(['account'=>'Required accounting account '.$code.' is not configured.']);
        return (int)$id;
    }
    protected function journal(string $code): int
    {
        $id=DB::table('accounting_journals')->where('code',$code)->where('is_active',true)->value('id');
        if (!$id) throw ValidationException::withMessages(['journal'=>'Required journal '.$code.' is inactive.']);
        return (int)$id;
    }
    protected function line(int $account,int $debit,int $credit,string $memo='',bool $control=false): array
    {
        return ['account_id'=>$account,'debit'=>AccountingMoney::format($debit),'credit'=>AccountingMoney::format($credit),
            'memo'=>$memo,'allow_control'=>$control];
    }

















}
