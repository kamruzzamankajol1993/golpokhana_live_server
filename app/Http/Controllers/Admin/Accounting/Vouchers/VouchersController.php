<?php
namespace App\Http\Controllers\Admin\Accounting\Vouchers;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingEventService,AccountingInventoryCostService,AccountingMoney,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** Vouchers accounting module. A shared base provides posting dependencies and invariant checks. */
final class VouchersController extends \App\Http\Controllers\Admin\Accounting\Shared\OperationsController
{
    public function vouchers()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $vouchers=DB::table('accounting_vouchers as v')->join('journal_entries as j','j.id','=','v.journal_entry_id')
            ->select('v.*','j.status','j.entry_date','j.number','j.reference')->orderByDesc('v.id')->paginate(20);
        $accounts=DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->orderBy('code')->get();
        return view('admin.accounting.vouchers.index',compact('vouchers','accounts'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function storeVoucher(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-voucher');
        $d=$r->validate([
            'voucher_type'=>'required|in:RV,PV,CV','entry_date'=>'required|date',
            'party_name'=>'nullable|string|max:150','reference'=>'nullable|string|max:100',
            'narration'=>'required|string|min:5|max:2000','payment_reference'=>'nullable|string|max:200',
            'lines'=>'required|array|min:2|max:30','lines.*.account_id'=>'required|exists:chart_of_accounts,id',
            'lines.*.debit'=>'required|numeric|min:0','lines.*.credit'=>'required|numeric|min:0',
        ]);
        $id=DB::transaction(function () use($d) {
            $code=$d['voucher_type']==='CV'?'BANK':($d['voucher_type']==='RV'?'CASH':'MISC');
            $lines=array_map(fn($l)=>['account_id'=>(int)$l['account_id'],
                'debit'=>AccountingMoney::format($this->amount($l['debit'])),
                'credit'=>AccountingMoney::format($this->amount($l['credit']))],$d['lines']);
            $this->posting->assertLines($lines);
            $id=$this->posting->createDraft(['journal_id'=>$this->journal($code),'entry_date'=>$d['entry_date'],
                'reference'=>$d['reference']??null,'narration'=>$d['narration'],'created_by'=>auth()->id()],$lines);
            DB::table('accounting_vouchers')->insert(['voucher_type'=>$d['voucher_type'],'journal_entry_id'=>$id,
                'party_name'=>$d['party_name']??null,'payment_reference'=>$d['payment_reference']??null,
                'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        },3);
        return redirect()->route('accounting.journals.show',$id)->with('success','Voucher created as DRAFT. Submit and obtain approval before posting.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
