<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\AccountingEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalController extends Controller
{
    public function __construct(private JournalPostingService $posting, private AccountingEventService $events) {}
    private function permission(string $p): void { abort_unless(auth()->user()->can($p),403); }
    public function index(Request $r)
    {
        $this->permission('accounting-view');
        $query=DB::table('journal_entries as e')->join('accounting_journals as j','j.id','=','e.journal_id')
            ->select('e.*','j.name as journal_name','j.code as journal_code')->orderByDesc('e.id');
        if ($r->filled('status')) $query->where('e.status',$r->status);
        $entries=$query->paginate(20)->withQueryString();
        return view('admin.accounting.journals',compact('entries'));
    }
    private function formData(): array
    {
        return [
            'journals'=>DB::table('accounting_journals')->where('is_active',true)->orderBy('code')->get(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_active',true)->where('is_header',false)->orderBy('code')->get(),
            'costCenters'=>DB::table('cost_centers')->where('is_active',true)->orderBy('code')->get(),
        ];
    }
    public function create()
    {
        $this->permission('accounting-journal-create');
        return view('admin.accounting.journal-form',$this->formData()+['entry'=>null,'lines'=>collect()]);
    }
    public function edit(int $id)
    {
        $this->permission('accounting-journal-create');
        $entry=DB::table('journal_entries')->find($id);
        abort_unless($entry && $entry->status==='DRAFT',404);
        $lines=DB::table('journal_lines')->where('journal_entry_id',$id)->orderBy('sort_order')->get();
        return view('admin.accounting.journal-form',$this->formData()+compact('entry','lines'));
    }
    private function validated(Request $r): array
    {
        return $r->validate(['journal_id'=>'required|exists:accounting_journals,id',
            'entry_date'=>'required|date_format:Y-m-d','reference'=>'nullable|string|max:100',
            'narration'=>'required|string|max:2000','lines'=>'required|array|min:2|max:100',
            'lines.*.account_id'=>'required|exists:chart_of_accounts,id',
            'lines.*.debit'=>'required|numeric|min:0','lines.*.credit'=>'required|numeric|min:0',
            'lines.*.memo'=>'nullable|string|max:500',
            'lines.*.cost_center_id'=>'nullable|exists:cost_centers,id']);
    }
    public function store(Request $r)
    {
        $this->permission('accounting-journal-create');
        $d=$this->validated($r); $id=$this->posting->createDraft($d,$d['lines']);
        return redirect()->route('accounting.journals.show',$id)->with('success','Journal DRAFT saved (not in GL).');
    }
    public function update(Request $r,int $id)
    {
        $this->permission('accounting-journal-create');
        $d=$this->validated($r); $this->posting->updateDraft($id,$d,$d['lines']);
        return redirect()->route('accounting.journals.show',$id)->with('success','Draft updated.');
    }
    public function show(int $id)
    {
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
        return view('admin.accounting.journal-show',compact('entry','lines','approvals','attachments','reverse'));
    }
    public function action(Request $r,int $id)
    {
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
    }
    public function upload(Request $r,int $id)
    {
        $this->permission('accounting-journal-create');
        $r->validate(['attachment'=>'required|file|max:5120|mimes:pdf,jpg,jpeg,png']);
        $entry=DB::table('journal_entries')->find($id);
        abort_unless($entry && $entry->status==='DRAFT',403);
        $file=$r->file('attachment'); $path=$file->store('accounting/journals','public');
        DB::table('journal_attachments')->insert(['journal_entry_id'=>$id,
            'filename'=>$file->getClientOriginalName(),'path'=>$path,'uploaded_by'=>auth()->id(),
            'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Supporting document saved.');
    }
    public function attachment(int $id)
    {
        $this->permission('accounting-view');
        $attachment=DB::table('journal_attachments')->find($id); abort_unless($attachment,404);
        abort_unless(DB::table('journal_entries')->where('id',$attachment->journal_entry_id)->exists(),404);
        $disk=\Illuminate\Support\Facades\Storage::disk('public');
        abort_unless($disk->exists($attachment->path),404);
        return $disk->download($attachment->path,basename($attachment->filename));
    }
    public function events(Request $r)
    {
        $this->permission('accounting-event-view');
        $q=DB::table('accounting_events')->orderByDesc('id');
        if ($r->filled('status')) $q->where('status',$r->status);
        if ($r->filled('source_type')) $q->where('source_type','like','%'.$r->source_type.'%');
        $events=$q->paginate(20)->withQueryString();
        return view('admin.accounting.events',compact('events'));
    }
    public function generate(Request $r,int $id)
    {
        $this->permission('accounting-event-process');
        $journalId=$this->events->generate($id,false,auth()->id());
        return redirect()->route('accounting.journals.show',$journalId)
            ->with('success','Journal draft created. Submit, approve and post separately.');
    }
    public function trialBalance()
    {
        $this->permission('accounting-view');
        $rows=DB::table('chart_of_accounts as a')->join('journal_lines as l','l.account_id','=','a.id')
            ->join('journal_entries as e','e.id','=','l.journal_entry_id')->where('e.status','POSTED')
            ->groupBy('a.id','a.code','a.name','a.normal_balance')
            ->selectRaw('a.code,a.name,a.normal_balance,SUM(l.debit) as debit_total,SUM(l.credit) as credit_total')
            ->orderBy('a.code')->get();
        return view('admin.accounting.trial-balance',compact('rows'));
    }
}
