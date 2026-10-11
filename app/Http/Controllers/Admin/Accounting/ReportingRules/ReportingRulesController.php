<?php
namespace App\Http\Controllers\Admin\Accounting\ReportingRules;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingReportService,AccountingMoney};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** ReportingRules accounting module. A shared base provides posting dependencies and invariant checks. */
final class ReportingRulesController extends \App\Http\Controllers\Admin\Accounting\Shared\AccountingReportsController
{
    public function rules()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        abort_unless(auth()->user()?->can('accounting-view'),403);
        $accounts=DB::table('chart_of_accounts')->where('is_header',false)->orderBy('code')->get();
        $cashRules=DB::table('accounting_cash_flow_rules')->pluck('activity','account_id');
        $taxRules=DB::table('accounting_tax_report_accounts')->pluck('tax_type','account_id');
        return view('admin.accounting.reporting-rules.index',compact('accounts','cashRules','taxRules'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveRules(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        abort_unless(auth()->user()?->can('accounting-report-mapping'),403);
        $d=$r->validate(['account_id'=>'required|exists:chart_of_accounts,id',
            'rule_type'=>'required|in:CASH_FLOW,TAX','classification'=>'required|string|max:20']);
        $account=DB::table('chart_of_accounts')->find($d['account_id']);
        if ($account->is_header || !$account->is_active) throw ValidationException::withMessages(['account_id'=>'Choose active posting account.']);
        if ($d['rule_type']==='CASH_FLOW') {
            if (!in_array($d['classification'],['CASH','OPERATING','INVESTING','FINANCING','NONE'],true))
                throw ValidationException::withMessages(['classification'=>'Invalid Cash Flow activity.']);
            if ($d['classification']==='CASH' && $account->type!=='asset')
                throw ValidationException::withMessages(['account_id'=>'Cash / bank accounts must be assets.']);
            DB::transaction(function() use($d) {
                DB::table('accounting_cash_flow_rules')->where('account_id',$d['account_id'])->delete();
                if ($d['classification']!=='NONE') DB::table('accounting_cash_flow_rules')->insert([
                    'account_id'=>$d['account_id'],'activity'=>$d['classification'],
                    'updated_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            });
        } else {
            if (!in_array($d['classification'],['VAT','VDS','TDS','NONE'],true))
                throw ValidationException::withMessages(['classification'=>'Invalid Tax tag.']);
            if ($d['classification']!=='NONE' && !in_array($account->type,['asset','liability'],true))
                throw ValidationException::withMessages(['account_id'=>'Tax accounts must be asset or liability posting accounts.']);
            DB::transaction(function() use($d) {
                DB::table('accounting_tax_report_accounts')->where('account_id',$d['account_id'])->delete();
                if ($d['classification']!=='NONE') DB::table('accounting_tax_report_accounts')->insert([
                    'account_id'=>$d['account_id'],'tax_type'=>$d['classification'],
                    'created_at'=>now(),'updated_at'=>now()]);
            });
        }
        \App\Services\Accounting\AccountingAuditService::record('report_account',(int)$d['account_id'],'MAP',null,$d);
        return back()->with('success','Report account classification updated and audited.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
