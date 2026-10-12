<?php
namespace App\Services\Accounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** All financial amounts below are integer ten-thousandths of BDT. POSTED GL is authoritative. */
final class AccountingReportService
{
    public function fmt(int $units): string
    {
        return ($units < 0 ? '-' : '').AccountingMoney::format(abs($units));
    }

    public function totals(?string $from, string $to, bool $excludeClosing = false): array
    {
        $rows=DB::table('chart_of_accounts as a')
            ->leftJoin('journal_lines as l','l.account_id','=','a.id')
            ->leftJoin('journal_entries as j',function($join) use ($from,$to,$excludeClosing) {
                $join->on('j.id','=','l.journal_entry_id')->where('j.status','=','POSTED')
                    ->where('j.entry_date','<=',$to);
                if ($excludeClosing) $join->where('j.origin','!=','CLOSING');
                if ($from) $join->where('j.entry_date','>=',$from);
            })
            // The LEFT join to journals must not count lines from draft or out-of-period journals.
            ->select('a.id','a.code','a.name','a.type','a.normal_balance','a.is_header')
            ->selectRaw('COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit ELSE 0 END),0) as dr')
            ->selectRaw('COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit ELSE 0 END),0) as cr')
            ->groupBy('a.id','a.code','a.name','a.type','a.normal_balance','a.is_header')
            ->orderBy('a.code')->get();
        $output=[];
        foreach ($rows as $row) {
            $debit=AccountingMoney::units((string)$row->dr);
            $credit=AccountingMoney::units((string)$row->cr);
            $output[]=['id'=>(int)$row->id,'code'=>$row->code,'name'=>$row->name,
                'type'=>$row->type,'is_header'=>(bool)$row->is_header,
                'normal_balance'=>$row->normal_balance,'dr'=>$debit,'cr'=>$credit,
                'net'=>$debit-$credit,'natural'=>$row->normal_balance==='debit' ? $debit-$credit : $credit-$debit];
        }
        return $output;
    }

    public function trialBalance(string $asOf): array
    {
        $rows=[];
        foreach($this->totals(null,$asOf) as $r) {
            if ($r['is_header']) continue;
            $net=$r['dr']-$r['cr'];
            if ($net===0) continue;
            $r['debit_balance']=max(0,$net);
            $r['credit_balance']=max(0,-$net);
            $rows[]=$r;
        }
        return ['rows'=>$rows,'debits'=>array_sum(array_column($rows,'debit_balance')),
            'credits'=>array_sum(array_column($rows,'credit_balance'))];
    }

    public function pnl(string $from, string $to): array
    {
        $data=['income'=>[],'contra_revenue'=>[],'cogs'=>[],'expense'=>[]];
        $sums=array_fill_keys(array_keys($data),0);
        foreach ($this->totals($from,$to,true) as $row) {
            if (!isset($data[$row['type']]) || $row['is_header']) continue;
            $amount=in_array($row['type'],['income'],true) ? $row['cr']-$row['dr'] : $row['dr']-$row['cr'];
            if ($amount===0) continue;
            $row['amount']=$amount;
            $data[$row['type']][]=$row;
            $sums[$row['type']]+=$amount;
        }
        $netSales=$sums['income']-$sums['contra_revenue'];
        $gross=$netSales-$sums['cogs'];
        return compact('data','sums','netSales','gross')+['profit'=>$gross-$sums['expense']];
    }

    public function balanceSheet(string $to): array
    {
        $groups=['asset'=>[],'liability'=>[],'equity'=>[]];
        $sum=['asset'=>0,'liability'=>0,'equity'=>0];
        $earnings=0;
        foreach ($this->totals(null,$to) as $row) {
            if ($row['is_header']) continue;
            $type=$row['type'];
            if (isset($groups[$type])) {
                // Signed balances preserve contra-assets, negative equity, and overdrafts.
                $row['amount']= $type==='asset' ? $row['dr']-$row['cr'] : $row['cr']-$row['dr'];
                if ($row['amount']!==0) $groups[$type][]=$row;
                $sum[$type]+=$row['amount'];
            } elseif ($type==='income') $earnings+=$row['cr']-$row['dr'];
            elseif (in_array($type,['expense','cogs','contra_revenue'],true)) $earnings-=$row['dr']-$row['cr'];
        }
        $liabilitiesAndEquity=$sum['liability']+$sum['equity']+$earnings;
        return compact('groups','sum','earnings','liabilitiesAndEquity')+
            ['difference'=>$sum['asset']-$liabilitiesAndEquity];
    }

    /** Cash book movements from posted journal lines. Conservative classification: never guess. */
    public function cashFlow(string $from, string $to): array
    {
        $activities=DB::table('accounting_cash_flow_rules')->pluck('activity','account_id')->all();
        $cashIds=[];
        foreach ($activities as $id=>$activity) if ($activity==='CASH') $cashIds[]=(int)$id;
        if (!$cashIds) return ['groups'=>[],'rows'=>[],'opening'=>0,'closing'=>0,'movement'=>0,'unclassified'=>0,
            'warning'=>'Choose at least one CASH account in Cash Flow Rules before relying on the report.'];
        $openingRaw=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
            ->where('j.status','POSTED')->where('j.entry_date','<',$from)->whereIn('l.account_id',$cashIds)
            ->selectRaw('COALESCE(SUM(l.debit),0) as dr, COALESCE(SUM(l.credit),0) as cr')->first();
        $opening=AccountingMoney::units((string)$openingRaw->dr)-AccountingMoney::units((string)$openingRaw->cr);
        $lines=DB::table('journal_entries as j')->join('journal_lines as l','l.journal_entry_id','=','j.id')
            ->join('chart_of_accounts as a','a.id','=','l.account_id')
            ->where('j.status','POSTED')->whereBetween('j.entry_date',[$from,$to])
            ->select('j.id','j.entry_date','j.number','j.narration','l.account_id','l.debit','l.credit','a.code','a.name')
            ->orderBy('j.entry_date')->orderBy('j.id')->get()->groupBy('id');
        $groups=['OPERATING'=>0,'INVESTING'=>0,'FINANCING'=>0,'UNCLASSIFIED'=>0]; $rows=[];
        foreach ($lines as $journalLines) {
            $net=0; $categories=[];
            foreach ($journalLines as $l) {
                $v=AccountingMoney::units((string)$l->debit)-AccountingMoney::units((string)$l->credit);
                if (in_array((int)$l->account_id,$cashIds,true)) $net+=$v;
                elseif ($v!==0) $categories[$activities[$l->account_id] ?? 'UNCLASSIFIED']=true;
            }
            if ($net===0) continue; // Cash↔bank internal transfers are not cash inflow/outflow.
            $activity=count($categories)===1 ? array_key_first($categories) : 'UNCLASSIFIED';
            if (!array_key_exists($activity,$groups)) $activity='UNCLASSIFIED';
            $groups[$activity]+=$net;
            $f=$journalLines->first();
            $rows[]=['id'=>$f->id,'date'=>$f->entry_date,'number'=>$f->number,
                'narration'=>$f->narration,'activity'=>$activity,'amount'=>$net];
        }
        $movement=array_sum($groups); $closing=$opening+$movement;
        return compact('groups','rows','opening','closing','movement')+['unclassified'=>count(array_filter($rows,fn($r)=>$r['activity']==='UNCLASSIFIED')),
            'warning'=>'Cash flow classifications are accountant-configured. Mixed or unmapped journals remain UNCLASSIFIED; do not publish without reviewing them.'];
    }

    public function aging(string $asOf): array
    {
        $cutoff=Carbon::parse($asOf);
        $out=[];
        foreach ([['AR','customer_receivables','receivable_allocations','receivable_id','order_id','orders','SALES_COMPLETED','order_due_payments','AR_SETTLEMENT','order_due_payment_id','original_due','customer_id'],
                  ['AP','vendor_payables','payable_allocations','vendor_payable_id','purchase_id','purchases','PURCHASE_RECEIVED','vendor_payments','AP_SETTLEMENT','vendor_payment_id','original_amount','vendor_id']] as $v) {
            [$name,$table,$alloc,$key,$srcId,$src,$kind,$paidSrc,$paidType,$paidId,$amountField,$party]=$v;
            $items=DB::table($table.' as s')->join('accounting_events as e',function($j) use($srcId,$src,$kind) {
                $j->where('e.source_type','=',$src)->where('e.event_type','=',$kind)
                    ->whereRaw('e.source_id = CAST(s.'.$srcId.' AS CHAR)');
            })->join('journal_entries as p',function($j) use($asOf) {
                $j->on('p.id','=','e.journal_entry_id')->where('p.status','=','POSTED')->where('p.entry_date','<=',$asOf);
            })->leftJoin('accounting_due_terms as dt',function($j) use($name) {
                $j->on('dt.source_id','=','s.id')->where('dt.source_type','=',$name);
            })->select('s.*','p.entry_date as recognized_on','dt.due_date as tracked_due_date')->distinct()->get();
            // Posted source payments only. Batch query prevents N+1 for restaurants with many invoices.
            $paymentRows=DB::table($alloc.' as a')->join('accounting_events as e',function($j) use($paidSrc,$paidType,$paidId) {
                $j->where('e.source_type','=',$paidSrc)->where('e.event_type','=',$paidType)
                    ->whereRaw('e.source_id = CAST(a.'.$paidId.' AS CHAR)');
            })->join('journal_entries as j',function($j) use($asOf) {
                $j->on('j.id','=','e.journal_entry_id')->where('j.status','=','POSTED')->where('j.entry_date','<=',$asOf);
            })->select('a.id','a.'.$key.' as invoice_id','a.amount')->distinct()->get();
            $paidMap=[];
            foreach ($paymentRows as $p) $paidMap[$p->invoice_id]=($paidMap[$p->invoice_id]??0)+AccountingMoney::units((string)$p->amount);
            $buckets=['current'=>0,'1-30'=>0,'31-60'=>0,'61-90'=>0,'90+'=>0];$rows=[];
            $append=function($sourceId,$partyId,string $due,int $original,int $paid) use(&$rows,&$buckets,$cutoff): void {
                $remaining=max(0,$original-$paid);
                if (!$remaining) return;
                $days=max(0,(int)(-$cutoff->diffInDays(Carbon::parse($due),false)));
                $bucket=$days===0?'current':($days<=30?'1-30':($days<=60?'31-60':($days<=90?'61-90':'90+')));
                $buckets[$bucket]+=$remaining;
                $rows[]=['source_id'=>$sourceId,'party_id'=>$partyId,'due'=>$due,
                    'original'=>$original,'paid'=>$paid,'outstanding'=>$remaining,'bucket'=>$bucket];
            };
            $seen=[];
            foreach ($items as $item) {
                if (isset($seen[$item->id])) continue;
                $seen[$item->id]=true;
                $append($item->$srcId,$item->$party,
                (string)($item->tracked_due_date ?: $item->due_date ?: $item->recognized_on),
                AccountingMoney::units((string)$item->$amountField),$paidMap[$item->id]??0);
            }
            // Pre-cutover invoices are financial assets/liabilities only when the opening journal
            // was POSTED and independently approved; later manual receipts must be POSTED too.
            $openItems=DB::table('accounting_opening_items as i')
                ->join('accounting_cutovers as c','c.id','=','i.cutover_id')
                ->where('c.status','ACTIVE')->where('c.starts_on','<=',$asOf)
                ->where('i.side',$name)->select('i.*')->get();
            $openPaid=DB::table('accounting_opening_allocations as a')
                ->join('journal_entries as j','j.id','=','a.journal_entry_id')
                ->where('j.status','POSTED')->where('j.entry_date','<=',$asOf)
                ->select('a.opening_item_id','a.amount')->get();
            $openPaidMap=[];foreach($openPaid as $p) $openPaidMap[$p->opening_item_id]=($openPaidMap[$p->opening_item_id]??0)+AccountingMoney::units((string)$p->amount);
            foreach($openItems as $item) $append('OPENING:'.$item->invoice_reference,$item->party_id,
                (string)$item->due_on,AccountingMoney::units((string)$item->amount),$openPaidMap[$item->id]??0);
            $out[$name]=['rows'=>$rows,'buckets'=>$buckets,'total'=>array_sum($buckets)];
        }
        return $out;
    }

    public function tax(string $from, string $to): array
    {
        $mapped=DB::table('accounting_tax_report_accounts as m')
            ->join('chart_of_accounts as a','a.id','=','m.account_id')->select('m.tax_type','a.id','a.code','a.name')->get();
        $balances=$this->totals($from,$to);
        $lookup=array_column($balances,null,'id');
        $rows=[];
        foreach ($mapped as $m) {
            $b=$lookup[$m->id] ?? ['dr'=>0,'cr'=>0];
            $rows[]=['type'=>$m->tax_type,'code'=>$m->code,'name'=>$m->name,
                'debit'=>$b['dr'],'credit'=>$b['cr'],'movement'=>$b['cr']-$b['dr']];
        }
        $withholdings=DB::table('accounting_withholdings as w')
            ->join('journal_entries as j','j.id','=','w.journal_entry_id')
            ->where('j.status','POSTED')->whereBetween('j.entry_date',[$from,$to])
            ->groupBy('w.tax_type')->select('w.tax_type')
            ->selectRaw('SUM(w.amount) as amount, COUNT(*) as count')->get();
        // Include vendor-payment withholding already posted inside AP_SETTLEMENT,
        // without changing existing report page, exports or manual withholding totals.
        if (DB::getSchemaBuilder()->hasColumn('vendor_payments','tds_amount')) {
            $automatic=DB::table('vendor_payments as vp')
                ->join('accounting_events as e', function ($j) {
                    $j->on('e.source_id','=','vp.id')->where('e.source_type','vendor_payments')
                        ->where('e.event_type','AP_SETTLEMENT');
                })
                ->join('journal_entries as j','j.id','=','e.journal_entry_id')
                ->where('j.status','POSTED')->whereBetween('j.entry_date',[$from,$to])
                ->select('vp.tds_amount','vp.vds_amount')->get();
            $merged=[];
            foreach ($withholdings as $w) $merged[$w->tax_type]=['amount'=>(float)$w->amount,'count'=>(int)$w->count];
            foreach ($automatic as $payment) foreach (['TDS'=>'tds_amount','VDS'=>'vds_amount'] as $type=>$field) {
                if ((float)$payment->$field<=0) continue;
                if (!isset($merged[$type])) $merged[$type]=['amount'=>0,'count'=>0];
                $merged[$type]['amount']+=(float)$payment->$field; $merged[$type]['count']++;
            }
            $withholdings=collect($merged)->map(fn ($v,$type)=>(object)[
                'tax_type'=>$type,'amount'=>number_format($v['amount'],4,'.',''),
                'count'=>$v['count'],])->values();
        }
        return compact('rows','withholdings');
    }

    public function ledger(int $accountId,string $from,string $to): array
    {
        $account=DB::table('chart_of_accounts')->find($accountId);
        if (!$account) throw ValidationException::withMessages(['account_id'=>'Account not found.']);
        $q=fn()=>DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
            ->where('j.status','POSTED')->where('l.account_id',$accountId);
        $openingRaw=$q()->where('j.entry_date','<',$from)->selectRaw('COALESCE(SUM(l.debit),0) as dr, COALESCE(SUM(l.credit),0) as cr')->first();
        $running=AccountingMoney::units((string)$openingRaw->dr)-AccountingMoney::units((string)$openingRaw->cr);
        $opening=$running; $rows=[];
        foreach ($q()->whereBetween('j.entry_date',[$from,$to])
            ->select('j.id','j.number','j.entry_date','j.narration','j.reference','l.debit','l.credit')
            ->orderBy('j.entry_date')->orderBy('j.id')->orderBy('l.id')->get() as $l) {
            $debit=AccountingMoney::units((string)$l->debit);$credit=AccountingMoney::units((string)$l->credit);
            $running+=$debit-$credit;
            $rows[]=['id'=>$l->id,'number'=>$l->number,'date'=>$l->entry_date,'narration'=>$l->narration,
                'reference'=>$l->reference,'dr'=>$debit,'cr'=>$credit,'running'=>$running];
        }
        return compact('account','opening','running','rows');
    }
}
