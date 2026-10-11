<?php
namespace App\Http\Controllers\Admin\Accounting\AuditLog;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** AuditLog accounting module. A shared base provides posting dependencies and invariant checks. */
final class AccountingAuditController extends \App\Http\Controllers\Admin\Accounting\Shared\FoundationController
{
    public function audit(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-view');
        $audits=DB::table('accounting_audit_logs')->orderByDesc('id')->paginate(30);
        return view('admin.accounting.audit-log.index',compact('audits'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
