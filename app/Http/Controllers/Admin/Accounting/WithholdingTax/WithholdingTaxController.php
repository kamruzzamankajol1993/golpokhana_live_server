<?php
namespace App\Http\Controllers\Admin\Accounting\WithholdingTax;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingEventService,AccountingInventoryCostService,AccountingMoney,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** WithholdingTax accounting module. A shared base provides posting dependencies and invariant checks. */
final class WithholdingTaxController extends \App\Http\Controllers\Admin\Accounting\Shared\OperationsController
{
    public function withholdings()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $records=DB::table('accounting_withholdings as w')->leftJoin('purchases as p','p.id','=','w.purchase_id')
            ->select('w.*','p.purchase_no')->orderByDesc('w.id')->paginate(20);
        $purchases=DB::table('vendor_payables as vp')->join('purchases as p','p.id','=','vp.purchase_id')
            ->select('p.id','p.purchase_no','p.total')->orderByDesc('p.id')->limit(200)->get();
        return view('admin.accounting.withholding-tax.index',compact('records','purchases'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function storeWithholding(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-reconcile');
        $d=$r->validate(['purchase_id'=>'required|exists:purchases,id','tax_type'=>'required|in:TDS,VDS',
            'amount'=>'required|numeric|gt:0','entry_date'=>'required|date',
            'reference'=>'required|string|max:200','evidence'=>'required|string|min:10|max:3000']);
        $id=DB::transaction(function() use($d) {
            $payable=DB::table('vendor_payables')->where('purchase_id',$d['purchase_id'])->first();
            if (!$payable) throw ValidationException::withMessages(['purchase_id'=>'Purchase AP must first be captured for accounting.']);
            if (!DB::table('accounting_events')->where('source_type','purchases')->where('source_id',(string)$d['purchase_id'])
                ->where('event_type','PURCHASE_RECEIVED')->where('status','POSTED')->exists()) {
                throw ValidationException::withMessages(['purchase_id'=>'Supplier purchase journal must be POSTED before withholding.']);
            }
            if (DB::table('accounting_withholdings')->where('purchase_id',$d['purchase_id'])
                ->where('tax_type',$d['tax_type'])->where('reference',$d['reference'])->exists()) {
                throw ValidationException::withMessages(['reference'=>'Withholding already recorded for this reference.']);
            }
            $amount=$this->amount($d['amount']);
            $paid=$this->amount(DB::table('payable_allocations')->where('vendor_payable_id',$payable->id)->sum('amount'));
            $withheld=$this->amount(DB::table('accounting_withholdings')->where('purchase_id',$d['purchase_id'])->sum('amount'));
            $credits=$this->amount(DB::table('accounting_supplier_credit_notes')->where('purchase_id',$d['purchase_id'])->sum('credit_amount'));
            if ($amount>$this->amount($payable->original_amount)-$paid-$withheld-$credits) {
                throw ValidationException::withMessages(['amount'=>'Withholding exceeds remaining accounting supplier payable.']);
            }
            $j=$this->posting->createDraft(['journal_id'=>$this->journal('PUR'),
                'entry_date'=>$d['entry_date'],'reference'=>$d['reference'],
                'narration'=>$d['tax_type'].' withholding: '.$d['evidence'],
                'origin'=>'SOURCE','created_by'=>auth()->id()],
                [$this->line($this->account('2100'),$amount,0,'Supplier payable settled by tax withholding',true),
                 $this->line($this->account($d['tax_type']==='TDS'?'2141':'2142'),0,$amount,$d['tax_type'].' withheld')]);
            DB::table('accounting_withholdings')->insert(['purchase_id'=>$d['purchase_id'],
                'tax_type'=>$d['tax_type'],'amount'=>AccountingMoney::format($amount),
                'reference'=>$d['reference'],'evidence'=>$d['evidence'],
                'journal_entry_id'=>$j,'created_at'=>now(),'updated_at'=>now()]);
            return $j;
        });
        return redirect()->route('accounting.journals.show',$id)->with('success','Withholding adjustment DRAFT created; accountant approval required.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
