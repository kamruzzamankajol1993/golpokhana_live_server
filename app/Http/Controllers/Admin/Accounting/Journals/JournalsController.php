<?php
namespace App\Http\Controllers\Admin\Accounting\Journals;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\AccountingEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Journals accounting module. A shared base provides posting dependencies and invariant checks. */
final class JournalsController extends \App\Http\Controllers\Admin\Accounting\Shared\JournalController
{
    public function index(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-view');
        $query=DB::table('journal_entries as e')->join('accounting_journals as j','j.id','=','e.journal_id')
            ->select('e.*','j.name as journal_name','j.code as journal_code')->orderByDesc('e.id');
        if ($r->filled('status')) $query->where('e.status',$r->status);
        $entries=$query->paginate(20)->withQueryString();
        return view('admin.accounting.journals.index',compact('entries'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function create()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-journal-create');
        return view('admin.accounting.journals.form',$this->formData()+['entry'=>null,'lines'=>collect()]);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function edit(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-journal-create');
        $entry=DB::table('journal_entries')->find($id);
        abort_unless($entry && $entry->status==='DRAFT',404);
        $lines=DB::table('journal_lines')->where('journal_entry_id',$id)->orderBy('sort_order')->get();
        return view('admin.accounting.journals.form',$this->formData()+compact('entry','lines'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function store(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-journal-create');
        $d=$this->validated($r); $id=$this->posting->createDraft($d,$d['lines']);
        return redirect()->route('accounting.journals.show',$id)->with('success','Journal DRAFT saved (not in GL).');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function update(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-journal-create');
        $d=$this->validated($r); $this->posting->updateDraft($id,$d,$d['lines']);
        return redirect()->route('accounting.journals.show',$id)->with('success','Draft updated.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function show(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-view');
        $entry=DB::table('journal_entries as e')->join('accounting_journals as j','j.id','=','e.journal_id')
            ->select('e.*','j.name as journal_name')->where('e.id',$id)->first();
        abort_unless($entry,404);
        $lines=DB::table('journal_lines as l')->join('chart_of_accounts as a','a.id','=','l.account_id')
            ->leftJoin('cost_centers as c','c.id','=','l.cost_center_id')
            ->where('l.journal_entry_id',$id)->select('l.*','a.code','a.name as account_name','c.name as cost_center_name')->orderBy('l.sort_order')->get();
        $approvals=DB::table('journal_approvals')->where('journal_entry_id',$id)->orderByDesc('id')->get();
        $attachments=DB::table('journal_attachments')->where('journal_entry_id',$id)->get();
        $reverse=DB::table('journal_entries')->where('reversal_of_id',$id)->first();
        return view('admin.accounting.journals.show',compact('entry','lines','approvals','attachments','reverse'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function action(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $d=$r->validate(['action'=>'required|in:SUBMIT,APPROVE,REJECT,POST,REVERSE',
            'reason'=>'nullable|string|max:2000','date'=>'nullable|date_format:Y-m-d']);
        $action=$d['action'];
        $this->permission(match($action){
            'SUBMIT'=>'accounting-journal-create','APPROVE','REJECT'=>'accounting-journal-approve',
            'POST'=>'accounting-journal-post','REVERSE'=>'accounting-journal-reverse',
        });
        if ($action==='POST') $this->posting->post($id,auth()->id());
        elseif ($action==='REVERSE') {
            $new=$this->posting->reverse($id,auth()->id(),$d['date'] ?? now()->toDateString(),$d['reason'] ?? '');
            return redirect()->route('accounting.journals.show',$new)->with('success','Reversal draft created. Independent approval required.');
        } else $this->posting->transition($id,$action,auth()->id(),$d['reason'] ?? null);
        return back()->with('success','Journal action completed: '.$action);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function upload(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-journal-create');
        $r->validate(['attachment'=>'required|file|max:5120|mimes:pdf,jpg,jpeg,png']);
        $entry=DB::table('journal_entries')->find($id);
        abort_unless($entry && $entry->status==='DRAFT',403);
        $file=$r->file('attachment'); $path=$file->store('accounting/journals','public');
        DB::table('journal_attachments')->insert(['journal_entry_id'=>$id,
            'filename'=>$file->getClientOriginalName(),'path'=>$path,'uploaded_by'=>auth()->id(),
            'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Supporting document saved.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function attachment(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-view');
        $attachment=DB::table('journal_attachments')->find($id); abort_unless($attachment,404);
        abort_unless(DB::table('journal_entries')->where('id',$attachment->journal_entry_id)->exists(),404);
        $disk=\Illuminate\Support\Facades\Storage::disk('public');
        abort_unless($disk->exists($attachment->path),404);
        return $disk->download($attachment->path,basename($attachment->filename));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function deleteDraft(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->permission('accounting-journal-create');
        app(\App\Services\Accounting\AccountingSafeDeletionService::class)->draftJournal($id);
        return back()->with('success','Unused draft/master record deleted. Accounting audit history retained.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

}
