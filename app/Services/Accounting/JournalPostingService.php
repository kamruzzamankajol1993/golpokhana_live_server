<?php
namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use RuntimeException;

final class JournalPostingService
{
    public function assertOpenPeriod(string $date, bool $lock = false): object
    {
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        if ($settings && $settings->locked_until && $date <= $settings->locked_until) {
            throw ValidationException::withMessages(['date'=>'Date is restricted by accounting lock date.']);
        }
        $query=DB::table('accounting_periods')->where('starts_on','<=',$date)->where('ends_on','>=',$date);
        $period=$lock ? $query->lockForUpdate()->first() : $query->first();
        if (!$period || $period->status !== 'OPEN') {
            throw ValidationException::withMessages(['date'=>'No OPEN accounting period covers the selected date.']);
        }
        return $period;
    }

    public function assertLines(array $lines): void
    {
        if (count($lines)<2) throw ValidationException::withMessages(['lines'=>'At least two journal lines are required.']);
        $debits=0;$credits=0;
        foreach ($lines as $line) {
            $account=DB::table('chart_of_accounts')->where('id',$line['account_id'] ?? 0)->first();
            if (!$account || !$account->is_active || $account->is_header) {
                throw ValidationException::withMessages(['account'=>'Journal lines must use active posting (leaf) accounts.']);
            }
            // Prevent arbitrary manual posting to AR/AP/control accounts until approved subledger policy.
            if ($account->is_control && !($line['allow_control'] ?? false)) {
                throw ValidationException::withMessages(['account'=>'Control account requires an approved source-linked mapping.']);
            }
            if (!empty($line['cost_center_id']) && !DB::table('cost_centers')->where('id',$line['cost_center_id'])->where('is_active',true)->exists()) {
                throw ValidationException::withMessages(['cost_center_id'=>'Choose an active restaurant cost center.']);
            }
            $dr=AccountingMoney::units($line['debit'] ?? '0');
            $cr=AccountingMoney::units($line['credit'] ?? '0');
            if (($dr>0 && $cr>0) || ($dr===0 && $cr===0)) {
                throw ValidationException::withMessages(['lines'=>'Exactly one positive debit or credit is required per line.']);
            }
            $debits+=$dr; $credits+=$cr;
        }
        if ($debits!==$credits || $debits===0) {
            throw ValidationException::withMessages(['lines'=>'Total debits and credits must exactly match and be positive.']);
        }
    }

    public function createDraft(array $payload, array $lines, ?int $eventId = null): int
    {
        return DB::transaction(function () use ($payload,$lines,$eventId) {
            $date=Carbon::parse($payload['entry_date'])->toDateString();
            $period=$this->assertOpenPeriod($date, true);
            $journal=DB::table('accounting_journals')->where('id',$payload['journal_id'])->where('is_active',true)->first();
            if (!$journal) throw ValidationException::withMessages(['journal'=>'Journal is inactive or missing.']);
            $this->assertLines($lines);
            if ($eventId && DB::table('journal_entries')->where('accounting_event_id',$eventId)->exists()) {
                throw ValidationException::withMessages(['event'=>'Source event already linked to a journal.']);
            }
            $id=DB::table('journal_entries')->insertGetId([
                'journal_id'=>$journal->id,'period_id'=>$period->id,'entry_date'=>$date,
                'business_date'=>$payload['business_date'] ?? null,
                'origin'=>$eventId ? 'SOURCE' : ($payload['origin'] ?? 'MANUAL'),
                'accounting_event_id'=>$eventId,'status'=>'DRAFT',
                'reference'=>$payload['reference'] ?? null,'narration'=>$payload['narration'],
                'created_by'=>$payload['created_by'] ?? auth()->id(),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach (array_values($lines) as $i=>$line) DB::table('journal_lines')->insert([
                'journal_entry_id'=>$id,'account_id'=>$line['account_id'],'sort_order'=>$i+1,
                'debit'=>AccountingMoney::format(AccountingMoney::units($line['debit'] ?? '0')),
                'credit'=>AccountingMoney::format(AccountingMoney::units($line['credit'] ?? '0')),
                'memo'=>$line['memo'] ?? null,'cost_center_id'=>$line['cost_center_id'] ?? null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $id;
        }, 3);
    }

    public function updateDraft(int $id, array $payload, array $lines): void
    {
        DB::transaction(function () use($id,$payload,$lines) {
            $entry=DB::table('journal_entries')->where('id',$id)->lockForUpdate()->first();
            if (!$entry || $entry->status!=='DRAFT') throw ValidationException::withMessages(['journal'=>'Only DRAFT journals may be edited.']);
            if (in_array($entry->origin,['OPENING_SETTLEMENT','CLOSING'],true)) throw ValidationException::withMessages(['journal'=>'Financial settlement/year-end closing lines are immutable. Reconcile and create a controlled replacement before posting.']);
            $period=$this->assertOpenPeriod($payload['entry_date'],true);
            if (in_array($entry->origin,['SOURCE','OPENING'],true)) {
                if ($entry->origin==='OPENING' && (!auth()->user()?->can('accounting-cutover') ||
                    !DB::table('accounting_cutovers')->where('opening_journal_entry_id',$id)->where('status','PREPARED')->exists()))
                    throw ValidationException::withMessages(['journal'=>'Opening balance requires an authorized prepared cutover.']);
                foreach ($lines as &$line) $line['allow_control']=true;
            }
            unset($line);
            $this->assertLines($lines);
            if ($entry->origin==='SOURCE') {
                $original=DB::table('journal_lines')->where('journal_entry_id',$id)->get()
                    ->map(fn($l)=>AccountingMoney::units((string)$l->debit).':'.AccountingMoney::units((string)$l->credit))
                    ->sort()->values()->all();
                $incoming=collect($lines)->map(fn($l)=>AccountingMoney::units((string)($l['debit'] ?? '0')).':'.AccountingMoney::units((string)($l['credit'] ?? '0')))
                    ->sort()->values()->all();
                if ($incoming!==$original) throw ValidationException::withMessages(['lines'=>'Source-generated amounts are immutable. Only accounting classification and memo may change.']);
            }
            DB::table('journal_entries')->where('id',$id)->update([
                'period_id'=>$period->id,'entry_date'=>$payload['entry_date'],
                'reference'=>$payload['reference'] ?? null,'narration'=>$payload['narration'],
                'updated_at'=>now(),
            ]);
            DB::table('journal_lines')->where('journal_entry_id',$id)->delete();
            foreach (array_values($lines) as $i=>$line) DB::table('journal_lines')->insert([
                'journal_entry_id'=>$id,'account_id'=>$line['account_id'],'sort_order'=>$i+1,
                'debit'=>AccountingMoney::format(AccountingMoney::units($line['debit'] ?? '0')),
                'credit'=>AccountingMoney::format(AccountingMoney::units($line['credit'] ?? '0')),
                'memo'=>$line['memo'] ?? null,'cost_center_id'=>$line['cost_center_id'] ?? null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
    }

    public function transition(int $id, string $action, ?int $actor, ?string $reason = null): void
    {
        DB::transaction(function () use($id,$action,$actor,$reason) {
            $entry=DB::table('journal_entries')->where('id',$id)->lockForUpdate()->first();
            if (!$entry) throw ValidationException::withMessages(['journal'=>'Journal not found.']);
            $next=match ($action) {
                'SUBMIT' => $entry->status==='DRAFT' ? 'SUBMITTED' : null,
                'APPROVE' => $entry->status==='SUBMITTED' ? 'APPROVED' : null,
                'REJECT' => in_array($entry->status,['SUBMITTED','APPROVED'],true) ? 'DRAFT' : null,
                default=>null,
            };
            if (!$next) throw ValidationException::withMessages(['journal'=>'Invalid state transition.']);
            if ($action==='APPROVE' && $actor) {
                $submittedBy=DB::table('journal_approvals')->where('journal_entry_id',$id)
                    ->where('action','SUBMIT')->orderByDesc('id')->value('user_id');
                if ((int)$entry->created_by===$actor || (int)$submittedBy===$actor) {
                    throw ValidationException::withMessages(['journal'=>'Maker or submitter cannot approve their own journal.']);
                }
            }
            if ($action==='REJECT' && !trim((string)$reason)) {
                throw ValidationException::withMessages(['reason'=>'Rejection requires a reason.']);
            }
            $this->assertOpenPeriod($entry->entry_date, true);
            if ($action==='SUBMIT') {
                $lines=DB::table('journal_lines')->where('journal_entry_id',$id)->get()->map(fn($l)=>(array)$l)->all();
                if (in_array($entry->origin,['SOURCE','OPENING','OPENING_SETTLEMENT'],true)) {
                    foreach ($lines as &$line) $line['allow_control']=true;
                    unset($line);
                }
                $this->assertLines($lines);
            }
            DB::table('journal_entries')->where('id',$id)->update([
                'status'=>$next, 'approved_by'=>$action==='APPROVE' ? $actor : null,
                'approved_at'=>$action==='APPROVE' ? now() : null, 'updated_at'=>now(),
            ]);
            DB::table('journal_approvals')->insert(['journal_entry_id'=>$id,'action'=>$action,
                'user_id'=>$actor,'reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]);
        },3);
    }

    public function post(int $id, ?int $actor, bool $auto = false): string
    {
        return DB::transaction(function () use($id,$actor,$auto) {
            $entry=DB::table('journal_entries')->where('id',$id)->lockForUpdate()->first();
            if (!$entry) throw ValidationException::withMessages(['journal'=>'Journal not found.']);
            if ($entry->status==='POSTED') return $entry->number;
            if ($entry->status!==($auto ? 'DRAFT' : 'APPROVED')) {
                throw ValidationException::withMessages(['journal'=>'Journal must be APPROVED (or validated automatic DRAFT) before posting.']);
            }
            if ($auto && $entry->origin!=='SOURCE') {
                throw ValidationException::withMessages(['journal'=>'Automatic posting permitted only for source-generated journals.']);
            }
            $this->assertOpenPeriod($entry->entry_date,true);
            $this->assertSourceDependencyPosted($entry);
            $this->assertExtendedLedgerPosting($entry);
            if ($entry->origin==='CLOSING') $this->assertYearEndPosting($entry);
            $lines=DB::table('journal_lines')->where('journal_entry_id',$id)->get()->map(function ($l) {
                $r=(array)$l; if ($r['journal_entry_id']) $r['allow_control']=true; return $r;
            })->all();
            // Existing source-generated control lines may pass; manual journal lines must not bypass restrictions.
            if ($entry->origin==='OPENING_SETTLEMENT') {
                $allocation=DB::table('accounting_opening_allocations')->where('journal_entry_id',$id)->first();
                $item=$allocation?DB::table('accounting_opening_items')->where('id',$allocation->opening_item_id)->first():null;
                if (!$item || !DB::table('accounting_cutovers')->where('id',$item->cutover_id)->where('status','ACTIVE')->exists())
                    throw ValidationException::withMessages(['journal'=>'Opening invoice settlement requires active approved cutover.']);
                $amount=AccountingMoney::units((string)$allocation->amount);
                $lineRows=DB::table('journal_lines')->where('journal_entry_id',$id)->get();
                $code=$item->side==='AR'?'1200':'2100';
                $control=DB::table('chart_of_accounts')->where('code',$code)->value('id');
                $verified=0;$cashVerified=0;
                foreach ($lineRows as $row) {
                    $dr=AccountingMoney::units((string)$row->debit);$cr=AccountingMoney::units((string)$row->credit);
                    if ((int)$row->account_id===(int)$control) $verified+=($item->side==='AR'?$cr-$dr:$dr-$cr);
                    elseif (DB::table('accounting_cash_flow_rules')->where('account_id',$row->account_id)->where('activity','CASH')->exists())
                        $cashVerified+=($item->side==='AR'?$dr-$cr:$cr-$dr);
                    else throw ValidationException::withMessages(['journal'=>'Settlement must contain only matching AR/AP control and mapped Cash/Bank accounts.']);
                }
                if ($lineRows->count()!==2 || $verified!==$amount || $cashVerified!==$amount)
                    throw ValidationException::withMessages(['journal'=>'Opening settlement journal lines differ from reserved invoice amount.']);
            } elseif ($entry->origin==='OPENING') {
                if (!DB::table('accounting_cutovers')->where('opening_journal_entry_id',$id)->where('status','PREPARED')->exists())
                    throw ValidationException::withMessages(['journal'=>'Opening journal requires a prepared cutover record.']);
            } elseif ($entry->origin!=='SOURCE') foreach ($lines as &$line) $line['allow_control']=false;
            unset($line);
            $this->assertLines($lines);
            $journal=DB::table('accounting_journals')->where('id',$entry->journal_id)->where('is_active',true)->first();
            if (!$journal) throw ValidationException::withMessages(['journal'=>'Inactive journal.']);
            $year=(int)substr($entry->entry_date,0,4);
            DB::table('number_sequences')->insertOrIgnore(['key'=>$journal->code,'year'=>$year,
                'next_number'=>1,'created_at'=>now(),'updated_at'=>now()]);
            $sequence=DB::table('number_sequences')->where('key',$journal->code)->where('year',$year)->lockForUpdate()->first();
            $number=sprintf('%s-%d-%06d',$journal->code,$year,$sequence->next_number);
            DB::table('number_sequences')->where('id',$sequence->id)->update(['next_number'=>$sequence->next_number+1,'updated_at'=>now()]);
            DB::table('journal_entries')->where('id',$id)->update([
                'number'=>$number,'status'=>'POSTED','posted_by'=>$actor,'posted_at'=>now(),'updated_at'=>now(),
            ]);
            if ($entry->accounting_event_id) DB::table('accounting_events')->where('id',$entry->accounting_event_id)->update([
                'status'=>'POSTED','journal_entry_id'=>$id,'last_error'=>null,'updated_at'=>now(),
            ]);
            // Late negative-cost COGS settles valuation ONLY within the same atomic POST transaction.
            // Draft/submission leaves verified inventory balances untouched.
            if ($entry->accounting_event_id && DB::getSchemaBuilder()->hasTable('negative_inventory_cost_pending')) {
                $costRow=DB::table('negative_inventory_cost_pending')
                    ->where('accounting_event_id',$entry->accounting_event_id)->lockForUpdate()->first();
                if ($costRow) {
                    if ($costRow->status!=='WAITING_POST' || !$costRow->verified_cost)
                        throw ValidationException::withMessages(['cost'=>'Cost verification is not awaiting posting.']);
                    $quantity=app(\App\Services\Inventory\DecimalQuantity::class);
                    $balance=DB::table('inventory_cost_balances')
                        ->where('stock_location_id',$costRow->stock_location_id)
                        ->where('ingredient_id',$costRow->ingredient_id)->lockForUpdate()->first();
                    if (!$balance || $quantity->compare((string)$balance->verified_qty,(string)$costRow->quantity_base)<0)
                        throw ValidationException::withMessages(['cost'=>'Late cost quantity has not been financially received/assigned.']);
                    $available=AccountingMoney::units((string)$balance->verified_value);
                    $expected=$quantity->compare((string)$balance->verified_qty,(string)$costRow->quantity_base)===0
                        ? $available
                        : AccountingMoney::units($quantity->multiply(
                            $quantity->divide(AccountingMoney::format($available),(string)$balance->verified_qty,12),
                            (string)$costRow->quantity_base,4));
                    if ($expected!==AccountingMoney::units((string)$costRow->verified_cost))
                        throw ValidationException::withMessages(['cost'=>'Approved late cost differs from current verified weighted-average value.']);
                    DB::table('inventory_cost_balances')->where('id',$balance->id)->update([
                        'verified_qty'=>$quantity->subtract((string)$balance->verified_qty,(string)$costRow->quantity_base),
                        'verified_value'=>AccountingMoney::format($available-$expected),'updated_at'=>now(),
                    ]);
                    DB::table('negative_inventory_cost_pending')->where('id',$costRow->id)
                        ->update(['status'=>'POSTED','updated_at'=>now()]);
                }
            }
            // Provider receivables exist only after source journal POSTED; no duplicate income.
            if ($entry->accounting_event_id && DB::getSchemaBuilder()->hasTable('payment_account_transactions')) {
                $event=DB::table('accounting_events')->where('id',$entry->accounting_event_id)->first();
                if ($event && in_array($event->event_type,['SALES_COMPLETED','AR_SETTLEMENT','BOOKING_ADVANCE_RECEIVED'],true)) {
                    $snapshot=json_decode((string)$event->snapshot,true);
                    foreach (['card_paid'=>'Card','mfs_paid'=>'MFS'] as $key=>$method) {
                        $units=AccountingMoney::units((string)($snapshot['amounts'][$key] ?? '0'));
                        if ($units<=0) continue;
                        $paymentAccount=DB::table('payment_accounts')->where('method',$method)->where('active',true)->orderBy('id')->first();
                        if ($paymentAccount) DB::table('payment_account_transactions')->insertOrIgnore([
                            'payment_account_id'=>$paymentAccount->id,
                            'reference'=>sprintf('%s:%s:%s',$event->source_type,$event->source_id,$event->event_type),
                            'direction'=>'IN','amount'=>AccountingMoney::format($units),
                            'status'=>'UNSETTLED','accounting_event_id'=>$event->id,
                            'created_at'=>now(),'updated_at'=>now(),
                        ]);
                    }
                }
            }
            // Provider clearing is marked SETTLED only when the associated double-entry journal POSTS.
            if (DB::getSchemaBuilder()->hasTable('accounting_bank_settlements')) {
                $settlements=DB::table('accounting_bank_settlements')->where('journal_entry_id',$id)->get();
                foreach ($settlements as $settlement) {
                    DB::table('accounting_bank_settlements')->where('id',$settlement->id)
                        ->update(['status'=>'POSTED','updated_at'=>now()]);
                    DB::table('payment_account_transactions')->where('id',$settlement->payment_account_transaction_id)
                        ->update(['status'=>'SETTLED','updated_at'=>now()]);
                }
                DB::table('accounting_withholdings')->where('journal_entry_id',$id)
                    ->update(['status'=>'POSTED','updated_at'=>now()]);
            }
            // Closing register's journal state follows the official posted journal atomically.
            if (DB::getSchemaBuilder()->hasTable('accounting_closing_runs')) {
                DB::table('accounting_closing_runs')->where('journal_entry_id',$id)
                    ->where('status','DRAFT')->update(['status'=>'POSTED','updated_at'=>now()]);
            }
            if (DB::getSchemaBuilder()->hasTable('accounting_sales_refunds')) {
                foreach (['accounting_sales_refunds','accounting_supplier_credit_notes',
                          'accounting_customer_deposit_refunds'] as $table) {
                    DB::table($table)->where('journal_entry_id',$id)
                        ->update(['status'=>'POSTED','updated_at'=>now()]);
                }
            }
            if (DB::getSchemaBuilder()->hasTable('expenses')) {
                DB::table('expenses')->where('journal_entry_id',$id)->update(['status'=>'POSTED','updated_at'=>now()]);
            }
            DB::table('journal_approvals')->insert(['journal_entry_id'=>$id,'action'=>'POST',
                'user_id'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            return $number;
        },3);
    }

    /** Year-end drafts cannot be manipulated after financial review, including by editing SQL/UI.
     * Revalidate every amount against the current posted year before posting.
     */
    private function assertYearEndPosting(object $entry): void
    {
        if (!DB::getSchemaBuilder()->hasTable('accounting_closing_runs'))
            throw ValidationException::withMessages(['closing'=>'Closing register not installed.']);
        $run=DB::table('accounting_closing_runs')->where('journal_entry_id',$entry->id)
            ->where('run_type','YEAR_END')->first();
        if (!$run || $run->status!=='DRAFT')
            throw ValidationException::withMessages(['closing'=>'Year-end closing journal is not an active registered draft.']);
        $year=DB::table('fiscal_years')->where('id',$run->fiscal_year_id)->first();
        if (!$year || $entry->entry_date!==$year->ends_on)
            throw ValidationException::withMessages(['closing'=>'Year-end journal date must be fiscal year end.']);
        $report=app(AccountingReportService::class);
        $expected=[];$net=0;
        foreach($report->totals((string)$year->starts_on,(string)$year->ends_on,true) as $a) {
            if ($a['is_header'] || !in_array($a['type'],['income','contra_revenue','cogs','expense'],true)) continue;
            $value=$a['dr']-$a['cr'];if ($value===0) continue;
            $expected[]=$a['id'].':'.max(0,-$value).':'.max(0,$value);
            $net+=$value;
        }
        if ($net!==0) {
            $retained=DB::table('chart_of_accounts')->where('code','3200')->value('id');
            $expected[]=$retained.':'.max(0,$net).':'.max(0,-$net);
        }
        $actual=[];
        foreach(DB::table('journal_lines')->where('journal_entry_id',$entry->id)->get() as $line)
            $actual[]=$line->account_id.':'.AccountingMoney::units((string)$line->debit)
                .':'.AccountingMoney::units((string)$line->credit);
        sort($expected);sort($actual);
        if ($expected!==$actual || count($expected)<2)
            throw ValidationException::withMessages(['closing'=>'Posted P&L has changed or closing draft lines differ from authorized year-end transfer. Regenerate a controlled closing draft before posting.']);
    }

    /** Phase-4 register-linked journals must remain numerically identical to the approved schedule.
     * Changing a DRAFT account or BDT value must never silently corrupt a loan/asset/deferral register.
     */
    private function assertExtendedLedgerPosting(object $entry): void
    {
        $id=(int)$entry->id;
        $expected=null;
        $def=DB::getSchemaBuilder()->hasTable('accounting_deferral_lines')
            ? DB::table('accounting_deferral_lines as l')->join('accounting_deferral_schedules as s','s.id','=','l.schedule_id')
                ->where('l.journal_entry_id',$id)->select('l.amount','s.*')->first() : null;
        if ($def) {
            $v=AccountingMoney::units((string)$def->amount);
            $expected=$def->type==='PREPAYMENT'
                ? [[$def->recognition_account_id,$v,0],[$def->balance_account_id,0,$v]]
                : [[$def->balance_account_id,$v,0],[$def->recognition_account_id,0,$v]];
        }
        $dep=DB::getSchemaBuilder()->hasTable('asset_depreciations')
            ? DB::table('asset_depreciations as d')->join('fixed_assets as a','a.id','=','d.fixed_asset_id')
                ->where('d.journal_entry_id',$id)->select('d.amount','a.*')->first() : null;
        if ($dep) {
            if (!DB::table('accounting_asset_policies')->where('id',1)->where('is_approved',true)->exists())
                throw ValidationException::withMessages(['asset'=>'Depreciation policy is not currently approved.']);
            $v=AccountingMoney::units((string)$dep->amount);
            $expected=[[$dep->depreciation_expense_account_id,$v,0],
                [$dep->accumulated_depreciation_account_id,0,$v]];
        }
        $payment=DB::getSchemaBuilder()->hasTable('company_loan_payments')
            ? DB::table('company_loan_payments as p')->join('company_loans as l','l.id','=','p.company_loan_id')
                ->where('p.journal_entry_id',$id)->select('p.principal','p.interest','l.liability_account_id')->first() : null;
        if ($payment) {
            $p=AccountingMoney::units((string)$payment->principal);
            $interest=AccountingMoney::units((string)$payment->interest);
            $expected=[[$payment->liability_account_id,$p,0]];
            if ($interest>0) $expected[]=[(int)DB::table('chart_of_accounts')->where('code','6550')->value('id'),$interest,0];
            // Payment account is user-selected at initial entry. Preserve it but enforce
            // one cash/bank credit of exactly principal plus interest.
            $cashLines=DB::table('journal_lines')->where('journal_entry_id',$id)
                ->where('credit','>',0)->get();
            if($cashLines->count()!==1) throw ValidationException::withMessages(['loan'=>'Company loan payment must have exactly one cash/bank credit line.']);
            $cash=$cashLines->first();
            $gl=DB::table('chart_of_accounts')->where('id',$cash->account_id)->first();
            if(!$gl || !$gl->is_active || $gl->type!=='asset' || !in_array($gl->code,['1100','1110','1120','1150'],true))
                throw ValidationException::withMessages(['loan'=>'Repayment credit must use an approved cash/bank/MFS asset account.']);
            $expected[]=[$cash->account_id,0,$p+$interest];
        }
        if ($expected===null) return;
        $real=DB::table('journal_lines')->where('journal_entry_id',$id)->get();
        $actual=[];
        foreach($real as $l) $actual[]=[(int)$l->account_id,
            AccountingMoney::units((string)$l->debit),AccountingMoney::units((string)$l->credit)];
        $expected=array_map(fn($x)=>array_map('intval',$x),$expected);
        $sort=static function(array &$rows): void { usort($rows,fn($a,$b)=>strcmp(implode(':',$a),implode(':',$b))); };
        $sort($actual); $sort($expected);
        if($actual!==$expected) throw ValidationException::withMessages(['journal'=>'DRAFT financial lines no longer match the Phase-4 registered amounts/accounts. Create an approved replacement instead.']);
    }

    /** An AR/AP settlement must never post before its originating liability/receivable exists in GL. */
    private function assertSourceDependencyPosted(object $entry): void
    {
        if (!$entry->accounting_event_id) return;
        $event=DB::table('accounting_events')->where('id',$entry->accounting_event_id)->first();
        if (!$event) return;
        $requires=null;
        if ($event->event_type==='AR_SETTLEMENT') {
            $orderId=DB::table('order_due_payments')->where('id',$event->source_id)->value('order_id');
            if ($orderId) $requires=['orders',(string)$orderId,'SALES_COMPLETED'];
        } elseif ($event->event_type==='AP_SETTLEMENT') {
            $purchaseId=DB::table('vendor_payments')->where('id',$event->source_id)->value('purchase_id');
            if ($purchaseId) $requires=['purchases',(string)$purchaseId,'PURCHASE_RECEIVED'];
        } elseif ($event->event_type==='PAYROLL_PAID') {
            $itemId=DB::table('payroll_payments')->where('id',$event->source_id)->value('payroll_item_id');
            if ($itemId) $requires=['payroll_items',(string)$itemId,'PAYROLL_ACCRUED'];
        } elseif ($event->event_type==='SALES_COMPLETED') {
            $o=DB::table('orders')->where('id',$event->source_id)->first();
            if ($o && (float)($o->booking_advance ?? 0)>0 && $o->table_booking_id) {
                $requires=['table_bookings',(string)$o->table_booking_id,'BOOKING_ADVANCE_RECEIVED'];
            }
        }
        // A purchase may only recognize Store Inventory as a GL asset after every
        // receiving line has an independently verified cost in the valuation subledger.
        // This blocks a full-amount AP journal against an incomplete/mismatched GRN.
        if ($event->event_type==='PURCHASE_RECEIVED') {
            $p=DB::table('purchases')->where('id',$event->source_id)->first();
            $movementId=$p?->received_stock_movement_id;
            if (!$p || !$movementId) {
                throw ValidationException::withMessages(['purchase'=>'Missing posted GRN movement.']);
            }
            $movementLines=DB::table('stock_movement_items')->where('stock_movement_id',$movementId)->count();
            $verifiedRows=DB::table('inventory_cost_movements')->where('stock_movement_id',$movementId)->get();
            $verifiedTotal=0;
            foreach ($verifiedRows as $verified) $verifiedTotal+=AccountingMoney::units((string)$verified->verified_cost);
            if (!$movementLines || $verifiedRows->count()!==$movementLines ||
                DB::table('negative_inventory_cost_pending')->where('stock_movement_id',$movementId)->exists() ||
                $verifiedTotal!==AccountingMoney::units((string)$p->total)) {
                throw ValidationException::withMessages(['purchase'=>'GRN cost is not fully verified. Reconcile all receipt quantities, invoice amounts and inventory costing before posting purchase AP.']);
            }
        }
        if ($requires && !DB::table('accounting_events')
            ->where('source_type',$requires[0])->where('source_id',$requires[1])
            ->where('event_type',$requires[2])->where('status','POSTED')->exists()) {
            throw ValidationException::withMessages(['journal'=>'Originating source journal '.$requires[2].' must be POSTED before this financial effect.']);
        }
        // Source exceptions must be reviewed; an invalid saved invoice cannot be fixed by remapping only.
        $snap=json_decode((string)$event->snapshot,true);
        if (!empty($snap['invalid_reason'])) {
            throw ValidationException::withMessages(['journal'=>'Source snapshot has an unresolved monetary reconciliation exception.']);
        }
    }

    public function reverse(int $id, ?int $actor, string $date, string $reason): int
    {
        if (!trim($reason)) throw ValidationException::withMessages(['reason'=>'Reason is required.']);
        return DB::transaction(function () use ($id,$actor,$date,$reason) {
            $original=DB::table('journal_entries')->where('id',$id)->lockForUpdate()->first();
            if (!$original || $original->status!=='POSTED' || $original->reversal_of_id) {
                throw ValidationException::withMessages(['journal'=>'Only an original POSTED journal may be reversed.']);
            }
            if (DB::table('journal_entries')->where('reversal_of_id',$id)->exists()) {
                throw ValidationException::withMessages(['journal'=>'This journal has already been reversed.']);
            }
            $this->assertOpenPeriod($date,true);
            $lines=DB::table('journal_lines')->where('journal_entry_id',$id)->get()->map(fn($l)=>[
                'account_id'=>$l->account_id,'debit'=>$l->credit,'credit'=>$l->debit,
                'allow_control'=>true,'memo'=>'Reversal '.$original->number,
            ])->all();
            $reversed=$this->createDraft(['journal_id'=>$original->journal_id,'entry_date'=>$date,
                'narration'=>'REVERSAL: '.$reason,'reference'=>$original->number,
                'origin'=>'SOURCE','created_by'=>$actor],$lines);
            DB::table('journal_entries')->where('id',$reversed)->update(['reversal_of_id'=>$id]);
            // Reversals require separate checker approval; reversing is never a silent mutation.
            DB::table('journal_approvals')->insert(['journal_entry_id'=>$reversed,'action'=>'REVERSE_REQUEST',
                'user_id'=>$actor,'reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]);
            return $reversed;
        },3);
    }
}
