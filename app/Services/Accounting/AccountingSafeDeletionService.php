<?php
namespace App\Services\Accounting;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Financial integrity guard for deleting records that have NEVER entered the ledger. */
final class AccountingSafeDeletionService
{
    private function blocked(string $message): never
    {
        throw ValidationException::withMessages(['delete' => $message]);
    }

    private function referenced(string $table, string $column, int $id): bool
    {
        return DB::table($table)->where($column, $id)->exists();
    }

    private function ensureUnused(int $id, array $references, string $kind): void
    {
        foreach ($references as [$table, $column]) {
            if ($this->referenced($table, $column, $id)) {
                $this->blocked("Cannot delete {$kind}: it is used by {$table}. Deactivate instead when appropriate.");
            }
        }
    }

    /** Runs the protected delete + audit in one transaction; foreign keys provide a final safety net. */
    private function safely(callable $work): void
    {
        try { DB::transaction($work, 3); }
        catch (QueryException $e) {
            if (in_array((string)$e->getCode(), ['23000','23503'], true) || in_array((int)($e->errorInfo[1] ?? 0), [1451,1452], true)) {
                $this->blocked('This record is already referenced by another accounting module and cannot be deleted.');
            }
            throw $e;
        }
    }

    public function account(int $id): void
    {
        $this->safely(function() use ($id) {
            $a = DB::table('chart_of_accounts')->where('id',$id)->lockForUpdate()->first();
            if (!$a) $this->blocked('Account not found.');
            $this->ensureUnused($id, [
                ['chart_of_accounts','parent_id'], ['account_mapping_rules','account_id'],
                ['journal_lines','account_id'], ['accounting_settings','rounding_account_id'],
                ['expense_categories','expense_account_id'], ['payment_accounts','gl_account_id'],
                ['fixed_assets','asset_account_id'], ['fixed_assets','accumulated_depreciation_account_id'],
                ['fixed_assets','depreciation_expense_account_id'], ['company_loans','liability_account_id'],
                ['accounting_budget_lines','account_id'], ['accounting_cash_flow_rules','account_id'],
                ['accounting_tax_report_accounts','account_id']
            ] , 'account');
            DB::table('chart_of_accounts')->where('id',$id)->delete();
            AccountingAuditService::record('account',$id,'DELETE',(array)$a,null,'Unused chart of accounts entry deleted');
        });
    }

    public function journalType(int $id): void
    {
        $this->safely(function() use ($id) {
            $j=DB::table('accounting_journals')->where('id',$id)->lockForUpdate()->first();
            if (!$j) $this->blocked('Journal type not found.');
            $this->ensureUnused($id,[['journal_entries','journal_id'],['account_mappings','journal_id']], 'journal series');
            DB::table('accounting_journals')->where('id',$id)->delete();
            AccountingAuditService::record('journal_series',$id,'DELETE',(array)$j,null,'Unused journal series deleted');
        });
    }

    public function mapping(int $id): void
    {
        $this->safely(function() use ($id) {
            $m=DB::table('account_mappings')->where('id',$id)->lockForUpdate()->first();
            if (!$m) $this->blocked('Mapping not found.');
            if ($m->is_active) $this->blocked('Deactivate this mapping first, then delete only if it has never captured a source event.');
            if (DB::table('accounting_events')->where('source_type',$m->source_type)->where('event_type',$m->event_type)->exists()) {
                $this->blocked('Mapping cannot be deleted because a source event exists. Keep it for accounting traceability.');
            }
            $rules=DB::table('account_mapping_rules')->where('mapping_id',$id)->get()->map(fn($r)=>(array)$r)->all();
            DB::table('account_mapping_rules')->where('mapping_id',$id)->delete();
            DB::table('account_mappings')->where('id',$id)->delete();
            AccountingAuditService::record('mapping',$id,'DELETE',['mapping'=>(array)$m,'rules'=>$rules],null,'Inactive unused mapping deleted');
        });
    }

    public function costCenter(int $id): void
    {
        $this->safely(function() use ($id) {
            $c=DB::table('cost_centers')->where('id',$id)->lockForUpdate()->first();
            if (!$c) $this->blocked('Cost center not found.');
            $this->ensureUnused($id,[['cost_center_allocations','cost_center_id'],['journal_lines','cost_center_id'],
                ['accounting_budget_lines','cost_center_id']], 'cost center');
            DB::table('cost_centers')->where('id',$id)->delete();
            AccountingAuditService::record('cost_center',$id,'DELETE',(array)$c,null,'Unused analytic cost center deleted');
        });
    }

    public function expense(int $id): void
    {
        $this->safely(function() use ($id) {
            $e=DB::table('expenses')->where('id',$id)->lockForUpdate()->first();
            if (!$e || $e->status!=='DRAFT' || $e->journal_entry_id) $this->blocked('Only unlinked DRAFT expenses can be deleted.');
            if ($this->referenced('expense_attachments','expense_id',$id)) $this->blocked('Remove or archive expense receipt attachments before deletion.');
            if (DB::table('accounting_events')->where('source_type','expenses')->where('source_id',(string)$id)->exists())
                $this->blocked('Captured expense cannot be deleted. Use an accounting correction.');
            DB::table('expenses')->where('id',$id)->delete();
            AccountingAuditService::record('expense',$id,'DELETE',(array)$e,null,'Unapproved expense draft deleted');
        });
    }

    public function draftBudget(int $id): void
    {
        $this->safely(function() use ($id) {
            $b=DB::table('accounting_budgets')->where('id',$id)->lockForUpdate()->first();
            if (!$b || $b->status!=='DRAFT') $this->blocked('Only DRAFT budgets may be deleted. Approved budgets stay in the audit history.');
            $lines=DB::table('accounting_budget_lines')->where('budget_id',$id)->get()->map(fn($l)=>(array)$l)->all();
            DB::table('accounting_budget_lines')->where('budget_id',$id)->delete();
            DB::table('accounting_budgets')->where('id',$id)->delete();
            AccountingAuditService::record('budget',$id,'DELETE',['budget'=>(array)$b,'lines'=>$lines],null,'Draft budget deleted');
        });
    }

    public function bankStatement(int $id): void
    {
        $this->safely(function() use ($id) {
            $b=DB::table('bank_statement_lines')->where('id',$id)->lockForUpdate()->first();
            if (!$b || $b->status!=='UNMATCHED' || $b->matched_journal_entry_id)
                $this->blocked('Only UNMATCHED bank statement lines may be deleted. Unmatch an entry first if necessary.');
            DB::table('bank_statement_lines')->where('id',$id)->delete();
            AccountingAuditService::record('bank_statement_line',$id,'DELETE',(array)$b,null,'Unmatched imported statement line deleted');
        });
    }

    public function draftJournal(int $id): void
    {
        $this->safely(function() use ($id) {
            $j=DB::table('journal_entries')->where('id',$id)->lockForUpdate()->first();
            if (!$j || $j->status!=='DRAFT' || $j->origin!=='MANUAL' || $j->accounting_event_id || $j->posted_at || $j->reversal_of_id)
                $this->blocked('Only standalone, never-posted MANUAL DRAFT journals can be deleted. Use reversal for posted journals.');
            if (DB::table('journal_approvals')->where('journal_entry_id',$id)->exists()
                || DB::table('journal_attachments')->where('journal_entry_id',$id)->exists()
                || DB::table('accounting_events')->where('journal_entry_id',$id)->exists()
                || DB::table('journal_entries')->where('reversal_of_id',$id)->exists())
                $this->blocked('Journal has approval, supporting document, event or reversal linkage. Retain it.');
            $lines=DB::table('journal_lines')->where('journal_entry_id',$id)->get();
            foreach($lines as $line) if (DB::table('cost_center_allocations')->where('journal_line_id',$line->id)->exists())
                $this->blocked('Remove draft cost center allocations before deletion.');
            DB::table('journal_lines')->where('journal_entry_id',$id)->delete();
            DB::table('journal_entries')->where('id',$id)->delete();
            AccountingAuditService::record('journal',$id,'DELETE',['entry'=>(array)$j,'lines'=>$lines->map(fn($l)=>(array)$l)->all()],null,'Standalone manual draft deleted');
        });
    }
}
