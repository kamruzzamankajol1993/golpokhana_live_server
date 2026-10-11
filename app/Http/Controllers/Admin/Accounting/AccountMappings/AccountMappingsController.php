<?php
namespace App\Http\Controllers\Admin\Accounting\AccountMappings;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** AccountMappings accounting module. A shared base provides posting dependencies and invariant checks. */
final class AccountMappingsController extends \App\Http\Controllers\Admin\Accounting\Shared\FoundationController
{
    public function mappings()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-view');
        $mappings=DB::table('account_mappings as m')->join('accounting_journals as j','j.id','=','m.journal_id')
            ->select('m.*','j.code as journal_code')->orderBy('m.source_type')->get();
        $journals=DB::table('accounting_journals')->where('is_active',true)->get();
        $accounts=DB::table('chart_of_accounts')->where('is_active',true)->where('is_header',false)->orderBy('code')->get();
        $rules=DB::table('account_mapping_rules')->orderBy('sort_order')->get()->groupBy('mapping_id');
        return view('admin.accounting.account-mappings.index',compact('mappings','journals','accounts','rules'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveMapping(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-mapping');
        $d=$r->validate(['id'=>'nullable|exists:account_mappings,id','name'=>'required|string|max:150',
            'source_type'=>'required|alpha_dash|max:70','event_type'=>'required|alpha_dash|max:80',
            'journal_id'=>'required|exists:accounting_journals,id','is_active'=>'required|boolean',
            'requires_approval'=>'required|boolean',
            'rules'=>'required|array|min:2|max:30','rules.*.role'=>'required|string|max:70',
            'rules.*.direction'=>'required|in:debit,credit',
            'rules.*.account_id'=>'required|exists:chart_of_accounts,id',
            'rules.*.amount_key'=>'required|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/|max:80']);
        DB::transaction(function () use($d) {
            $id=$d['id'] ?? null; unset($d['id']); $rules=$d['rules']; unset($d['rules']);
            $j=DB::table('accounting_journals')->find($d['journal_id']);
            if (!$j || !$j->is_active) throw ValidationException::withMessages(['journal'=>'Invalid journal.']);
            $other=DB::table('account_mappings')->where('source_type',$d['source_type'])->where('event_type',$d['event_type'])
                ->when($id,fn($q)=>$q->where('id','!=',$id))->exists();
            if ($other) throw ValidationException::withMessages(['source_type'=>'Event mapping exists already.']);
            foreach ($rules as $line) {
                $account=DB::table('chart_of_accounts')->find($line['account_id']);
                if (!$account || !$account->is_active || $account->is_header)
                    throw ValidationException::withMessages(['rules'=>'Mapping needs active posting accounts.']);
            }
            $oldMapping=$id ? ['mapping'=>(array)DB::table('account_mappings')->find($id),
                'rules'=>DB::table('account_mapping_rules')->where('mapping_id',$id)->get()->toArray()] : null;
            if ($id) DB::table('account_mappings')->where('id',$id)->update($d+['updated_at'=>now()]);
            else $id=DB::table('account_mappings')->insertGetId($d+['created_at'=>now(),'updated_at'=>now()]);
            DB::table('account_mapping_rules')->where('mapping_id',$id)->delete();
            foreach ($rules as $i=>$line) DB::table('account_mapping_rules')->insert($line+[
                'mapping_id'=>$id,'sort_order'=>$i+1,'created_at'=>now(),'updated_at'=>now(),
            ]);
            \App\Services\Accounting\AccountingAuditService::record('mapping',$id,$oldMapping?'UPDATE':'CREATE',
                $oldMapping,['mapping'=>$d,'rules'=>$rules]);
        });
        return back()->with('success','Multi-line mapping saved. Changes do not rewrite posted journals.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function deleteMapping(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-mapping');
        app(\App\Services\Accounting\AccountingSafeDeletionService::class)->mapping($id);
        return back()->with('success','Unused draft/master record deleted. Accounting audit history retained.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

}
