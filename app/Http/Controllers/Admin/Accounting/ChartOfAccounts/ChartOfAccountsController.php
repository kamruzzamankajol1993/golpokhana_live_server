<?php
namespace App\Http\Controllers\Admin\Accounting\ChartOfAccounts;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** ChartOfAccounts accounting module. A shared base provides posting dependencies and invariant checks. */
final class ChartOfAccountsController extends \App\Http\Controllers\Admin\Accounting\Shared\FoundationController
{
    public function accounts()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-view');
        $accounts=DB::table('chart_of_accounts')->orderBy('code')->get();
        $q=trim((string)request()->query('q', ''));
        $accountMatches=DB::table('chart_of_accounts')
            ->when($q!=='', fn($query)=>$query->where(function($match) use ($q){
                $match->where('code','like','%'.$q.'%')->orWhere('name','like','%'.$q.'%');
            }))
            ->orderBy('code')->paginate(20, ['*'], 'acc_chart_of_accounts_1')->withQueryString();
        return view('admin.accounting.chart-of-accounts.index',compact('accounts','accountMatches','q'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveAccount(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-coa');
        $data=$r->validate([
            'id'=>'nullable|integer|exists:chart_of_accounts,id','code'=>'required|string|max:30',
            'name'=>'required|string|max:150','type'=>'required|in:asset,liability,equity,income,contra_revenue,cogs,expense',
            'normal_balance'=>'required|in:debit,credit','parent_id'=>'nullable|exists:chart_of_accounts,id',
            'is_header'=>'required|boolean','is_active'=>'required|boolean','is_control'=>'required|boolean',
            'description'=>'nullable|string|max:1000',
        ]);
        DB::transaction(function () use($data) {
            $id=$data['id'] ?? null; unset($data['id']);
            if (DB::table('chart_of_accounts')->where('code',$data['code'])->when($id,fn($q)=>$q->where('id','!=',$id))->exists()) {
                throw ValidationException::withMessages(['code'=>'Account code is already used.']);
            }
            if ($id && ($id==($data['parent_id'] ?? null))) throw ValidationException::withMessages(['parent_id'=>'Account cannot be its own parent.']);
            if ($data['parent_id']) {
                $p=DB::table('chart_of_accounts')->find($data['parent_id']);
                if (!$p || !$p->is_header || !$p->is_active || $p->type!==$data['type'])
                    throw ValidationException::withMessages(['parent_id'=>'Parent must be an active header of the same type.']);
                $cursor=$p;
                while ($id && $cursor) {
                    if ((int)$cursor->id===(int)$id) throw ValidationException::withMessages(['parent_id'=>'Circular account hierarchy.']);
                    $cursor=$cursor->parent_id ? DB::table('chart_of_accounts')->find($cursor->parent_id) : null;
                }
            }
            if ($id) {
                $oldAccount=DB::table('chart_of_accounts')->find($id);
                $posted=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
                    ->where('l.account_id',$id)->where('j.status','POSTED')->exists();
                if ($posted) {
                    $existing=DB::table('chart_of_accounts')->find($id);
                    foreach (['code','type','normal_balance','is_header','is_control','parent_id'] as $f) {
                        if ((string)($existing->$f ?? '') !== (string)($data[$f] ?? ''))
                            throw ValidationException::withMessages(['account'=>'Financial classification of a posted account is immutable.']);
                    }
                }
                if ($data['is_header'] && DB::table('journal_lines')->where('account_id',$id)->exists())
                    throw ValidationException::withMessages(['account'=>'An account with journal lines cannot become a header.']);
                if (!$data['is_header'] && DB::table('chart_of_accounts')->where('parent_id',$id)->exists())
                    throw ValidationException::withMessages(['account'=>'A parent cannot become a posting account.']);
                DB::table('chart_of_accounts')->where('id',$id)->update($data+['updated_at'=>now()]);
                \App\Services\Accounting\AccountingAuditService::record('account',$id,'UPDATE',(array)$oldAccount,$data);
            } else {
                $newId=DB::table('chart_of_accounts')->insertGetId($data+['created_at'=>now(),'updated_at'=>now()]);
                \App\Services\Accounting\AccountingAuditService::record('account',$newId,'CREATE',null,$data);
            }
        });
        return back()->with('success','Account saved.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function deleteAccount(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-coa');
        app(\App\Services\Accounting\AccountingSafeDeletionService::class)->account($id);
        return back()->with('success','Unused draft/master record deleted. Accounting audit history retained.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

}
