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
