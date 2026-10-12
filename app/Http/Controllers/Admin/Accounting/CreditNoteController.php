<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingMoney,JournalPostingService};
use App\Models\{Purchase,StockMovement};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit, audited financial corrections; never edits POS/Purchase originals. */
final class CreditNoteController extends Controller
{
    public function __construct(private JournalPostingService $posting) {}
    private function can(): void { abort_unless(auth()->user()?->can('accounting-journal-create'),403); }
    private function units(mixed $x): int { return AccountingMoney::units((string)$x); }
    private function amount(int $x): string { return AccountingMoney::format($x); }
    private function ac(string $code): int
    {
        $account=DB::table('chart_of_accounts')->where('code',$code)->where('is_active',true)->where('is_header',false)->value('id');
        if (!$account) throw ValidationException::withMessages(['account'=>'Account '.$code.' not available.']);
        return (int)$account;
    }
    private function journal(string $code): int
    {
        $id=DB::table('accounting_journals')->where('code',$code)->where('is_active',true)->value('id');
        if (!$id) throw ValidationException::withMessages(['journal'=>'Journal '.$code.' is disabled.']);
        return (int)$id;
    }
    private function line(string $code,int $debit,int $credit,string $memo): array
    {
        return ['account_id'=>$this->ac($code),'debit'=>$this->amount($debit),
            'credit'=>$this->amount($credit),'memo'=>$memo,'allow_control'=>true];
    }
    public function index()
    {
        $this->can();
        $sales=DB::table('accounting_sales_refunds')->orderByDesc('id')->limit(60)->get();
        $deposits=DB::table('accounting_customer_deposit_refunds')->orderByDesc('id')->limit(60)->get();
        $suppliers=DB::table('accounting_supplier_credit_notes')->orderByDesc('id')->limit(60)->get();
        return view('admin.accounting.credit-notes',compact('sales','deposits','suppliers'));
    }
    private function paidFrom(string $method): string
    {
        return match($method) {'Cash'=>'1100','Bank'=>'1120','MFS'=>'1150',
            default=>throw ValidationException::withMessages(['payment_method'=>'Unsupported refund payment account.'])};
    }
    public function sale(Request $r)
    {
        $this->can();
        $d=$r->validate(['order_id'=>'required|integer|exists:orders,id','refund_reference'=>'required|string|max:150|unique:accounting_sales_refunds,refund_reference',
            'refund_date'=>'required|date','payment_method'=>'required|in:Cash,Bank,MFS',
            'refund_amount'=>'required|numeric|gt:0','vat_refund'=>'required|numeric|min:0',
            'supporting_evidence'=>'required|string|min:15|max:3000']);
        $id=DB::transaction(function() use($d) {
            $order=DB::table('orders')->where('id',$d['order_id'])->lockForUpdate()->first();
            if (!$order || strcasecmp((string)$order->status,'Completed')!==0) throw ValidationException::withMessages(['order_id'=>'Completed order required.']);
            $saleEvent=DB::table('accounting_events')->where('source_type','orders')->where('source_id',(string)$order->id)
                ->where('event_type','SALES_COMPLETED')->where('status','POSTED')->first();
            if (!$saleEvent) throw ValidationException::withMessages(['order_id'=>'Original sale must be POSTED before creating a financial refund.']);
            $refunded=$this->units(DB::table('accounting_sales_refunds')->where('order_id',$order->id)->sum('refund_amount'));
            $amount=$this->units($d['refund_amount']);
            // Refund liability may include later due collections; count only receipts whose
            // source journals are already POSTED, never a mutable cumulative POS field.
            $saleSnap=json_decode((string)$saleEvent->snapshot,true,512,JSON_THROW_ON_ERROR);
            $cashCollected=0;
            foreach (['cash_paid','card_paid','mfs_paid','advance_used'] as $key) {
                $cashCollected+=$this->units($saleSnap['amounts'][$key]??'0');
            }
            $settled=DB::table('accounting_events as ae')
                ->join('order_due_payments as odp',function($join){
                    $join->on('ae.source_id','=','odp.id');
                })
                ->where('ae.source_type','order_due_payments')
                ->where('ae.event_type','AR_SETTLEMENT')
                ->where('ae.status','POSTED')
                ->where('odp.order_id',$order->id)
                ->select('ae.snapshot')->get();
            foreach($settled as $receipt){
                $snap=json_decode((string)$receipt->snapshot,true,512,JSON_THROW_ON_ERROR);
                foreach(['cash_paid','card_paid','mfs_paid'] as $key){
                    $cashCollected+=$this->units($snap['amounts'][$key]??'0');
                }
            }
            if ($amount>$cashCollected-$refunded) throw ValidationException::withMessages(['refund_amount'=>'Cannot refund more than the amount already collected.']); $vat=$this->units($d['vat_refund']);
            if ($amount>$this->units($order->grand_total)-$refunded || $vat>$amount) {
                throw ValidationException::withMessages(['refund_amount'=>'Refund exceeds remaining original paid invoice or VAT exceeds refund.']);
            }
            $priorVat=$this->units(DB::table('accounting_sales_refunds')->where('order_id',$order->id)->sum('vat_refund'));
            if ($priorVat+$vat>$this->units($order->vat_tax)) throw ValidationException::withMessages(['vat_refund'=>'VAT refund exceeds saved POS VAT.']);
            $lines=[];
            if ($amount>$vat) $lines[]=$this->line('4910',$amount-$vat,0,'Sales return / allowance');
            if ($vat>0) $lines[]=$this->line('2120',$vat,0,'POS VAT reversal');
            $lines[]=$this->line($this->paidFrom($d['payment_method']),0,$amount,'Verified paid refund');
            $j=$this->posting->createDraft(['journal_id'=>$this->journal('SALES'),
                'entry_date'=>$d['refund_date'],'reference'=>$d['refund_reference'],
                'narration'=>'Sales credit note: '.$d['supporting_evidence'],
                'origin'=>'SOURCE','created_by'=>auth()->id()],$lines);
            DB::table('accounting_sales_refunds')->insert([
                'order_id'=>$order->id,'refund_reference'=>$d['refund_reference'],'refund_date'=>$d['refund_date'],
                'payment_method'=>$d['payment_method'],'refund_amount'=>$this->amount($amount),
                'vat_refund'=>$this->amount($vat),'supporting_evidence'=>$d['supporting_evidence'],
                'journal_entry_id'=>$j,'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $j;
        },3);
        return redirect()->route('accounting.journals.show',$id)->with('success','Sales refund correction requires independent journal approval.');
    }
    public function booking(Request $r)
    {
        $this->can();
        $d=$r->validate(['table_booking_id'=>'required|exists:table_bookings,id','refund_reference'=>'required|string|max:150|unique:accounting_customer_deposit_refunds,refund_reference',
            'refund_date'=>'required|date','payment_method'=>'required|in:Cash,Bank,MFS',
            'amount'=>'required|numeric|gt:0','supporting_evidence'=>'required|string|min:15|max:3000']);
        $id=DB::transaction(function() use($d) {
            $deposit=DB::table('customer_deposits')->where('table_booking_id',$d['table_booking_id'])->lockForUpdate()->first();
            if (!$deposit) throw ValidationException::withMessages(['table_booking_id'=>'No captured booking advance.']);
            $bookingPosted=DB::table('accounting_events')->where('source_type','table_bookings')
                ->where('source_id',(string)$d['table_booking_id'])->where('event_type','BOOKING_ADVANCE_RECEIVED')
                ->where('status','POSTED')->exists();
            if (!$bookingPosted) throw ValidationException::withMessages(['table_booking_id'=>'Booking deposit journal must first be POSTED.']);
            $applied=$this->units(DB::table('deposit_allocations')->where('customer_deposit_id',$deposit->id)->sum('amount'));
            $refunded=$this->units(DB::table('accounting_customer_deposit_refunds')
                ->where('table_booking_id',$d['table_booking_id'])->sum('amount'));
            $amt=$this->units($d['amount']);
            if ($amt>$this->units($deposit->received_amount)-$applied-$refunded) {
                throw ValidationException::withMessages(['amount'=>'Refund exceeds unallocated deposit.']);
            }
            $j=$this->posting->createDraft(['journal_id'=>$this->journal('CASH'),
                'entry_date'=>$d['refund_date'],'reference'=>$d['refund_reference'],
                'narration'=>'Deposit refund: '.$d['supporting_evidence'],'origin'=>'SOURCE',
                'created_by'=>auth()->id()],[
                    $this->line('2110',$amt,0,'Customer deposit liability released'),
                    $this->line($this->paidFrom($d['payment_method']),0,$amt,'Cash/bank refund paid')]);
            DB::table('accounting_customer_deposit_refunds')->insert([
                'table_booking_id'=>$d['table_booking_id'],'refund_reference'=>$d['refund_reference'],
                'refund_date'=>$d['refund_date'],'payment_method'=>$d['payment_method'],
                'amount'=>$this->amount($amt),'supporting_evidence'=>$d['supporting_evidence'],
                'journal_entry_id'=>$j,'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $j;
        },3);
        return redirect()->route('accounting.journals.show',$id)->with('success','Deposit refund journal DRAFT saved.');
    }
    public function supplier(Request $r)
    {
        $this->can();
        $d=$r->validate(['purchase_id'=>'required|exists:purchases,id','stock_movement_id'=>'required|exists:stock_movements,id',
            'credit_note_reference'=>'required|string|max:150|unique:accounting_supplier_credit_notes,credit_note_reference',
            'credit_note_date'=>'required|date','credit_amount'=>'required|numeric|gt:0',
            'supporting_evidence'=>'required|string|min:15|max:3000']);
        $id=DB::transaction(function() use($d) {
            $p=DB::table('purchases')->where('id',$d['purchase_id'])->lockForUpdate()->first();
            if (!$p || $p->status!==Purchase::STATUS_RECEIVED ||
                !DB::table('vendor_payables')->where('purchase_id',$p->id)->exists()) {
                throw ValidationException::withMessages(['purchase_id'=>'Original received purchase AP not captured.']);
            }
            if (!DB::table('accounting_events')->where('source_type','purchases')
                ->where('source_id',(string)$p->id)->where('event_type','PURCHASE_RECEIVED')
                ->where('status','POSTED')->exists()) {
                throw ValidationException::withMessages(['purchase_id'=>'Original purchase journal must first be POSTED.']);
            }
            $m=DB::table('stock_movements')->where('id',$d['stock_movement_id'])->first();
            if (!$m || $m->status!=='POSTED' || $m->movement_type!==StockMovement::PURCHASE_RETURN ||
                $m->reference_type!==Purchase::class || (int)$m->reference_id!==(int)$p->id) {
                throw ValidationException::withMessages(['stock_movement_id'=>'Posted stock return must be linked to this purchase.']);
            }
            if (DB::table('accounting_supplier_credit_notes')->where('stock_movement_id',$m->id)->exists()) {
                throw ValidationException::withMessages(['stock_movement_id'=>'Return already linked to supplier credit note.']);
            }
            $costRows=DB::table('inventory_cost_movements')->where('stock_movement_id',$m->id)->get();
            $movedRows=DB::table('stock_movement_items')->where('stock_movement_id',$m->id)->count();
            $cost=0;foreach($costRows as $row) $cost+=$this->units($row->verified_cost);
            if ($movedRows===0 || $costRows->count()!==$movedRows ||
                DB::table('negative_inventory_cost_pending')->where('stock_movement_id',$m->id)->exists()) {
                throw ValidationException::withMessages(['stock_movement_id'=>'Unverified stock return cost must be reconciled first.']);
            }
            $amount=$this->units($d['credit_amount']);
            if ($cost!==$amount) throw ValidationException::withMessages(['credit_amount'=>'Vendor credit and verified returned stock value must exactly match.']);
            $payed=$this->units(DB::table('payable_allocations as a')->join('vendor_payables as v','a.vendor_payable_id','=','v.id')
                ->where('v.purchase_id',$p->id)->sum('a.amount'));
            $withheld=$this->units(DB::table('accounting_withholdings')->where('purchase_id',$p->id)->sum('amount'));
            $credited=$this->units(DB::table('accounting_supplier_credit_notes')->where('purchase_id',$p->id)->sum('credit_amount'));
            if ($amount>$this->units($p->total)-$payed-$withheld-$credited) {
                throw ValidationException::withMessages(['credit_amount'=>'Credit exceeds available supplier payable. Vendor cash refund requires a separate approved receipt.']);
            }
            $j=$this->posting->createDraft(['journal_id'=>$this->journal('PUR'),
                'entry_date'=>$d['credit_note_date'],'reference'=>$d['credit_note_reference'],
                'narration'=>'Supplier return credit: '.$d['supporting_evidence'],'origin'=>'SOURCE',
                'created_by'=>auth()->id()],[
                    $this->line('2100',$amount,0,'Vendor AP reduced by credit note'),
                    $this->line('1300',0,$amount,'Store goods returned')]);
            DB::table('accounting_supplier_credit_notes')->insert(['purchase_id'=>$p->id,
                'stock_movement_id'=>$m->id,'credit_note_reference'=>$d['credit_note_reference'],
                'credit_note_date'=>$d['credit_note_date'],'credit_amount'=>$this->amount($amount),
                'supporting_evidence'=>$d['supporting_evidence'],'journal_entry_id'=>$j,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $j;
        },3);
        return redirect()->route('accounting.journals.show',$id)->with('success','Supplier credit note drafted for approval.');
    }
}
