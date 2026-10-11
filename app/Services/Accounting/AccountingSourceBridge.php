<?php
namespace App\Services\Accounting;

use App\Models\{Order,OrderDuePayment,TableBooking,Purchase,VendorPayment,StockMovement};
use Illuminate\Support\Facades\{DB,Log,Schema};
use Throwable;

/** Bridges CURRENT Main-RMS finalized records into V2.1. Never changes source amounts. */
final class AccountingSourceBridge
{
    /** Schedule after the LAST surrounding operational transaction commits; never during a rollback. */
    public static function schedule(string $type, int $id): void
    {
        try {
            if (!$id || !Schema::hasTable('accounting_settings') ||
                !DB::table('accounting_settings')->where('id',1)->where('capture_enabled',true)->exists()) return;
            $callback=static function () use ($type,$id): void {
                try { app(self::class)->process($type,$id); }
                catch (Throwable $e) { Log::error('Accounting source adapter exception', ['source'=>$type,'id'=>$id,'error'=>$e->getMessage()]); }
            };
            if (DB::transactionLevel()>0) DB::afterCommit($callback);
            else $callback();
        } catch (Throwable $e) {
            Log::warning('Accounting source scheduling skipped', ['source'=>$type,'id'=>$id,'error'=>$e->getMessage()]);
        }
    }

    public function __construct(private AccountingEventService $events,
                                private AccountingInventoryCostService $inventory) {}

    public function process(string $type, int $id): void
    {
        // Second fence catches OFF between saving a source and invoking the callback.
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        if (!$settings || !$settings->capture_enabled || !$settings->activated_at) return;
        match ($type) {
            'order' => $this->order($id),
            'due' => $this->due($id),
            'booking' => $this->booking($id),
            'purchase' => $this->purchase($id),
            'vendor_payment' => $this->vendorPayment($id),
            'stock_movement' => $this->inventory->captureMovement($id),
            'payroll_item' => app(AccountingPayrollBridge::class)->approvedItem($id),
            'payroll_payment' => app(AccountingPayrollBridge::class)->paidItem($id),
            default => null,
        };
    }

    private function money(mixed $amount): int
    {
        return AccountingMoney::units((string)($amount ?? '0'));
    }
    private function fmt(int $units): string
    {
        if ($units<0) throw new \InvalidArgumentException('Negative payment or source amount; review the operational record.');
        return AccountingMoney::format($units);
    }
    private function capture(string $source,int $id,string $kind,string $date,array $amounts,
                             array $metadata=[],?string $businessDate=null,int $version=1,?string $invalidReason=null): ?int
    {
        $total=0;
        foreach ($amounts as $key=>$amount) {
            $u=$this->money($amount); $amounts[$key]=$this->fmt($u); $total=max($total,$u);
        }
        $snapshot=['total'=>$this->fmt($total),'amounts'=>$amounts,'source'=>$metadata];
        if ($invalidReason) $snapshot['invalid_reason']=$invalidReason;
        return $this->events->capture($source,$id,$kind,$version,$date,$businessDate,$snapshot);
    }

    private function order(int $id): void
    {
        $o=Order::query()->find($id);
        if (!$o || strcasecmp((string)$o->status,'Completed')!==0) return;
        $grand=$this->money($o->grand_total); $vat=$this->money($o->vat_tax);
        $service=$this->money($o->service_charge); $delivery=$this->money($o->delivery_charge);
        $discount=$this->money($o->discount_amount)+$this->money($o->product_discount_amount);
        $cash=$this->money($o->paid_in_cash); $card=$this->money($o->paid_in_card);
        $mfs=$this->money($o->paid_in_mfc); $due=$this->money($o->due);
        $advance=$this->money($o->booking_advance);
        $subtotal=$this->money($o->subtotal);
        $rounding=$grand+$discount-($subtotal+$vat+$service+$delivery);
        $paid=$cash+$card+$mfs+$due+$advance;
        $errors=[];
        if ($grand!==$paid) $errors[]='Saved paid + due + advance do not equal saved grand total.';
        // Compare saved pre-discount subtotal when available; never silently change invoice calculations.
        if (abs($rounding)>10000) {
            $errors[]='POS amount difference exceeds BDT 1.0000; verify saved tax-inclusive rule, delivery charge and discounts.';
        }
        if ($advance>0 && !$o->table_booking_id) $errors[]='Unlinked advance; no verified customer deposit.';
        if ($advance>0 && $o->table_booking_id && !DB::table('customer_deposits')->where('table_booking_id',$o->table_booking_id)->exists()) {
            $errors[]='Booking deposit not captured (perhaps during Capture OFF); do not credit twice.';
        }
        if ($grand===0 && $vat===0 && $service===0 && $delivery===0 && $discount===0 && $this->money($o->tips_amount)===0) return;
        $when=(string)($o->updated_at ?? $o->order_time ?? now());
        $amounts=['cash_paid'=>$this->fmt($cash),'card_paid'=>$this->fmt($card),'mfs_paid'=>$this->fmt($mfs),
            'ar_due'=>$this->fmt($due),'advance_used'=>$this->fmt($advance),'discount_total'=>$this->fmt($discount),
            'sales_gross'=>$this->fmt($subtotal),'vat'=>$this->fmt($vat),
            'service'=>$this->fmt($service),'delivery'=>$this->fmt($delivery),
            'rounding_dr'=>$this->fmt(max(0,-$rounding)),
            'rounding_cr'=>$this->fmt(max(0,$rounding))];
        $event=$this->capture('orders',$id,'SALES_COMPLETED',$when,$amounts,
            ['order_number'=>$o->order_number,'customer_id'=>$o->customer_id,'booking_id'=>$o->table_booking_id,
             'saved_grand_total'=>$this->fmt($grand),'saved_subtotal'=>$this->fmt($this->money($o->subtotal)),
             'payment_type'=>$o->payment_type,'pos_order_time'=>(string)$o->order_time],
            null,1,$errors ? implode(' ',$errors) : null);
        if (!$event) return;
        // These are tracking records only; only POSTED journal_lines change official GL.
        if ($due>0 && !$errors) DB::table('customer_receivables')->insertOrIgnore([
            'order_id'=>$id,'customer_id'=>$o->customer_id,'original_due'=>$this->fmt($due),
            'created_at'=>now(),'updated_at'=>now()]);
        if ($advance>0 && $o->table_booking_id) {
            $deposit=DB::table('customer_deposits')->where('table_booking_id',$o->table_booking_id)->first();
            if ($deposit && $this->money($deposit->received_amount)>=$advance) {
                DB::table('deposit_allocations')->insertOrIgnore(['customer_deposit_id'=>$deposit->id,
                    'order_id'=>$id,'amount'=>$this->fmt($advance),'created_at'=>now(),'updated_at'=>now()]);
            }
        }
        if ($this->money($o->tips_amount)>0) {
            $tip=$this->fmt($this->money($o->tips_amount));
            // POS captures tips separately from grand total: never fold it into sales.
            $this->capture('orders',$id,'TIPS_RECEIVED',$when,['cash_tips'=>$tip,'tips_payable'=>$tip],
                ['order_number'=>$o->order_number,'tip_payment_policy'=>'Cash; accountant review required']);
        }
    }

    private function due(int $id): void
    {
        $p=OrderDuePayment::query()->find($id);
        if (!$p || !$p->order_id) return;
        $amount=$this->money($p->amount);
        $cash=$this->money($p->paid_in_cash); $card=$this->money($p->paid_in_card);
        $mfs=$this->money($p->paid_in_mfc);
        $invalid=null;
        if ($amount!==$cash+$card+$mfs) {
            // Historical single-method rows may lack breakdown; use SAVED type only.
            if ($cash+$card+$mfs===0) {
                $method=strtolower(trim((string)$p->payment_type));
                if ($method==='cash') $cash=$amount;
                elseif (in_array($method,['card','bank','bank / card'],true)) $card=$amount;
                elseif (in_array($method,['mfs','mobile banking'],true)) $mfs=$amount;
                else $invalid='Due payment split is incomplete/unclassified.';
            } else $invalid='Due payment breakdown does not reconcile with saved amount.';
        }
        $receivable=DB::table('customer_receivables')->where('order_id',$p->order_id)->first();
        if (!$receivable) $invalid='Original AR was not captured during an active Accounting Capture window.';
        $when=(string)($p->paid_at ?? $p->created_at ?? now());
        $event=$this->capture('order_due_payments',$id,'AR_SETTLEMENT',$when,
            ['cash_paid'=>$this->fmt($cash),'card_paid'=>$this->fmt($card),'mfs_paid'=>$this->fmt($mfs),
             'ar_cleared'=>$this->fmt($amount)],
            ['order_id'=>$p->order_id,'method'=>$p->payment_type,'reference'=>$p->transaction_reference],
            null,1,$invalid);
        if ($event && $receivable && !$invalid) DB::table('receivable_allocations')->insertOrIgnore([
            'receivable_id'=>$receivable->id,'order_due_payment_id'=>$id,'amount'=>$this->fmt($amount),
            'created_at'=>now(),'updated_at'=>now()]);
    }

    private function booking(int $id): void
    {
        $b=TableBooking::query()->find($id);
        if (!$b) return;
        $amount=$this->money($b->advance_amount);
        if ($amount===0) return;
        $cash=$this->money($b->advance_paid_in_cash);
        $card=$this->money($b->advance_paid_in_card);
        $mfs=$this->money($b->advance_paid_in_mfs);
        $previous=DB::table('customer_deposits')->where('table_booking_id',$id)->first();
        if ($previous) {
            // A booking edit does NOT mean a second receipt: never auto-record a delta as cash.
            if ($this->money($previous->received_amount)!==$amount) {
                $this->capture('table_bookings',$id,'BOOKING_ADVANCE_CHANGED',(string)$b->updated_at,
                    ['old_advance'=>$previous->received_amount,'new_advance'=>$this->fmt($amount)],
                    ['booking_id'=>$b->booking_id],null,1,
                    'Booking advance edited after receipt. Accountant must verify receipt/refund and issue correction.');
            }
            return;
        }
        $invalid=$cash+$card+$mfs!==$amount ? 'Booking advance breakdown does not equal the saved receipt.' : null;
        $event=$this->capture('table_bookings',$id,'BOOKING_ADVANCE_RECEIVED',(string)$b->created_at,
            ['cash_paid'=>$this->fmt($cash),'card_paid'=>$this->fmt($card),'mfs_paid'=>$this->fmt($mfs),
             'advance_received'=>$this->fmt($amount)],
            ['booking_id'=>$b->booking_id,'customer_id'=>$b->customer_id,'reference'=>$b->advance_payment_reference],
            null,1,$invalid);
        if ($event && !$invalid) DB::table('customer_deposits')->insertOrIgnore([
            'table_booking_id'=>$id,'customer_id'=>$b->customer_id,'received_amount'=>$this->fmt($amount),
            'created_at'=>now(),'updated_at'=>now()]);
    }

    private function purchase(int $id): void
    {
        $p=Purchase::query()->find($id);
        if (!$p || $p->status!==Purchase::STATUS_RECEIVED || !$p->received_stock_movement_id) return;
        $total=$this->money($p->total);
        $items=DB::table('purchase_items')->where('purchase_id',$id)->get();
        $lineSum=0; foreach ($items as $item) $lineSum+=$this->money($item->line_total);
        $invalid= $total!==$lineSum ? 'GRN cost lines do not equal supplier total (discount/tax/withholding allocation must be approved).' : null;
        $date=(string)($p->received_at ?? $p->updated_at);
        $event=$this->capture('purchases',$id,'PURCHASE_RECEIVED',$date,
            ['inventory_received'=>$this->fmt($total),'ap_created'=>$this->fmt($total)],
            ['purchase_no'=>$p->purchase_no,'vendor_id'=>$p->vendor_id,'grn'=>$p->grn,
                'stock_movement_id'=>$p->received_stock_movement_id,'line_sum'=>$this->fmt($lineSum),
                'tax'=>$p->tax,'discount'=>$p->discount],null,1,$invalid);
        if ($event && !$invalid) DB::table('vendor_payables')->insertOrIgnore([
            'purchase_id'=>$id,'vendor_id'=>$p->vendor_id,'original_amount'=>$this->fmt($total),
            'created_at'=>now(),'updated_at'=>now()]);
    }

    private function vendorPayment(int $id): void
    {
        $p=VendorPayment::query()->find($id); if (!$p) return;
        $amount=$this->money($p->amount); $cash=$this->money($p->paid_in_cash);
        $card=$this->money($p->paid_in_card); $mfs=$this->money($p->paid_in_mfs);
        $payable=DB::table('vendor_payables')->where('purchase_id',$p->purchase_id)->first();
        $invalid=$amount!==$cash+$card+$mfs ? 'Vendor payment split does not match total.' : null;
        if (!$payable) $invalid='Purchase AP was not captured. Vendor payment cannot clear unknown GL payable.';
        $event=$this->capture('vendor_payments',$id,'AP_SETTLEMENT',(string)$p->created_at,
            ['ap_cleared'=>$this->fmt($amount),'cash_paid'=>$this->fmt($cash),
             'card_paid'=>$this->fmt($card),'mfs_paid'=>$this->fmt($mfs)],
            ['vendor_id'=>$p->vendor_id,'purchase_id'=>$p->purchase_id,'payment_no'=>$p->payment_no,
             'card_reference'=>$p->card_reference,'mfs_reference'=>$p->mfs_reference],
             (string)$p->payment_date,1,$invalid);
        if ($event && $payable && !$invalid) DB::table('payable_allocations')->insertOrIgnore([
            'vendor_payable_id'=>$payable->id,'vendor_payment_id'=>$id,'amount'=>$this->fmt($amount),
            'created_at'=>now(),'updated_at'=>now()]);
    }
}
