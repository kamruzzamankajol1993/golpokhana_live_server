<?php
namespace App\Services\Accounting;

use App\Models\{Order,OrderDuePayment,TableBooking,Purchase,VendorPayment,StockMovement};
use Illuminate\Support\Facades\{DB,Log,Schema};
use Throwable;

/** Bridges CURRENT Main-RMS finalized records into V2.1. Never changes source amounts. */
final class AccountingSourceBridge
{
    /** Schedule after the LAST surrounding operational transaction commits; never during a rollback. */
    public static function schedule(string $type, int $id, array $context = []): void
    {
        try {
            if (!$id || !Schema::hasTable('accounting_settings') ||
                !DB::table('accounting_settings')->where('id',1)->where('capture_enabled',true)->exists()) return;
            $callback=static function () use ($type,$id,$context): void {
                try { app(self::class)->process($type,$id,$context); }
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

    public function process(string $type, int $id, array $context = []): void
    {
        // Second fence catches OFF between saving a source and invoking the callback.
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        if (!$settings || !$settings->capture_enabled || !$settings->activated_at) return;
        match ($type) {
            'order' => $this->order($id),
            'order_edit' => $this->order($id,true),
            'due' => $this->due($id),
            'booking' => $this->booking($id,$context),
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

    /**
     * Capture a financial sale version without double-counting independently recorded Due Payments.
     * Edits before activation must NOT backfill an older completed Order.
     */
    private function order(int $id, bool $edited = false): void
    {
        $o=Order::query()->find($id);
        if (!$o || strcasecmp((string)$o->status,'Completed')!==0) return;
        $hasCapturedSale=DB::table('accounting_events')->where('source_type','orders')
            ->where('source_id',(string)$id)->where('event_type','SALES_COMPLETED')->exists();
        if ($edited && !$hasCapturedSale) return; // Never backfill a completed OFF-window sale.
        if (!$edited && $hasCapturedSale) $edited=true; // Re-completing an old order is a new version.

        $grand=$this->money($o->grand_total); $vat=$this->money($o->vat_tax);
        $service=$this->money($o->service_charge); $delivery=$this->money($o->delivery_charge);
        $discount=$this->money($o->discount_amount)+$this->money($o->product_discount_amount);
        $cash=$this->money($o->paid_in_cash); $card=$this->money($o->paid_in_card);
        $mfs=$this->money($o->paid_in_mfc); $due=$this->money($o->due);
        $advance=$this->money($o->booking_advance);
        $subtotal=$this->money($o->subtotal);
        $errors=[];
        // Due settlement already generates its own AR_SETTLEMENT entry. Order's cumulative
        // paid/due fields are reversed back to the original sale allocation before comparing versions.
        $laterDueTotal=0;
        foreach (OrderDuePayment::query()->where('order_id',$id)->get() as $p) {
            $amount=$this->money($p->amount);
            $pc=$this->money($p->paid_in_cash); $pk=$this->money($p->paid_in_card);
            $pm=$this->money($p->paid_in_mfc);
            if (!$pc && !$pk && !$pm) {
                $method=strtolower(trim((string)$p->payment_type));
                if ($method==='cash') $pc=$amount;
                elseif (in_array($method,['card','bank','bank / card'],true)) $pk=$amount;
                elseif (in_array($method,['mfs','mobile banking'],true)) $pm=$amount;
            }
            if ($pc+$pk+$pm!==$amount) {
                $errors[]='Due-payment breakdown does not reconcile; manual correction required.';
                continue;
            }
            $cash-=$pc; $card-=$pk; $mfs-=$pm;
            $due+=$amount; $laterDueTotal+=$amount;
        }
        if ($cash<0 || $card<0 || $mfs<0) {
            $errors[]='Edited order paid allocation is smaller than recorded Due settlements; review refund/payment adjustment.';
        }
        $rounding=$grand+$discount-($subtotal+$vat+$service+$delivery);
        if ($grand!==$cash+$card+$mfs+$due+$advance) {
            $errors[]='Saved paid + due + advance do not equal saved grand total.';
        }
        if (abs($rounding)>10000) {
            $errors[]='POS amount difference exceeds BDT 1.0000; verify saved tax-inclusive rule and discounts.';
        }
        if ($advance>0 && !$o->table_booking_id) $errors[]='Unlinked advance; no verified customer deposit.';
        if ($advance>0 && $o->table_booking_id && !DB::table('customer_deposits')
            ->where('table_booking_id',$o->table_booking_id)->exists()) {
            $errors[]='Booking deposit not captured (perhaps during Capture OFF); do not credit twice.';
        }
        if ($grand===0 && $vat===0 && $service===0 && $delivery===0 && $discount===0
            && $this->money($o->tips_amount)===0 && !$edited) return;
        $when=(string)($o->updated_at ?? $o->order_time ?? now());
        // A negative post-due normalization becomes a REVIEW exception, never an unhandled
        // negative-number exception that would silently drop the edit.
        $fmt=static fn (int $n) => AccountingMoney::format($n);
        $amounts=['cash_paid'=>$fmt($cash),'card_paid'=>$fmt($card),'mfs_paid'=>$fmt($mfs),
            'ar_due'=>$fmt($due),'advance_used'=>$fmt($advance),'discount_total'=>$fmt($discount),
            'sales_gross'=>$fmt($subtotal),'vat'=>$fmt($vat),'service'=>$fmt($service),
            'delivery'=>$fmt($delivery),'rounding_dr'=>$fmt(max(0,-$rounding)),
            'rounding_cr'=>$fmt(max(0,$rounding))];
        $meta=['order_number'=>$o->order_number,'customer_id'=>$o->customer_id,
            'booking_id'=>$o->table_booking_id,'saved_grand_total'=>$fmt($grand),
            'saved_subtotal'=>$fmt($subtotal),'payment_type'=>$o->payment_type,
            'pos_order_time'=>(string)$o->order_time,'due_collections_excluded'=>$fmt($laterDueTotal)];
        $event=$this->syncOrderFinancialEvent($id,'SALES_COMPLETED','SALES_CORRECTION',$when,
            $amounts,$meta,$errors,$edited);
        if (!$event && !$edited) return;
        // Subledger is a tracking record only, never a posted GL balance.
        if ($due>0 && !$errors) DB::table('customer_receivables')->insertOrIgnore([
            'order_id'=>$id,'customer_id'=>$o->customer_id,'original_due'=>$fmt($due),
            'created_at'=>now(),'updated_at'=>now()]);
        if ($advance>0 && $o->table_booking_id) {
            $deposit=DB::table('customer_deposits')->where('table_booking_id',$o->table_booking_id)->first();
            if ($deposit && $this->money($deposit->received_amount)>=$advance) {
                DB::table('deposit_allocations')->insertOrIgnore(['customer_deposit_id'=>$deposit->id,
                    'order_id'=>$id,'amount'=>$fmt($advance),'created_at'=>now(),'updated_at'=>now()]);
            }
        }
        $tips=$this->money($o->tips_amount);
        if ($tips>0 || $edited) {
            $tipAmounts=['cash_tips'=>$fmt($tips),'tips_payable'=>$fmt($tips)];
            $this->syncOrderFinancialEvent($id,'TIPS_RECEIVED','TIPS_CORRECTION',$when,
                $tipAmounts,['order_number'=>$o->order_number,'tip_payment_policy'=>'Cash; accountant review required'],
                [],$edited);
        }
    }

    /** Full snapshot versions replace unposted drafts; posted journals are adjusted by signed deltas. */
    private function syncOrderFinancialEvent(int $id, string $originalType, string $correctionType,
                                             string $when, array $full, array $metadata,
                                             array $errors, bool $edited): ?int
    {
        return DB::transaction(function () use ($id,$originalType,$correctionType,$when,$full,$metadata,$errors,$edited) {
            DB::table('orders')->where('id',$id)->lockForUpdate()->first();
            $events=DB::table('accounting_events')->where('source_type','orders')->where('source_id',(string)$id)
                ->whereIn('event_type',[$originalType,$correctionType])->orderBy('source_version')
                ->lockForUpdate()->get();
            if (!$edited) {
                // Normal completion is version 1 only. Do not mistake later source saves for new sales.
                if ($events->isNotEmpty()) return null;
                return $this->captureFinancialSnapshot($id,$originalType,1,$when,$full,$full,$metadata,$errors);
            }
            $latest=$events->last();
            if ($latest) {
                $lastSnap=json_decode((string)$latest->snapshot,true);
                $lastFull=$lastSnap['source']['full_amounts'] ?? $lastSnap['amounts'] ?? [];
                $sameIssue=($lastSnap['invalid_reason'] ?? null) === ($errors ? implode(' ',array_unique($errors)) : null);
                if ($latest->status!=='SUPERSEDED' && $lastFull===$full && $sameIssue) return null;
            } elseif ($originalType==='SALES_COMPLETED' || array_sum(array_map('floatval',$full))===0.0) {
                return null;
            }
            // Each unposted generation is permanently stale when the financial source changes.
            foreach ($events as $event) {
                if ($event->status!=='POSTED' && $event->status!=='SUPERSEDED') {
                    DB::table('accounting_events')->where('id',$event->id)->update([
                        'status'=>'SUPERSEDED', 'last_error'=>'Superseded by a newer saved Order version; do not post this draft.',
                        'updated_at'=>now(),
                    ]);
                }
            }
            $posted=$events->filter(fn ($e)=>$e->status==='POSTED')->last();
            $version=($latest ? (int)$latest->source_version : 0)+1;
            if (!$posted) {
                // No posted financial effect exists to undo; do not leave a zero-line draft.
                if (!array_filter($full,fn ($v)=>$this->signedMoneyUnits((string)$v)!==0)) return null;
                return $this->captureFinancialSnapshot($id,$originalType,$version,$when,$full,$full,
                    $metadata+['baseline_version'=>0],$errors);
            }
            $baseSnapshot=json_decode((string)$posted->snapshot,true);
            $base=$baseSnapshot['source']['full_amounts'] ?? $baseSnapshot['amounts'] ?? [];
            $delta=[];$nonzero=false;
            foreach ($full as $key=>$value) {
                $difference=$this->signedMoneyUnits((string)$value)
                    - $this->signedMoneyUnits((string)($base[$key] ?? '0'));
                $delta[$key]=AccountingMoney::format($difference);
                if ($difference!==0) $nonzero=true;
            }
            if (!$nonzero) return null;
            return $this->captureFinancialSnapshot($id,$correctionType,$version,$when,$delta,$full,
                $metadata+['baseline_version'=>(int)$posted->source_version,
                           'original_event_id'=>$posted->id,'corrects_event_id'=>$latest?->id],$errors);
        },3);
    }

    private function signedMoneyUnits(string $value): int
    {
        return str_starts_with($value,'-')
            ? -AccountingMoney::units(substr($value,1)) : AccountingMoney::units($value);
    }

    private function captureFinancialSnapshot(int $id, string $kind, int $version, string $date,
                                              array $amounts, array $full, array $metadata,
                                              array $errors): ?int
    {
        $max=0;
        foreach ($amounts as $amount) $max=max($max,abs($this->signedMoneyUnits((string)$amount)));
        $snapshot=['total'=>AccountingMoney::format($max),'amounts'=>$amounts,
                   'source'=>$metadata+['full_amounts'=>$full]];
        if ($errors) $snapshot['invalid_reason']=implode(' ',array_unique($errors));
        return $this->events->capture('orders',$id,$kind,$version,$date,null,$snapshot);
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

    private function booking(int $id, array $context = []): void
    {
        $b=TableBooking::query()->find($id);
        if (!$b) return;
        $amount=$this->money($b->advance_amount);
        if ($amount===0) return;
        $cash=$this->money($b->advance_paid_in_cash);
        $card=$this->money($b->advance_paid_in_card);
        $mfs=$this->money($b->advance_paid_in_mfs);
        $previous=DB::table('customer_deposits')->where('table_booking_id',$id)->first();
        $previousAmount=$this->money($context['previous_advance'] ?? '0');
        $date=(string)($b->updated_at ?? $b->created_at ?? now());
        if ($previous || $previousAmount>0) {
            // An advance change after any earlier receipt is NOT another verified cash transaction.
            // For pre-activation deposits a review event is safer than a silent backfill.
            if (($previous && $this->money($previous->received_amount)!==$amount)
                || (!$previous && $previousAmount!==$amount)) {
                $this->capture('table_bookings',$id,'BOOKING_ADVANCE_CHANGED',$date,
                    ['old_advance'=>$this->fmt($previous ? $this->money($previous->received_amount) : $previousAmount),
                     'new_advance'=>$this->fmt($amount)],
                    ['booking_id'=>$b->booking_id,'requires_receipt_verification'=>true],null,1,
                    'Advance changed after a prior receipt; accountant must verify refund/additional collection.');
            }
            return;
        }
        $invalid=$cash+$card+$mfs!==$amount ? 'Booking advance breakdown does not equal the saved receipt.' : null;
        // An old Booking with zero initial advance receives fresh money *now*, not when booked.
        $event=$this->capture('table_bookings',$id,'BOOKING_ADVANCE_RECEIVED',$date,
            ['cash_paid'=>$this->fmt($cash),'card_paid'=>$this->fmt($card),'mfs_paid'=>$this->fmt($mfs),
             'advance_received'=>$this->fmt($amount)],
            ['booking_id'=>$b->booking_id,'customer_id'=>$b->customer_id,
             'reference'=>$b->advance_payment_reference,'previous_advance'=>$this->fmt($previousAmount)],
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
                'tax'=>$p->tax,'discount'=>$p->discount,
                'tds_rate_snapshot'=>$p->tds_rate_snapshot,'vds_rate_snapshot'=>$p->vds_rate_snapshot],null,1,$invalid);
        if ($event && !$invalid) DB::table('vendor_payables')->insertOrIgnore([
            'purchase_id'=>$id,'vendor_id'=>$p->vendor_id,'original_amount'=>$this->fmt($total),
            'created_at'=>now(),'updated_at'=>now()]);
    }

    private function vendorPayment(int $id): void
    {
        $p=VendorPayment::query()->find($id); if (!$p) return;
        $amount=$this->money($p->amount); $cash=$this->money($p->paid_in_cash);
        $card=$this->money($p->paid_in_card); $mfs=$this->money($p->paid_in_mfs);
        $tds=$this->money($p->tds_amount ?? 0); $vds=$this->money($p->vds_amount ?? 0);
        $payable=DB::table('vendor_payables')->where('purchase_id',$p->purchase_id)->first();
        $invalid=$amount!==$cash+$card+$mfs+$tds+$vds ? 'Vendor gross AP payment does not reconcile to net paid plus withholding.' : null;
        if (!$payable) $invalid='Purchase AP was not captured. Vendor payment cannot clear unknown GL payable.';
        $event=$this->capture('vendor_payments',$id,'AP_SETTLEMENT',(string)$p->created_at,
            ['ap_cleared'=>$this->fmt($amount),'cash_paid'=>$this->fmt($cash),
             'card_paid'=>$this->fmt($card),'mfs_paid'=>$this->fmt($mfs),
             'tds_withheld'=>$this->fmt($tds),'vds_withheld'=>$this->fmt($vds)],
            ['vendor_id'=>$p->vendor_id,'purchase_id'=>$p->purchase_id,'payment_no'=>$p->payment_no,
             'card_reference'=>$p->card_reference,'mfs_reference'=>$p->mfs_reference,
             'tds_rate_snapshot'=>$p->tds_rate_snapshot,'vds_rate_snapshot'=>$p->vds_rate_snapshot,
             'gross_ap_settled'=>$p->amount,'withholding_base'=>$p->withholding_base],
             (string)$p->payment_date,1,$invalid);
        if ($event && $payable && !$invalid) DB::table('payable_allocations')->insertOrIgnore([
            'vendor_payable_id'=>$payable->id,'vendor_payment_id'=>$id,'amount'=>$this->fmt($amount),
            'created_at'=>now(),'updated_at'=>now()]);
    }
}
