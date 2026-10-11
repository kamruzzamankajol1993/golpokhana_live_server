<?php
namespace App\Http\Controllers\Admin\Accounting\Shared;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingReportService,AccountingMoney};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

abstract class AccountingReportsController extends Controller
{
    use LogsAccountingExceptions;

    public function __construct(protected AccountingReportService $service) {}

    protected function access(): void { abort_unless(auth()->user()?->can('accounting-financial-reports'),403); }

    protected function params(Request $r): array
    {
        $d=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d',
            'type'=>'nullable|in:profit-loss,balance-sheet,trial-balance,cash-flow,tax,aging,general-ledger',
            'account_id'=>'nullable|integer|exists:chart_of_accounts,id']);
        $from=$d['from'] ?? now()->startOfMonth()->toDateString();
        $to=$d['to'] ?? now()->toDateString();
        if ($from>$to || Carbon::parse($from)->diffInDays(Carbon::parse($to))>740)
            throw ValidationException::withMessages(['dates'=>'Choose an ordered date range of up to 24 months.']);
        return [$d['type'] ?? 'profit-loss',$from,$to,(int)($d['account_id'] ?? 0)];
    }

    protected function build(string $type,string $from,string $to,int $account): array
    {
        return match($type) {
            'profit-loss'=>$this->service->pnl($from,$to),
            'balance-sheet'=>$this->service->balanceSheet($to),
            'trial-balance'=>$this->service->trialBalance($to),
            'cash-flow'=>$this->service->cashFlow($from,$to),
            'tax'=>$this->service->tax($from,$to),
            'aging'=>$this->service->aging($to),
            'general-ledger'=>$account ? $this->service->ledger($account,$from,$to) : ['rows'=>[], 'opening'=>0,'running'=>0,'account'=>null],
        };
    }









}
