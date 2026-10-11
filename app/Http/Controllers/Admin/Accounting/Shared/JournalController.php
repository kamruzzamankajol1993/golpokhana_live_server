<?php
namespace App\Http\Controllers\Admin\Accounting\Shared;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\AccountingEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

abstract class JournalController extends Controller
{
    use LogsAccountingExceptions;

    public function __construct(protected JournalPostingService $posting, protected AccountingEventService $events) {}
    protected function permission(string $p): void { abort_unless(auth()->user()->can($p),403); }

    protected function formData(): array
    {
        return [
            'journals'=>DB::table('accounting_journals')->where('is_active',true)->orderBy('code')->get(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_active',true)->where('is_header',false)->orderBy('code')->get(),
            'costCenters'=>DB::table('cost_centers')->where('is_active',true)->orderBy('code')->get(),
        ];
    }


    protected function validated(Request $r): array
    {
        return $r->validate(['journal_id'=>'required|exists:accounting_journals,id',
            'entry_date'=>'required|date_format:Y-m-d','reference'=>'nullable|string|max:100',
            'narration'=>'required|string|max:2000','lines'=>'required|array|min:2|max:100',
            'lines.*.account_id'=>'required|exists:chart_of_accounts,id',
            'lines.*.debit'=>'required|numeric|min:0','lines.*.credit'=>'required|numeric|min:0',
            'lines.*.memo'=>'nullable|string|max:500',
            'lines.*.cost_center_id'=>'nullable|exists:cost_centers,id']);
    }









}
