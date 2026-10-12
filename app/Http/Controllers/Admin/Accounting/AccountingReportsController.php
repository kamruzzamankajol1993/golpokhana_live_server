<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingReportService,AccountingMoney};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AccountingReportsController extends Controller
{
    public function __construct(private AccountingReportService $service) {}

    private function access(): void { abort_unless(auth()->user()?->can('accounting-financial-reports'),403); }

    private function params(Request $r): array
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

    private function build(string $type,string $from,string $to,int $account): array
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

    public function index(Request $r)
    {
        $this->access();
        [$type,$from,$to,$account]=$this->params($r);
        $report=$this->build($type,$from,$to,$account);
        $accounts=DB::table('chart_of_accounts')->where('is_header',false)->orderBy('code')->get();
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        $cutover=DB::table('accounting_cutovers')->where('status','ACTIVE')->first();
        $exceptions=DB::table('accounting_events')->where('status','!=','POSTED')->count();
        return view('admin.accounting.reports.index',compact('type','from','to','account','report','accounts','settings','cutover','exceptions'));
    }

    public function export(Request $r)
    {
        $this->access();
        [$type,$from,$to,$account]=$this->params($r);
        $report=$this->build($type,$from,$to,$account);
        $data=[];
        $data[]=['Company-level POSTED General Ledger Report',$type,$from,$to];
        $data[]=['BDT, four-decimal precision; draft/pending excluded'];
        if ($type==='profit-loss') {
            foreach ($report['data'] as $section=>$items) {
                $data[]=[$section,'Code','Name','BDT'];
                foreach ($items as $row) $data[]=['',$row['code'],$row['name'],$this->service->fmt($row['amount'])];
            }
            foreach (['netSales'=>'Net sales','gross'=>'Gross profit','profit'=>'Net profit'] as $k=>$title)
                $data[]=[$title,$this->service->fmt($report[$k])];
        } elseif ($type==='balance-sheet') {
            foreach ($report['groups'] as $section=>$items) {
                $data[]=[$section,'Code','Name','BDT'];
                foreach($items as $row) $data[]=['',$row['code'],$row['name'],$this->service->fmt($row['amount'])];
            }
            foreach(['asset','liability','equity'] as $g) $data[]=[$g.' total',$this->service->fmt($report['sum'][$g])];
            $data[]=['Untransferred earnings',$this->service->fmt($report['earnings'])];
            $data[]=['Accounting equation difference',$this->service->fmt($report['difference'])];
        } elseif ($type==='trial-balance') {
            $data[]=['Account Code','Account Name','Total Debit','Total Credit'];
            foreach($report['rows'] as $row) $data[]=[$row['code'],$row['name'],$this->service->fmt($row['debit_balance']),$this->service->fmt($row['credit_balance'])];
            $data[]=['TOTAL','',$this->service->fmt($report['debits']),$this->service->fmt($report['credits'])];
        } elseif ($type==='cash-flow') {
            $data[]=['Date','Journal','Classification','Cash Change','Memo'];
            foreach($report['rows'] as $row) $data[]=[$row['date'],$row['number'],$row['activity'],$this->service->fmt($row['amount']),$row['narration']];
            foreach(['opening','movement','closing'] as $field) $data[]=[$field,$this->service->fmt($report[$field])];
            $data[]=['Cashflow Review Warning',$report['warning']];
        } elseif ($type==='tax') {
            $data[]=['Tax Type','Account','Debit','Credit','Liability Movement'];
            foreach($report['rows'] as $row) $data[]=[$row['type'],$row['code'].' '.$row['name'],$this->service->fmt($row['debit']),$this->service->fmt($row['credit']),$this->service->fmt($row['movement'])];
            foreach($report['withholdings'] as $row) $data[]=['Approved Withholding '.$row->tax_type,'Count '.$row->count,$row->amount];
        } elseif ($type==='aging') {
            foreach(['AR','AP'] as $side) {
                $data[]=[$side,'Source ID','Party ID','Due Date','Outstanding','Age'];
                foreach($report[$side]['rows'] as $row) $data[]=['',$row['source_id'],$row['party_id'],$row['due'],$this->service->fmt($row['outstanding']),$row['bucket']];
                $data[]=[$side.' TOTAL',$this->service->fmt($report[$side]['total'])];
            }
        } elseif ($type==='general-ledger') {
            $data[]=['Date','Journal','Debit','Credit','Running Debit-minus-Credit'];
            foreach($report['rows'] as $row) $data[]=[$row['date'],$row['number'],$this->service->fmt($row['dr']),$this->service->fmt($row['cr']),$this->service->fmt($row['running'])];
        }
        $filename='accounting-'.str_replace('-','_',$type).'-'.$to.'.csv';
        return response()->streamDownload(function() use($data) {
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            foreach($data as $line) {
                // Prevent spreadsheet formula injection in user-authored account names/references.
                $safe=array_map(function($cell) {
                    $cell=(string)$cell;
                    return preg_match('/^[=+@\t\r]/u',$cell) || preg_match('/^-(?![0-9]+(?:\.[0-9]+)?$)/u',$cell)
                        ? "'".$cell : $cell;
                },$line);
                fputcsv($out,$safe);
            }
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function dashboard()
    {
        $this->access();
        $to=now()->toDateString();$from=now()->startOfMonth()->toDateString();
        $pnl=$this->service->pnl($from,$to);
        $bs=$this->service->balanceSheet($to);
        $tb=$this->service->trialBalance($to);
        $aging=$this->service->aging($to);
        $settings=DB::table('accounting_settings')->find(1);
        $cutover=DB::table('accounting_cutovers')->where('status','ACTIVE')->first();
        $counts=[
            'pending'=>DB::table('accounting_events')->where('status','!=','POSTED')->count(),
            'draft'=>DB::table('journal_entries')->where('status','!=','POSTED')->count(),
            'negative'=>DB::table('negative_inventory_cost_pending')->where('status','!=','POSTED')->count(),
            'bank'=>DB::table('bank_statement_lines')->where('status','!=','MATCHED')->count(),
            'cash'=>DB::table('cash_reconciliations')->where('status','!=','APPROVED')->count(),
        ];
        return view('admin.accounting.reports.dashboard',compact('to','from','pnl','bs','tb','aging','settings','cutover','counts'));
    }

    public function rules()
    {
        abort_unless(auth()->user()?->can('accounting-view'),403);
        $accounts=DB::table('chart_of_accounts')->where('is_header',false)->orderBy('code')->get();
        $cashRules=DB::table('accounting_cash_flow_rules')->pluck('activity','account_id');
        $taxRules=DB::table('accounting_tax_report_accounts')->pluck('tax_type','account_id');
        return view('admin.accounting.reports.rules',compact('accounts','cashRules','taxRules'));
    }
    public function saveRules(Request $r)
    {
        abort_unless(auth()->user()?->can('accounting-report-mapping'),403);
        $d=$r->validate(['account_id'=>'required|exists:chart_of_accounts,id',
            'rule_type'=>'required|in:CASH_FLOW,TAX','classification'=>'required|string|max:20']);
        $account=DB::table('chart_of_accounts')->find($d['account_id']);
        if ($account->is_header || !$account->is_active) throw ValidationException::withMessages(['account_id'=>'Choose active posting account.']);
        if ($d['rule_type']==='CASH_FLOW') {
            if (!in_array($d['classification'],['CASH','OPERATING','INVESTING','FINANCING','NONE'],true))
                throw ValidationException::withMessages(['classification'=>'Invalid Cash Flow activity.']);
            if ($d['classification']==='CASH' && $account->type!=='asset')
                throw ValidationException::withMessages(['account_id'=>'Cash / bank accounts must be assets.']);
            DB::transaction(function() use($d) {
                DB::table('accounting_cash_flow_rules')->where('account_id',$d['account_id'])->delete();
                if ($d['classification']!=='NONE') DB::table('accounting_cash_flow_rules')->insert([
                    'account_id'=>$d['account_id'],'activity'=>$d['classification'],
                    'updated_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            });
        } else {
            if (!in_array($d['classification'],['VAT','VDS','TDS','NONE'],true))
                throw ValidationException::withMessages(['classification'=>'Invalid Tax tag.']);
            if ($d['classification']!=='NONE' && !in_array($account->type,['asset','liability'],true))
                throw ValidationException::withMessages(['account_id'=>'Tax accounts must be asset or liability posting accounts.']);
            DB::transaction(function() use($d) {
                DB::table('accounting_tax_report_accounts')->where('account_id',$d['account_id'])->delete();
                if ($d['classification']!=='NONE') DB::table('accounting_tax_report_accounts')->insert([
                    'account_id'=>$d['account_id'],'tax_type'=>$d['classification'],
                    'created_at'=>now(),'updated_at'=>now()]);
            });
        }
        \App\Services\Accounting\AccountingAuditService::record('report_account',(int)$d['account_id'],'MAP',null,$d);
        return back()->with('success','Report account classification updated and audited.');
    }
}
