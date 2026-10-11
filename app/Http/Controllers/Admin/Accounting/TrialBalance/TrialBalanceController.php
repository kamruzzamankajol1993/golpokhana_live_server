<?php
namespace App\Http\Controllers\Admin\Accounting\TrialBalance;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\AccountingEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** TrialBalance accounting module. A shared base provides posting dependencies and invariant checks. */
final class TrialBalanceController extends \App\Http\Controllers\Admin\Accounting\Shared\JournalController
{
    public function trialBalance()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-view');
        $rows=DB::table('chart_of_accounts as a')->join('journal_lines as l','l.account_id','=','a.id')
            ->join('journal_entries as e','e.id','=','l.journal_entry_id')->where('e.status','POSTED')
            ->groupBy('a.id','a.code','a.name','a.normal_balance')
            ->selectRaw('a.code,a.name,a.normal_balance,SUM(l.debit) as debit_total,SUM(l.credit) as credit_total')
            ->orderBy('a.code')->get();
        return view('admin.accounting.trial-balance.index',compact('rows'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
