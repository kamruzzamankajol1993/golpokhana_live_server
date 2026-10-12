<?php
namespace App\Services\Accounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Phase-2+ source hooks call capture() only after the operational transaction is finalized. */
final class AccountingEventService
{
    public function __construct(private JournalPostingService $posting) {}

    /** Null = capture OFF, pre-activation event or no valid source. Never replays OFF-window history. */
    public function capture(string $sourceType, string|int $sourceId, string $eventType,
                            int $version, string $occurredAt, ?string $businessDate,
                            array $snapshot): ?int
    {
        try {
            if (!$sourceType || !$sourceId || !$eventType || $version<1) return null;
            $occurred=Carbon::parse($occurredAt);
            $id=DB::transaction(function () use ($sourceType,$sourceId,$eventType,$version,$occurred,$businessDate,$snapshot) {
                $settings=DB::table('accounting_settings')->where('id',1)->lockForUpdate()->first();
                if (!$settings || !$settings->capture_enabled || !$settings->activated_at ||
                    $occurred->lessThan(Carbon::parse($settings->activated_at)) || $occurred->greaterThan(now()->addMinutes(5))) return null;
                // Approved cutover forbids historical source capture even when an older
                // operational record is edited after activation. No backfill through this API.
                $cutover=DB::getSchemaBuilder()->hasTable('accounting_cutovers')
                    ? DB::table('accounting_cutovers')->where('status','ACTIVE')->first() : null;
                if ($cutover) {
                    $cutoff=Carbon::parse($cutover->starts_on)->startOfDay();
                    if ($occurred->lessThan($cutoff) || ($businessDate && Carbon::parse($businessDate)->lessThan($cutoff))) return null;
                    $tables=['orders','purchases','vendor_payments','order_due_payments',
                        'table_bookings','payroll_items','payroll_payments','stock_movements'];
                    if (in_array($sourceType,$tables,true) && ctype_digit((string)$sourceId)
                        && DB::getSchemaBuilder()->hasColumn($sourceType,'created_at')) {
                        $original=DB::table($sourceType)->where('id',$sourceId)->value('created_at');
                        // The first advance added to an older zero-advance booking is a NEW receipt,
                    // not historical booking backfill. It must have an auditable 0 -> positive transition.
                    $newBookingReceipt=$sourceType==='table_bookings'
                        && $eventType==='BOOKING_ADVANCE_RECEIVED'
                        && (string)($snapshot['source']['previous_advance'] ?? '') !== ''
                        && AccountingMoney::units((string)$snapshot['source']['previous_advance'])===0;
                    if ($original && Carbon::parse($original)->lessThan($cutoff) && !$newBookingReceipt) return null;
                    }
                }

                $key=hash('sha256', implode('|',[$sourceType,(string)$sourceId,$eventType,$version]));
                $amount=AccountingMoney::format(AccountingMoney::units((string)($snapshot['total'] ?? 0)));
                $created=DB::table('accounting_events')->insertOrIgnore([
                    'event_key'=>$key,'source_type'=>$sourceType,'source_id'=>(string)$sourceId,
                    'event_type'=>$eventType,'source_version'=>$version,'occurred_at'=>$occurred,
                    'business_date'=>$businessDate,'amount'=>$amount,'snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),
                    'status'=>empty($snapshot['invalid_reason']) ? 'PENDING' : 'MAPPING_ERROR',
                    'last_error'=>empty($snapshot['invalid_reason']) ? null : substr((string)$snapshot['invalid_reason'],0,3000),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                return [(int)DB::table('accounting_events')->where('event_key',$key)->value('id'),$created > 0];
            },3);
            if (!$id) return null;
            [$id,$newlyCaptured]=$id;
            // Never mutate source POS/Purchase data on mapping/posting exceptions.
            $settings=DB::table('accounting_settings')->where('id',1)->first();
            if ($newlyCaptured && empty($snapshot['invalid_reason']) && empty($snapshot['manual_only']) && $settings && $settings->capture_enabled && $settings->posting_mode==='auto') {
                try { $this->generate($id,true,null); }
                catch (Throwable $e) { Log::warning('Accounting automatic posting queued for review',['event_id'=>$id,'reason'=>$e->getMessage()]); }
            }
            return $id;
        } catch (Throwable $e) {
            // No event/attempt record while capture OFF. Operational save must not be blocked.
            Log::error('Accounting capture failed',['source_type'=>$sourceType,'source_id'=>$sourceId,'error'=>$e->getMessage()]);
            return null;
        }
    }

    public function generate(int $eventId, bool $post, ?int $actor): int
    {
        try {
            $result=DB::transaction(function () use ($eventId,$post,$actor) {
                $event=DB::table('accounting_events')->where('id',$eventId)->lockForUpdate()->first();
                if (!$event) throw ValidationException::withMessages(['event'=>'Accounting event not found.']);
                if (!empty(json_decode($event->snapshot,true)['invalid_reason'])) {
                    throw ValidationException::withMessages(['event'=>'Source reconciliation exception must be resolved before journal generation.']);
                }
                if ($event->status==='SUPERSEDED') {
                    throw ValidationException::withMessages(['event'=>'This event was superseded by a later Order version and cannot be generated.']);
                }
                if ($event->journal_entry_id) {
                    // Exactly once: a repeat request gets its existing draft/posting.
                    return (int)$event->journal_entry_id;
                }
                if ($post && $actor===null) {
                    $settings=DB::table('accounting_settings')->where('id',1)->lockForUpdate()->first();
                    if (!$settings || !$settings->capture_enabled || $settings->posting_mode!=='auto') {
                        throw ValidationException::withMessages(['event'=>'Automatic posting is not enabled.']);
                    }
                }
                // Corrections use the SAME approved mapping as the original sale/tip;
                // avoid a separate configuration that could diverge from the live POS rules.
                $mappingType=match ($event->event_type) {
                    'SALES_CORRECTION'=>'SALES_COMPLETED',
                    'TIPS_CORRECTION'=>'TIPS_RECEIVED',
                    default=>$event->event_type,
                };
                $mapping=DB::table('account_mappings')->where('source_type',$event->source_type)
                    ->where('event_type',$mappingType)->where('is_active',true)->first();
                if (!$mapping) throw ValidationException::withMessages(['mapping'=>'No active approved account mapping.']);
                $snapshot=json_decode($event->snapshot,true,512,JSON_THROW_ON_ERROR);
                $amounts=$snapshot['amounts'] ?? [];
                $rules=DB::table('account_mapping_rules')->where('mapping_id',$mapping->id)->orderBy('sort_order')->get();
                $lines=[];
                foreach ($rules as $rule) {
                    if (!array_key_exists($rule->amount_key,$amounts)) {
                        throw ValidationException::withMessages(['mapping'=>'Missing saved amount: '.$rule->amount_key]);
                    }
                    $raw=(string)$amounts[$rule->amount_key];
                    $correction=in_array($event->event_type,['SALES_CORRECTION','TIPS_CORRECTION'],true);
                    if (!$correction && str_starts_with($raw,'-')) {
                        throw ValidationException::withMessages(['event'=>'Negative amounts are only allowed in versioned correction events.']);
                    }
                    $negative=$correction && str_starts_with($raw,'-');
                    $units=AccountingMoney::units($negative ? substr($raw,1) : $raw);
                    if ($units===0) continue;
                    $debit=($rule->direction==='debit') !== $negative;
                    $lines[]=['account_id'=>$rule->account_id,'debit'=>$debit ? AccountingMoney::format($units) : '0',
                        'credit'=>!$debit ? AccountingMoney::format($units) : '0',
                        'allow_control'=>true,'memo'=>$rule->role];
                }
                // Direct vendor withholding is an inseparable part of the same AP_SETTLEMENT,
                // NOT a second unlinked accounting withholding transaction.
                if ($event->event_type==='AP_SETTLEMENT') {
                    foreach (['tds_withheld'=>'2141','vds_withheld'=>'2142'] as $key=>$code) {
                        $units=AccountingMoney::units((string)($amounts[$key] ?? '0'));
                        if (!$units) continue;
                        if ($rules->contains('amount_key',$key)) continue; // approved custom mapping already handles it
                        $account=DB::table('chart_of_accounts')->where('code',$code)
                            ->where('is_active',true)->where('is_header',false)->value('id');
                        if (!$account) throw ValidationException::withMessages(['mapping'=>'Required '.$code.' withholding liability account is missing.']);
                        $lines[]=['account_id'=>(int)$account,'debit'=>'0',
                            'credit'=>AccountingMoney::format($units),'allow_control'=>true,'memo'=>$key];
                    }
                }
                $this->posting->assertLines($lines);
                $id=$this->posting->createDraft(['journal_id'=>$mapping->journal_id,
                    'entry_date'=>Carbon::parse($event->occurred_at)->toDateString(),
                    'business_date'=>$event->business_date,'reference'=>$event->source_type.':'.$event->source_id,
                    'narration'=>'Source '.$event->event_type.' (version '.$event->source_version.')',
                    'created_by'=>$actor],$lines,(int)$event->id);
                DB::table('accounting_events')->where('id',$eventId)->update([
                    'journal_entry_id'=>$id,'status'=>'DRAFT','last_error'=>null,'updated_at'=>now(),
                ]);
                DB::table('accounting_event_attempts')->insert(['accounting_event_id'=>$eventId,
                    'action'=>'GENERATE','result'=>'SUCCESS','user_id'=>$actor,
                    'created_at'=>now(),'updated_at'=>now()]);
                return $id;
            },3);
            if ($post) {
                $sourceEvent=DB::table('accounting_events')->where('id',$eventId)->first();
                $policyType=match ($sourceEvent->event_type) {
                    'SALES_CORRECTION'=>'SALES_COMPLETED',
                    'TIPS_CORRECTION'=>'TIPS_RECEIVED',
                    default=>$sourceEvent->event_type,
                };
                $policy=DB::table('account_mappings')->where('source_type',$sourceEvent->source_type)
                    ->where('event_type',$policyType)->first();
                if ($policy && $policy->requires_approval) return $result;
                // A failed post leaves a draft for explicit correction, never posts partial lines.
                $journal=DB::table('journal_entries')->where('id',$result)->first();
                if ($journal && $journal->status==='DRAFT') {
                    $this->posting->post($result,$actor,true);
                }
            }
            return $result;
        } catch (Throwable $e) {
            // Retry state and trace are written separately so they survive the failed generation transaction.
            $event=DB::table('accounting_events')->where('id',$eventId)->first();
            if ($event && !in_array($event->status,['POSTED','SUPERSEDED'],true)) {
                DB::table('accounting_events')->where('id',$eventId)->update([
                    'status'=>$event->journal_entry_id ? 'DRAFT' : 'MAPPING_ERROR',
                    'last_error'=>substr($e->getMessage(),0,3000),'updated_at'=>now(),
                ]);
                DB::table('accounting_event_attempts')->insert(['accounting_event_id'=>$eventId,
                    'action'=>'GENERATE','result'=>'FAILED','message'=>substr($e->getMessage(),0,3000),
                    'user_id'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            }
            throw $e;
        }
    }
}
