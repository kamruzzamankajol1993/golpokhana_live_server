<?php
namespace App\Http\Controllers\Admin\Accounting\Expenses;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingEventService,AccountingInventoryCostService,AccountingMoney,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** Expenses accounting module. A shared base provides posting dependencies and invariant checks. */
final class ExpensesController extends \App\Http\Controllers\Admin\Accounting\Shared\OperationsController
{
    public function expenses()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $expenses=DB::table('expenses as e')->join('expense_categories as c','c.id','=','e.expense_category_id')
            ->select('e.*','c.name as category')->orderByDesc('e.id')->paginate(20);
        $categories=DB::table('expense_categories')->where('is_active',true)->orderBy('name')->get();
        return view('admin.accounting.expenses.index',compact('expenses','categories'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function storeExpense(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-expense');
        $d=$r->validate(['expense_category_id'=>'required|exists:expense_categories,id','expense_date'=>'required|date',
            'amount'=>'required|numeric|gt:0','description'=>'required|string|max:3000']);
        $amount=AccountingMoney::format($this->amount($d['amount']));
        DB::transaction(function () use($d,$amount) {
            $id=DB::table('expenses')->insertGetId([
                'expense_no'=>'EXP-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(4))),
                'expense_category_id'=>$d['expense_category_id'],'expense_date'=>$d['expense_date'],
                'amount'=>$amount,'description'=>$d['description'],'status'=>'DRAFT','created_by'=>auth()->id(),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
        return back()->with('success','Expense DRAFT saved. Approval does not automatically pay the expense.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function approveExpense(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-expense');
        $this->can('accounting-journal-approve');
        DB::transaction(function () use($id) {
            $e=DB::table('expenses')->where('id',$id)->lockForUpdate()->first();
            if (!$e || $e->status!=='DRAFT') throw ValidationException::withMessages(['expense'=>'Only DRAFT expense can be approved.']);
            if ((int)$e->created_by===(int)auth()->id()) throw ValidationException::withMessages(['expense'=>'Maker cannot approve own expense.']);
            DB::table('expenses')->where('id',$id)->update(['status'=>'APPROVED',
                'approved_by'=>auth()->id(),'approved_at'=>now(),'updated_at'=>now()]);
            $callback=function() use($id) {
                try { $this->captureExpense($id); }
                catch (\Throwable $e) { Log::error('Accounting expense capture needs manual review',['expense_id'=>$id,'reason'=>$e->getMessage()]); }
            };
            DB::afterCommit($callback);
        },3);
        return back()->with('success','Expense approved; if Capture ON, a controlled journal DRAFT is generated.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function captureExpense(int $id): void
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $e=DB::table('expenses')->where('id',$id)->first();
        $s=DB::table('accounting_settings')->where('id',1)->first();
        if (!$e || $e->status!=='APPROVED' || !$s || !$s->capture_enabled || $e->journal_entry_id) return;
        $c=DB::table('expense_categories')->where('id',$e->expense_category_id)->first();
        if (!$c) return;
        $event=$this->events->capture('expenses',$id,'EXPENSE_APPROVED',1,(string)$e->approved_at,null,
            ['total'=>(string)$e->amount,'amounts'=>['expense'=>(string)$e->amount,
             'accrued_payable'=>(string)$e->amount],
             'source'=>['category'=>$c->name,'expense_no'=>$e->expense_no,'description'=>$e->description],
             'manual_only'=>true]);
        if (!$event) return;
        DB::transaction(function() use($event,$e,$c) {
            $lock=DB::table('expenses')->where('id',$e->id)->lockForUpdate()->first();
            if (!$lock || $lock->journal_entry_id) return;
            $amount=$this->amount($lock->amount);
            $id=$this->posting->createDraft(['journal_id'=>$this->journal('MISC'),
                'entry_date'=>$lock->expense_date,'reference'=>$lock->expense_no,
                'narration'=>$lock->description,'created_by'=>$lock->approved_by],
                [$this->line((int)$c->expense_account_id,$amount,0,'Expense'),
                 $this->line($this->account('2150'),0,$amount,'Accrued expense liability')],$event);
            DB::table('expenses')->where('id',$lock->id)->update(['journal_entry_id'=>$id,'updated_at'=>now()]);
            DB::table('accounting_events')->where('id',$event)->update([
                'journal_entry_id'=>$id,'status'=>'DRAFT','updated_at'=>now(),
            ]);
        },3);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function attachExpense(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-expense');
        $d=$r->validate(['attachment'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $e=DB::table('expenses')->where('id',$id)->first();
        if (!$e || !in_array($e->status,['DRAFT','APPROVED'],true)) {
            throw ValidationException::withMessages(['attachment'=>'Attachment allowed only before journal posting.']);
        }
        $path=$r->file('attachment')->store('accounting/expenses','local');
        DB::table('expense_attachments')->insert(['expense_id'=>$id,
            'filename'=>basename($r->file('attachment')->getClientOriginalName()),
            'path'=>$path,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Expense receipt attached securely.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function downloadExpenseAttachment(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $item=DB::table('expense_attachments')->find($id);
        abort_unless($item,404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($item->path),404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($item->path,$item->filename);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function deleteExpense(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-expense');
        app(\App\Services\Accounting\AccountingSafeDeletionService::class)->expense($id);
        return back()->with('success','Unused draft/master record deleted. Accounting audit history retained.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

}
