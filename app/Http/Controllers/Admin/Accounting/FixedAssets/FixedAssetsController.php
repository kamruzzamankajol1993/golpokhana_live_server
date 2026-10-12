<?php
namespace App\Http\Controllers\Admin\Accounting\FixedAssets;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingMoney,JournalPostingService,AccountingAuditService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit accountant scheduling: never silently auto-posts deferrals, asset depreciation or loan payments. */

/** FixedAssets accounting module. A shared base provides posting dependencies and invariant checks. */
final class FixedAssetsController extends \App\Http\Controllers\Admin\Accounting\Shared\ExtendedSchedulesController
{
    public function assets()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-view');
        return view('admin.accounting.fixed-assets.index',[
            'policy'=>DB::table('accounting_asset_policies')->where('id',1)->first(),
            'assets'=>DB::table('fixed_assets as a')->join('asset_categories as c','c.id','=','a.asset_category_id')
                ->select('a.*','c.name as category')->orderByDesc('a.id')->paginate(20),
            'depreciations'=>DB::table('asset_depreciations')->orderByDesc('period_month')->paginate(20, ['*'], 'acc_fixed_assets_2')->withQueryString(),
            'categories'=>DB::table('asset_categories')->where('is_active',true)->get(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->get(),
            'disposals'=>DB::table('accounting_asset_disposals as d')->join('fixed_assets as a','a.id','=','d.fixed_asset_id')->select('d.*','a.code as asset_code')->orderByDesc('d.id')->limit(100)->get(),
        ]);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function approveAssetPolicy(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-settings');
        $d=$r->validate(['approval_reference'=>'required|string|min:10|max:3000','is_approved'=>'required|boolean']);
        DB::transaction(function() use($d){
            $old=DB::table('accounting_asset_policies')->where('id',1)->lockForUpdate()->first();
            DB::table('accounting_asset_policies')->where('id',1)->update($d+[
                'approved_by'=>auth()->id(),'approved_at'=>$d['is_approved']?now():null,'updated_at'=>now()]);
            AccountingAuditService::record('asset_policy',1,'CHANGE',(array)$old,$d,$d['approval_reference']);
        });
        return back()->with('success','Asset policy updated with approval history.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function addAsset(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-assets');
        $d=$r->validate(['asset_category_id'=>'required|exists:asset_categories,id',
            'code'=>'required|string|max:60|unique:fixed_assets,code','name'=>'required|string|max:180',
            'source_journal_entry_id'=>'required|exists:journal_entries,id',
            'asset_account_id'=>'required|exists:chart_of_accounts,id',
            'accumulated_depreciation_account_id'=>'required|exists:chart_of_accounts,id',
            'depreciation_expense_account_id'=>'required|exists:chart_of_accounts,id',
            'cost'=>'required|numeric|gt:0','residual_value'=>'required|numeric|min:0',
            'useful_life_months'=>'required|integer|min:1|max:600','in_service_on'=>'required|date_format:Y-m-d']);
        $cost=$this->u($d['cost']);$residual=$this->u($d['residual_value']);
        if($cost-$residual<(int)$d['useful_life_months']) throw ValidationException::withMessages(['cost'=>'Depreciable cost must support a positive 4-decimal monthly charge.']);
        if($residual>=$cost) throw ValidationException::withMessages(['residual_value'=>'Residual must be lower than cost.']);
        $this->leaf($d['asset_account_id'],'asset');$this->leaf($d['accumulated_depreciation_account_id'],'asset');
        $this->leaf($d['depreciation_expense_account_id'],'expense');
        $this->validateSourceValue($d['source_journal_entry_id'],$d['asset_account_id'],$cost);
        if(DB::table('fixed_assets')->where('source_journal_entry_id',$d['source_journal_entry_id'])->exists())
            throw ValidationException::withMessages(['source_journal_entry_id'=>'Acquisition journal already linked to an asset.']);
        DB::table('fixed_assets')->insert($d+['created_by'=>auth()->id(),'status'=>'ACTIVE',
            'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Asset registered. Depreciation remains disabled until accountant policy approval.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function depreciation(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-assets');
        $d=$r->validate(['period_month'=>'required|date_format:Y-m-d']);
        if(!DB::table('accounting_asset_policies')->where('id',1)->where('is_approved',true)->exists())
            throw ValidationException::withMessages(['policy'=>'Depreciation requires approved accounting policy.']);
        $journal=DB::transaction(function() use($id,$d){
            $a=DB::table('fixed_assets')->where('id',$id)->lockForUpdate()->first();
            if (!$a || $a->status!=='ACTIVE') throw ValidationException::withMessages(['asset'=>'Asset is not active.']);
            if(DB::table('accounting_asset_disposals')->where('fixed_asset_id',$id)->where('status','PENDING')->exists()) throw ValidationException::withMessages(['asset'=>'Pending disposal must be completed or cancelled before depreciation.']);
            $month=Carbon::parse($d['period_month']);
            if ($month->day!==1 || $month->lt(Carbon::parse($a->in_service_on)->startOfMonth()))
                throw ValidationException::withMessages(['period_month'=>'Invalid depreciation month.']);
            $elapsed=(int) Carbon::parse($a->in_service_on)->startOfMonth()->diffInMonths($month);
            if ($elapsed>=$a->useful_life_months) throw ValidationException::withMessages(['asset'=>'Useful life has ended.']);
            if(DB::table('asset_depreciations')->where('fixed_asset_id',$id)->where('period_month',$month->toDateString())->exists())
                throw ValidationException::withMessages(['asset'=>'Month already scheduled.']);
            $total=$this->u($a->cost)-$this->u($a->residual_value);
            $monthly=intdiv($total,$a->useful_life_months);
            $value=$elapsed===$a->useful_life_months-1 ? $total-$monthly*($a->useful_life_months-1) : $monthly;
            $j=$this->draft($month->copy()->endOfMonth()->toDateString(),'DEP-'.$id.'-'.$month->format('Ym'),
                'Monthly straight-line depreciation: '.$a->name,[
                    $this->line($a->depreciation_expense_account_id,$value,0,'Depreciation expense'),
                    $this->line($a->accumulated_depreciation_account_id,0,$value,'Accumulated depreciation')],'DEP');
            DB::table('asset_depreciations')->insert(['fixed_asset_id'=>$id,'period_month'=>$month->toDateString(),
                'amount'=>$this->f($value),'journal_entry_id'=>$j,'created_at'=>now(),'updated_at'=>now()]);
            return $j;
        },3);
        return redirect()->route('accounting.journals.show',$journal)->with('success','Depreciation DRAFT generated. Submit, approve and post separately.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    /** Disposal generates a DRAFT. Asset status changes only after approved POST. */
    public function dispose(Request $r,int $id)
    {
        $this->gate('accounting-assets');
        $d=$r->validate(['disposal_type'=>'required|in:SALE,WRITE_OFF',
            'disposed_on'=>'required|date_format:Y-m-d','reference'=>'required|string|max:100',
            'reason'=>'required|string|min:10|max:2000',
            'sale_proceeds'=>'required|numeric|min:0',
            'cash_account_id'=>'nullable|integer|exists:chart_of_accounts,id',
            'gain_loss_account_id'=>'nullable|integer|exists:chart_of_accounts,id']);
        $journal=DB::transaction(function() use($id,$d){
            $asset=DB::table('fixed_assets')->where('id',$id)->lockForUpdate()->first();
            if (!$asset || $asset->status!=='ACTIVE')
                throw ValidationException::withMessages(['asset'=>'Only ACTIVE assets may be disposed.']);
            if($d['disposed_on']<$asset->in_service_on)
                throw ValidationException::withMessages(['disposed_on'=>'Cannot dispose before the in-service date.']);
            if(DB::table('accounting_asset_disposals')->where('fixed_asset_id',$id)
                ->whereIn('status',['PENDING','POSTED'])->exists())
                throw ValidationException::withMessages(['asset'=>'Asset already has an active disposal.']);
            $deps=DB::table('asset_depreciations as d')
                ->join('journal_entries as j','j.id','=','d.journal_entry_id')
                ->where('d.fixed_asset_id',$id)->get(['d.amount','d.period_month','j.id as journal_id','j.status']);
            $accum=0;
            foreach($deps as $dep){
                if(in_array($dep->status,['DRAFT','SUBMITTED','APPROVED'],true))
                    throw ValidationException::withMessages(['depreciation'=>'Resolve pending depreciation journal before disposal.']);
                if($dep->status!=='POSTED') continue;
                if(DB::table('journal_entries')->where('status','POSTED')->where('reversal_of_id',$dep->journal_id)->exists()) continue;
                if(Carbon::parse($dep->period_month)->endOfMonth()->toDateString()>$d['disposed_on'])
                    throw ValidationException::withMessages(['disposed_on'=>'Disposal cannot predate posted depreciation.']);
                $accum+=$this->u($dep->amount);
            }
            $cost=$this->u($asset->cost); $book=$cost-$accum;
            if($book<0) throw ValidationException::withMessages(['asset'=>'Depreciation exceeds acquisition cost.']);
            $sale=$this->u($d['sale_proceeds']);
            if($d['disposal_type']==='WRITE_OFF' && $sale>0)
                throw ValidationException::withMessages(['sale_proceeds'=>'Write-off cannot have sale proceeds. Select SALE.']);
            if($d['disposal_type']==='SALE' && $sale<=0)
                throw ValidationException::withMessages(['sale_proceeds'=>'Sale proceeds must be positive.']);
            $gain=$sale-$book;
            $lines=[];
            if($sale>0){
                if(empty($d['cash_account_id'])) throw ValidationException::withMessages(['cash_account_id'=>'Select receiving bank/cash account.']);
                $cash=$this->leaf((int)$d['cash_account_id'],'asset');
                if(!in_array($cash->code,['1100','1110','1120','1150'],true))
                    throw ValidationException::withMessages(['cash_account_id'=>'Select cash/bank account.']);
                $lines[]=$this->line($cash->id,$sale,0,'Asset sale proceeds');
            }
            if($accum>0) $lines[]=$this->line($asset->accumulated_depreciation_account_id,$accum,0,'Remove accumulated depreciation');
            $lines[]=$this->line($asset->asset_account_id,0,$cost,'Remove disposed asset at cost');
            if($gain!==0){
                if(empty($d['gain_loss_account_id'])) throw ValidationException::withMessages(['gain_loss_account_id'=>'Select disposal gain/loss posting account.']);
                $acc=$this->leaf((int)$d['gain_loss_account_id'],$gain>0?'income':'expense');
                $lines[]=$this->line($acc->id,max(0,-$gain),max(0,$gain),'Asset disposal '.($gain>0?'gain':'loss'));
            }
            // Checks period/amount balance before writing any disposal row.
            $journal=$this->draft($d['disposed_on'],$d['reference'],'Asset disposal: '.$asset->code.' — '.$d['reason'],$lines,'ADJ');
            DB::table('accounting_asset_disposals')->insert([
                'fixed_asset_id'=>$id,'journal_entry_id'=>$journal,'disposal_type'=>$d['disposal_type'],
                'disposed_on'=>$d['disposed_on'],'sale_proceeds'=>$this->f($sale),
                'cash_account_id'=>$sale?(int)$d['cash_account_id']:null,
                'gain_loss_account_id'=>$gain?(int)$d['gain_loss_account_id']:null,
                'accumulated_depreciation'=>$this->f($accum), 'book_value'=>$this->f($book),
                'gain_loss'=>$this->f($gain),'status'=>'PENDING','reference'=>$d['reference'],
                'reason'=>$d['reason'],'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $journal;
        },3);
        return redirect()->route('accounting.journals.show',$journal)
            ->with('success','Disposal DRAFT created. Asset remains ACTIVE until independent approval and posting.');
    }

    public function cancelDisposal(Request $r,int $id)
    {
        $this->gate('accounting-assets');
        DB::transaction(function() use($id){
            $d=DB::table('accounting_asset_disposals')->where('id',$id)->lockForUpdate()->first();
            if(!$d || $d->status!=='PENDING') throw ValidationException::withMessages(['asset'=>'Only pending disposal may be cancelled.']);
            $j=DB::table('journal_entries')->where('id',$d->journal_entry_id)->lockForUpdate()->first();
            if(!$j || $j->status!=='DRAFT') throw ValidationException::withMessages(['asset'=>'Only unused DRAFT may be cancelled; submitted journals require approval workflow.']);
            DB::table('accounting_asset_disposals')->where('id',$id)->update(['status'=>'CANCELLED','updated_at'=>now()]);
            DB::table('journal_entries')->where('id',$j->id)->update(['status'=>'CANCELLED','updated_at'=>now()]);
        },3);
        return back()->with('success','Unused disposal draft cancelled. Asset remains active.');
    }
}
