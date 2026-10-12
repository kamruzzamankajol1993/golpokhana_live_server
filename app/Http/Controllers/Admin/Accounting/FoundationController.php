<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class FoundationController extends Controller
{
    private function canDo(string $permission): void { abort_unless(auth()->user()->can($permission),403); }

    public function dashboard()
    {
        $this->canDo('accounting-view');
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        $metrics=[
            'accounts'=>DB::table('chart_of_accounts')->count(),
            'drafts'=>DB::table('journal_entries')->whereIn('status',['DRAFT','SUBMITTED','APPROVED'])->count(),
            'posted'=>DB::table('journal_entries')->where('status','POSTED')->count(),
            'pending'=>DB::table('accounting_events')->whereNotIn('status',['POSTED'])->count(),
        ];
        $balance=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
            ->where('j.status','POSTED')->selectRaw('COALESCE(SUM(l.debit),0) AS dr, COALESCE(SUM(l.credit),0) AS cr')->first();
        return view('admin.accounting.dashboard',compact('settings','metrics','balance'));
    }
    public function settings()
    {
        $this->canDo('accounting-settings');
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        $history=DB::table('accounting_setting_histories')->orderByDesc('id')->limit(30)->get();
        $accounts=DB::table('chart_of_accounts')->where('is_active',true)->where('is_header',false)->orderBy('code')->get();
        return view('admin.accounting.settings',compact('settings','history','accounts'));
    }
    public function saveSettings(Request $r)
    {
        $this->canDo('accounting-settings');
        $data=$r->validate(['capture_enabled'=>'required|boolean','posting_mode'=>'required|in:manual,auto',
            'locked_until'=>'nullable|date','rounding_account_id'=>'nullable|exists:chart_of_accounts,id',
            'valuation_method'=>'required|in:weighted_average','reason'=>'required|string|min:8|max:500']);
        DB::transaction(function () use($data) {
            $old=DB::table('accounting_settings')->where('id',1)->lockForUpdate()->first();
            if (!$old) throw ValidationException::withMessages(['settings'=>'Accounting migration has not been run.']);
            $new=[
                'capture_enabled'=>(int)$data['capture_enabled'],
                'posting_mode'=>$data['posting_mode'],
                'locked_until'=>$data['locked_until'] ?? null,
                'rounding_account_id'=>$data['rounding_account_id'] ?? null,
                'valuation_method'=>$data['valuation_method'],
            ];
            if (!empty($new['rounding_account_id'])) {
                $a=DB::table('chart_of_accounts')->find($new['rounding_account_id']);
                if (!$a || !$a->is_active || $a->is_header) throw ValidationException::withMessages(['rounding_account_id'=>'Select a valid active posting account.']);
            }
            if ($new['capture_enabled'] && !$old->capture_enabled) {
                // Strict start fence: no historical operational transaction auto backfill.
                $new['activated_at']=now();
            }
            foreach ($new as $field=>$value) {
                $oldValue=$old->$field;
                if ((string)($oldValue ?? '') !== (string)($value ?? '')) {
                    DB::table('accounting_setting_histories')->insert([
                        'user_id'=>auth()->id(),'field'=>$field,'old_value'=>(string)($oldValue ?? ''),
                        'new_value'=>(string)($value ?? ''),'reason'=>$data['reason'],'changed_at'=>now(),
                    ]);
                }
            }
            DB::table('accounting_settings')->where('id',1)->update($new+['updated_by'=>auth()->id(),'updated_at'=>now()]);
        });
        return back()->with('success','Accounting settings saved with audit history.');
    }

    public function accounts()
    {
        $this->canDo('accounting-view');
        $accounts=DB::table('chart_of_accounts')->orderBy('code')->get();
        return view('admin.accounting.accounts',compact('accounts'));
    }
    public function saveAccount(Request $r)
    {
        $this->canDo('accounting-coa');
        $data=$r->validate([
            'id'=>'nullable|integer|exists:chart_of_accounts,id','code'=>'required|string|max:30',
            'name'=>'required|string|max:150','type'=>'required|in:asset,liability,equity,income,contra_revenue,cogs,expense',
            'normal_balance'=>'required|in:debit,credit','parent_id'=>'nullable|exists:chart_of_accounts,id',
            'is_header'=>'required|boolean','is_active'=>'required|boolean','is_control'=>'required|boolean',
            'description'=>'nullable|string|max:1000',
        ]);
        DB::transaction(function () use($data) {
            $id=$data['id'] ?? null; unset($data['id']);
            if (DB::table('chart_of_accounts')->where('code',$data['code'])->when($id,fn($q)=>$q->where('id','!=',$id))->exists()) {
                throw ValidationException::withMessages(['code'=>'Account code is already used.']);
            }
            if ($id && ($id==($data['parent_id'] ?? null))) throw ValidationException::withMessages(['parent_id'=>'Account cannot be its own parent.']);
            if ($data['parent_id']) {
                $p=DB::table('chart_of_accounts')->find($data['parent_id']);
                if (!$p || !$p->is_header || !$p->is_active || $p->type!==$data['type'])
                    throw ValidationException::withMessages(['parent_id'=>'Parent must be an active header of the same type.']);
                $cursor=$p;
                while ($id && $cursor) {
                    if ((int)$cursor->id===(int)$id) throw ValidationException::withMessages(['parent_id'=>'Circular account hierarchy.']);
                    $cursor=$cursor->parent_id ? DB::table('chart_of_accounts')->find($cursor->parent_id) : null;
                }
            }
            if ($id) {
                $oldAccount=DB::table('chart_of_accounts')->find($id);
                $posted=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
                    ->where('l.account_id',$id)->where('j.status','POSTED')->exists();
                if ($posted) {
                    $existing=DB::table('chart_of_accounts')->find($id);
                    foreach (['code','type','normal_balance','is_header','is_control','parent_id'] as $f) {
                        if ((string)($existing->$f ?? '') !== (string)($data[$f] ?? ''))
                            throw ValidationException::withMessages(['account'=>'Financial classification of a posted account is immutable.']);
                    }
                }
                if ($data['is_header'] && DB::table('journal_lines')->where('account_id',$id)->exists())
                    throw ValidationException::withMessages(['account'=>'An account with journal lines cannot become a header.']);
                if (!$data['is_header'] && DB::table('chart_of_accounts')->where('parent_id',$id)->exists())
                    throw ValidationException::withMessages(['account'=>'A parent cannot become a posting account.']);
                DB::table('chart_of_accounts')->where('id',$id)->update($data+['updated_at'=>now()]);
                \App\Services\Accounting\AccountingAuditService::record('account',$id,'UPDATE',(array)$oldAccount,$data);
            } else {
                $newId=DB::table('chart_of_accounts')->insertGetId($data+['created_at'=>now(),'updated_at'=>now()]);
                \App\Services\Accounting\AccountingAuditService::record('account',$newId,'CREATE',null,$data);
            }
        });
        return back()->with('success','Account saved.');
    }

    public function periods()
    {
        $this->canDo('accounting-view');
        $years=DB::table('fiscal_years')->orderByDesc('starts_on')->get();
        $periods=DB::table('accounting_periods')->orderByDesc('starts_on')->get();
        return view('admin.accounting.periods',compact('years','periods'));
    }
    public function createYear(Request $r)
    {
        $this->canDo('accounting-period');
        $data=$r->validate(['name'=>'required|string|max:60|unique:fiscal_years,name',
            'starts_on'=>'required|date','ends_on'=>'required|date|after:starts_on']);
        DB::transaction(function () use($data) {
            $start=Carbon::parse($data['starts_on'])->startOfDay(); $end=Carbon::parse($data['ends_on'])->startOfDay();
            if (DB::table('fiscal_years')->where('starts_on','<=',$end)->where('ends_on','>=',$start)->exists())
                throw ValidationException::withMessages(['starts_on'=>'Fiscal years must not overlap.']);
            $id=DB::table('fiscal_years')->insertGetId($data+['status'=>'OPEN','created_at'=>now(),'updated_at'=>now()]);
            $cursor=$start->copy();
            while ($cursor->lte($end)) {
                $last=$cursor->copy()->endOfMonth()->startOfDay(); if ($last->gt($end)) $last=$end->copy();
                DB::table('accounting_periods')->insert(['fiscal_year_id'=>$id,'name'=>$cursor->format('M Y'),
                    'starts_on'=>$cursor->toDateString(),'ends_on'=>$last->toDateString(),'status'=>'OPEN',
                    'created_at'=>now(),'updated_at'=>now()]);
                $cursor=$last->copy()->addDay();
            }
        });
        return back()->with('success','Fiscal year and monthly periods created.');
    }
    public function changePeriod(Request $r, int $id)
    {
        $this->canDo('accounting-period');
        $d=$r->validate(['status'=>'required|in:OPEN,SOFT_CLOSED,CLOSED',
            'reason'=>'required|string|min:8|max:2000']);
        app(\App\Services\Accounting\AccountingCloseService::class)
            ->changePeriod($id,$d['status'],$d['reason'],auth()->id());
        return back()->with('success','Period change saved with finance checklist and audit.');
    }

    public function mappings()
    {
        $this->canDo('accounting-view');
        $mappings=DB::table('account_mappings as m')->join('accounting_journals as j','j.id','=','m.journal_id')
            ->select('m.*','j.code as journal_code')->orderBy('m.source_type')->get();
        $journals=DB::table('accounting_journals')->where('is_active',true)->get();
        $accounts=DB::table('chart_of_accounts')->where('is_active',true)->where('is_header',false)->orderBy('code')->get();
        $rules=DB::table('account_mapping_rules')->orderBy('sort_order')->get()->groupBy('mapping_id');
        return view('admin.accounting.mappings',compact('mappings','journals','accounts','rules'));
    }
    public function saveMapping(Request $r)
    {
        $this->canDo('accounting-mapping');
        $d=$r->validate(['id'=>'nullable|exists:account_mappings,id','name'=>'required|string|max:150',
            'source_type'=>'required|alpha_dash|max:70','event_type'=>'required|alpha_dash|max:80',
            'journal_id'=>'required|exists:accounting_journals,id','is_active'=>'required|boolean',
            'requires_approval'=>'required|boolean',
            'rules'=>'required|array|min:2|max:30','rules.*.role'=>'required|string|max:70',
            'rules.*.direction'=>'required|in:debit,credit',
            'rules.*.account_id'=>'required|exists:chart_of_accounts,id',
            'rules.*.amount_key'=>'required|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/|max:80']);
        DB::transaction(function () use($d) {
            $id=$d['id'] ?? null; unset($d['id']); $rules=$d['rules']; unset($d['rules']);
            $j=DB::table('accounting_journals')->find($d['journal_id']);
            if (!$j || !$j->is_active) throw ValidationException::withMessages(['journal'=>'Invalid journal.']);
            $other=DB::table('account_mappings')->where('source_type',$d['source_type'])->where('event_type',$d['event_type'])
                ->when($id,fn($q)=>$q->where('id','!=',$id))->exists();
            if ($other) throw ValidationException::withMessages(['source_type'=>'Event mapping exists already.']);
            foreach ($rules as $line) {
                $account=DB::table('chart_of_accounts')->find($line['account_id']);
                if (!$account || !$account->is_active || $account->is_header)
                    throw ValidationException::withMessages(['rules'=>'Mapping needs active posting accounts.']);
            }
            $oldMapping=$id ? ['mapping'=>(array)DB::table('account_mappings')->find($id),
                'rules'=>DB::table('account_mapping_rules')->where('mapping_id',$id)->get()->toArray()] : null;
            if ($id) DB::table('account_mappings')->where('id',$id)->update($d+['updated_at'=>now()]);
            else $id=DB::table('account_mappings')->insertGetId($d+['created_at'=>now(),'updated_at'=>now()]);
            DB::table('account_mapping_rules')->where('mapping_id',$id)->delete();
            foreach ($rules as $i=>$line) DB::table('account_mapping_rules')->insert($line+[
                'mapping_id'=>$id,'sort_order'=>$i+1,'created_at'=>now(),'updated_at'=>now(),
            ]);
            \App\Services\Accounting\AccountingAuditService::record('mapping',$id,$oldMapping?'UPDATE':'CREATE',
                $oldMapping,['mapping'=>$d,'rules'=>$rules]);
        });
        return back()->with('success','Multi-line mapping saved. Changes do not rewrite posted journals.');
    }
    public function journalTypes()
    {
        $this->canDo('accounting-view');
        $journals=DB::table('accounting_journals')->orderBy('code')->get();
        return view('admin.accounting.journal-types',compact('journals'));
    }
    public function saveJournalType(Request $r)
    {
        $this->canDo('accounting-settings');
        $d=$r->validate(['id'=>'nullable|exists:accounting_journals,id',
            'code'=>'required|alpha_dash|max:12','name'=>'required|string|max:100',
            'type'=>'required|in:sales,purchase,cash,bank,miscellaneous,adjustment,depreciation',
            'is_active'=>'required|boolean']);
        DB::transaction(function () use($d) {
            $id=$d['id'] ?? null;unset($d['id']);
            if (DB::table('accounting_journals')->where('code',$d['code'])->when($id,fn($q)=>$q->where('id','!=',$id))->exists())
                throw ValidationException::withMessages(['code'=>'Journal prefix already exists.']);
            if ($id) {
                $old=DB::table('accounting_journals')->where('id',$id)->lockForUpdate()->first();
                if ($old->code!==$d['code'] && DB::table('journal_entries')->where('journal_id',$id)->exists())
                    throw ValidationException::withMessages(['code'=>'Cannot change a journal code after it has entries.']);
                DB::table('accounting_journals')->where('id',$id)->update($d+['updated_at'=>now()]);
                \App\Services\Accounting\AccountingAuditService::record('journal_series',$id,'UPDATE',(array)$old,$d);
            } else {
                $id=DB::table('accounting_journals')->insertGetId($d+['created_at'=>now(),'updated_at'=>now()]);
                \App\Services\Accounting\AccountingAuditService::record('journal_series',$id,'CREATE',null,$d);
            }
        });
        return back()->with('success','Journal series saved.');
    }
    public function audit(Request $r)
    {
        $this->canDo('accounting-view');
        $audits=DB::table('accounting_audit_logs')->orderByDesc('id')->paginate(30);
        return view('admin.accounting.audit',compact('audits'));
    }

}
