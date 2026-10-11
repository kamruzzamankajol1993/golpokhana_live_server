<?php
namespace App\Http\Controllers\Admin\Accounting\JournalTypes;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** JournalTypes accounting module. A shared base provides posting dependencies and invariant checks. */
final class JournalTypesController extends \App\Http\Controllers\Admin\Accounting\Shared\FoundationController
{
    public function journalTypes()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-view');
        $journals = DB::table('accounting_journals as j')->select('j.*')
            ->selectSub(DB::table('account_mappings')->selectRaw('COUNT(*)')->whereColumn('journal_id', 'j.id'), 'mapping_count')
            ->selectSub(DB::table('account_mappings')->selectRaw('COUNT(*)')->whereColumn('journal_id', 'j.id')->where('is_active', 1), 'active_mapping_count')
            ->selectSub(DB::table('journal_entries')->selectRaw('COUNT(*)')->whereColumn('journal_id', 'j.id'), 'entry_count')
            ->orderBy('j.code')->paginate(20, ['*'], 'acc_journal_types_1')->withQueryString();
        return view('admin.accounting.journal-types.index',compact('journals'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveJournalType(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

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
                if (!(bool) $d['is_active'] && DB::table('account_mappings')->where('journal_id', $id)->where('is_active', 1)->exists())
                    throw ValidationException::withMessages(['is_active'=>'Deactivate all active account mappings for this series first.']);
                DB::table('accounting_journals')->where('id',$id)->update($d+['updated_at'=>now()]);
                \App\Services\Accounting\AccountingAuditService::record('journal_series',$id,'UPDATE',(array)$old,$d);
            } else {
                $id=DB::table('accounting_journals')->insertGetId($d+['created_at'=>now(),'updated_at'=>now()]);
                \App\Services\Accounting\AccountingAuditService::record('journal_series',$id,'CREATE',null,$d);
            }
        });
        return back()->with('success','Journal series saved.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    /** Deactivation preserves journal references and historical financial reports. */
    public function deactivateJournalType(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-settings');
        DB::transaction(function () use ($id) {
            $old = DB::table('accounting_journals')->where('id', $id)->lockForUpdate()->first();
            if (!$old) {
                throw ValidationException::withMessages(['delete' => 'Journal type does not exist.']);
            }
            if (DB::table('account_mappings')->where('journal_id', $id)->where('is_active', 1)->exists()) {
                throw ValidationException::withMessages(['delete' => 'Deactivate linked active account mappings first, then deactivate the journal type.']);
            }
            if (!$old->is_active) return;
            DB::table('accounting_journals')->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);
            \App\Services\Accounting\AccountingAuditService::record('journal_series', $id, 'DEACTIVATE', (array) $old,
                ['is_active' => false], 'Journal type deactivated, references retained');
        });
        return back()->with('success', 'Journal type deactivated. Existing mappings and journals have been preserved.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function deleteJournalType(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-settings');
        app(\App\Services\Accounting\AccountingSafeDeletionService::class)->journalType($id);
        return back()->with('success','Unused draft/master record deleted. Accounting audit history retained.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

}
