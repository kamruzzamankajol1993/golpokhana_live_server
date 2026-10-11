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
}
