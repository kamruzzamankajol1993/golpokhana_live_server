<?php
namespace App\Http\Controllers\Admin\Accounting\Overview;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** Overview accounting module. A shared base provides posting dependencies and invariant checks. */
final class OverviewController extends \App\Http\Controllers\Admin\Accounting\Shared\FoundationController
{
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
        // Setup/readiness must be visible even while Capture is OFF. A balanced *empty*
        // ledger is not evidence that the restaurant has reconciled its source books.
        $readiness = [
            ['name'=>'Fiscal year & open posting period',
                'ready'=>DB::table('accounting_periods')->where('status','OPEN')->exists(),
                'route'=>'accounting.periods', 'permission'=>'accounting-view', 'hint'=>'Create an approved fiscal year and its posting periods.'],
            ['name'=>'Approved active source mappings',
                'ready'=>DB::table('account_mappings')->where('is_active',true)->exists(),
                'route'=>'accounting.mappings', 'permission'=>'accounting-mapping', 'hint'=>'Review VAT, split payments, purchase/GRN and payroll mapping before activation.'],
            ['name'=>'Posted journal evidence',
                'ready'=>$metrics['posted'] > 0,
                'route'=>'accounting.journals.index', 'permission'=>'accounting-view', 'hint'=>'No posted financial entries: reports are not yet verified against operational data.'],
            ['name'=>'Accounting capture enabled',
                'ready'=>(bool)($settings?->capture_enabled ?? false),
                'route'=>'accounting.settings', 'permission'=>'accounting-settings', 'hint'=>'Activate only after accountant sign-off and data reconciliation.'],
        ];
        return view('admin.accounting.overview.index',compact('settings','metrics','balance','readiness'));
    }
}
