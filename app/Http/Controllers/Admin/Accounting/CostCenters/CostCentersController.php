<?php
namespace App\Http\Controllers\Admin\Accounting\CostCenters;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingEventService,AccountingMoney,JournalPostingService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 4: company-level registers. Only POSTED journals affect financial balances. */

/** CostCenters accounting module. A shared base provides posting dependencies and invariant checks. */
final class CostCentersController extends \App\Http\Controllers\Admin\Accounting\Shared\ExtendedLedgerController
{
    public function costCenters()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate();
        return view('admin.accounting.cost-centers.index',[
            'centers'=>DB::table('cost_centers')->orderBy('code')->get(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->get(),
            'allocations'=>DB::table('cost_center_allocations as x')->join('journal_lines as l','l.id','=','x.journal_line_id')
                ->join('journal_entries as j','j.id','=','l.journal_entry_id')
                ->join('cost_centers as c','c.id','=','x.cost_center_id')
                ->select('x.*','j.number','j.status','c.name as center','l.debit','l.credit')->orderByDesc('x.id')->paginate(20, ['*'], 'acc_cost_centers_2')->withQueryString(),
        ]);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveCenter(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-cost-centers');
        $d=$r->validate(['code'=>'required|alpha_dash|max:32','name'=>'required|string|max:120','is_active'=>'required|boolean']);
        DB::table('cost_centers')->updateOrInsert(['code'=>$d['code']],$d+['created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Cost center saved; no separate financial ledger created.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function allocate(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-cost-centers');
        $r->merge(['shares'=>array_values(array_filter((array)$r->input('shares',[]),
            fn($share)=>is_array($share) && !empty($share['cost_center_id']) && ($share['percentage']??'')!==''))]);
        $d=$r->validate(['journal_line_id'=>'required|exists:journal_lines,id','shares'=>'required|array|min:1|max:30',
            'shares.*.cost_center_id'=>'required|exists:cost_centers,id',
            'shares.*.percentage'=>'required|numeric|gt:0|max:100']);
        DB::transaction(function() use($d){
            $line=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
                ->where('l.id',$d['journal_line_id'])->select('j.status','l.id')->lockForUpdate()->first();
            if (!$line || $line->status!=='DRAFT') throw ValidationException::withMessages(['journal_line_id'=>'Allocation can only change on DRAFT journals.']);
            $sum=0;$ids=[];
            foreach($d['shares'] as $s) {
                $sum+=$this->u($s['percentage']); $ids[]=$s['cost_center_id'];
                if (!DB::table('cost_centers')->where('id',$s['cost_center_id'])->where('is_active',true)->exists())
                    throw ValidationException::withMessages(['shares'=>'Inactive cost center.']);
            }
            if (count(array_unique($ids))!==count($ids) || $sum!==1000000)
                throw ValidationException::withMessages(['shares'=>'Unique center allocations must total exactly 100%.']);
            DB::table('cost_center_allocations')->where('journal_line_id',$line->id)->delete();
            foreach($d['shares'] as $s) DB::table('cost_center_allocations')->insert([
                'journal_line_id'=>$line->id,'cost_center_id'=>$s['cost_center_id'],'percentage'=>$this->f($this->u($s['percentage'])),
                'created_at'=>now(),'updated_at'=>now()]);
            DB::table('journal_lines')->where('id',$line->id)->update(['cost_center_id'=>count($d['shares'])===1?$ids[0]:null,'updated_at'=>now()]);
        },3);
        return back()->with('success','100% analytic allocation saved; GL value unchanged.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function deleteCenter(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-cost-centers');
        app(\App\Services\Accounting\AccountingSafeDeletionService::class)->costCenter($id);
        return back()->with('success','Unused draft/master record deleted. Accounting audit history retained.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

}
