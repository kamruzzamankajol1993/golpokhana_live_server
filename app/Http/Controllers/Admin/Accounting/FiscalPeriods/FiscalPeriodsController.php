<?php
namespace App\Http\Controllers\Admin\Accounting\FiscalPeriods;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** FiscalPeriods accounting module. A shared base provides posting dependencies and invariant checks. */
final class FiscalPeriodsController extends \App\Http\Controllers\Admin\Accounting\Shared\FoundationController
{
    public function periods()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-view');
        $years=DB::table('fiscal_years')->orderByDesc('starts_on')->get();
        $periods=DB::table('accounting_periods')->orderByDesc('starts_on')->paginate(20, ['*'], 'acc_fiscal_periods_1')->withQueryString();
        return view('admin.accounting.fiscal-periods.index',compact('years','periods'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function createYear(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-period');
        $data=$r->validate(['name'=>'required|string|max:60|unique:fiscal_years,name',
            'starts_on'=>'required|date','ends_on'=>'required|date|after:starts_on']);
        DB::transaction(function () use($data) {
            $start=Carbon::parse($data['starts_on'])->startOfDay(); $end=Carbon::parse($data['ends_on'])->startOfDay();
            if (DB::table('fiscal_years')->where('starts_on','<=',$end)->where('ends_on','>=',$start)->exists())
                throw ValidationException::withMessages(['starts_on'=>'Fiscal years must not overlap.']);
            $id=DB::table('fiscal_years')->insertGetId($data+['status'=>'OPEN','created_at'=>now(),'updated_at'=>now()]);
            $cursor=$start->copy();
            while ($cursor->lte($end)) {
                $last=$cursor->copy()->endOfMonth()->startOfDay(); if ($last->gt($end)) $last=$end->copy();
                DB::table('accounting_periods')->insert(['fiscal_year_id'=>$id,'name'=>$cursor->format('M Y'),
                    'starts_on'=>$cursor->toDateString(),'ends_on'=>$last->toDateString(),'status'=>'OPEN',
                    'created_at'=>now(),'updated_at'=>now()]);
                $cursor=$last->copy()->addDay();
            }
        });
        return back()->with('success','Fiscal year and monthly periods created.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function changePeriod(Request $r, int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-period');
        $d=$r->validate(['status'=>'required|in:OPEN,SOFT_CLOSED,CLOSED',
            'reason'=>'required|string|min:8|max:2000']);
        app(\App\Services\Accounting\AccountingCloseService::class)
            ->changePeriod($id,$d['status'],$d['reason'],auth()->id());
        return back()->with('success','Period change saved with finance checklist and audit.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
