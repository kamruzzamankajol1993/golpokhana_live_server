<?php
namespace App\Http\Controllers\Admin\Accounting\PendingEvents;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\AccountingEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** PendingEvents accounting module. A shared base provides posting dependencies and invariant checks. */
final class PendingEventsController extends \App\Http\Controllers\Admin\Accounting\Shared\JournalController
{
    public function events(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-event-view');
        $q=DB::table('accounting_events')->orderByDesc('id');
        if ($r->filled('status')) $q->where('status',$r->status);
        if ($r->filled('source_type')) $q->where('source_type','like','%'.$r->source_type.'%');
        $events=$q->paginate(20)->withQueryString();
        return view('admin.accounting.pending-events.index',compact('events'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    /** Generate one independent DRAFT per selected source, never an implicit GL post. */
    public function bulkGenerate(Request $r)
    {
        $this->permission('accounting-event-process');
        $data=$r->validate([
            'event_ids'=>'required|array|min:1|max:100',
            'event_ids.*'=>'required|integer|distinct|min:1',
        ]);
        $ids=array_map('intval',$data['event_ids']);
        $existing=DB::table('accounting_events')->whereIn('id',$ids)->pluck('id')->map(fn ($v)=>(int)$v)->all();
        $known=array_fill_keys($existing,true);
        $ok=0;$failed=[];$skipped=0;
        foreach ($ids as $id) {
            if (!isset($known[$id])) { $failed[]='#'.$id.': not found'; continue; }
            $event=DB::table('accounting_events')->where('id',$id)->first();
            if (!$event || !in_array($event->status,['PENDING','MAPPING_ERROR'],true) || $event->journal_entry_id) {
                $skipped++; continue;
            }
            try {
                $this->events->generate($id,false,auth()->id());
                $ok++;
            } catch (\Throwable $e) {
                $failed[]='#'.$id.': '.\Illuminate\Support\Str::limit($e->getMessage(),140);
            }
        }
        $summary=$ok.' draft(s) created, '.$skipped.' already linked/ineligible, '.count($failed).' failed.';
        return redirect()->route('accounting.events', $r->only(['status','source_type']))
            ->with('success',$summary)
            ->with('bulk_generate_errors',array_slice($failed,0,30));
    }

    /** Fast-path only where mapping policy does NOT require approval. */
    public function generatePost(Request $r,int $id)
    {
        $this->permission('accounting-event-process');
        $this->permission('accounting-journal-post');
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        if (!$settings || !$settings->capture_enabled)
            throw ValidationException::withMessages(['capture'=>'Accounting Capture is OFF.']);
        $event=DB::table('accounting_events')->where('id',$id)->first();
        if (!$event || !in_array($event->status,['PENDING','MAPPING_ERROR','DRAFT'],true))
            throw ValidationException::withMessages(['event'=>'Event is not eligible for generation.']);
        $mappingType=match($event->event_type) {
            'SALES_CORRECTION'=>'SALES_COMPLETED','TIPS_CORRECTION'=>'TIPS_RECEIVED',
            default=>$event->event_type,
        };
        $mapping=DB::table('account_mappings')->where('source_type',$event->source_type)
            ->where('event_type',$mappingType)->where('is_active',true)->first();
        if (!$mapping) throw ValidationException::withMessages(['mapping'=>'Active source mapping is required.']);
        // Never claim a fast post for an approval-required mapping.
        if ($mapping->requires_approval) {
            $draftId=$this->events->generate($id,false,auth()->id());
            return redirect()->route('accounting.journals.show',$draftId)
                ->with('success','Mandatory independent approval: Draft created, not posted.');
        }
        $journalId=$this->events->generate($id,true,auth()->id());
        $journal=DB::table('journal_entries')->find($journalId);
        return redirect()->route('accounting.journals.show',$journalId)
            ->with('success',$journal?->status==='POSTED'?'Source journal POSTED with authorization.':'Draft ready for review.');
    }

    public function generate(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-event-process');
        $journalId=$this->events->generate($id,false,auth()->id());
        return redirect()->route('accounting.journals.show',$journalId)
            ->with('success','Journal draft created. Submit, approve and post separately.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
