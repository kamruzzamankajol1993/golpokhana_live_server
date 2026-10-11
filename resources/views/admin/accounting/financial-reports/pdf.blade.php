{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
<!doctype html>
<html><head><meta charset="UTF-8"><style>
body{font-family:dejavusans,sans-serif;font-size:9pt;color:#273b32}
h1{font-size:17pt;color:#21352a;margin:0 0 5px}
.meta{font-size:9pt;color:#647a6c;margin-bottom:13px}
table{border-collapse:collapse;width:100%;table-layout:fixed}
th,td{border-bottom:1px solid #dce6dc;padding:7px 5px;text-align:left;word-wrap:break-word;vertical-align:top}
tr:nth-child(even){background:#fafcf9}
tr.total td{background:#f0f5ef;font-weight:bold;color:#21352a}
tr.section td{background:#e2ece3;font-weight:bold}
.small{color:#66786e;font-size:8pt}
</style></head><body>
<h1>{{ ucwords(str_replace('-', ' ', $type)) }}</h1>
<div class="meta">Restaurant Accounting &nbsp; | &nbsp; {{ $from }} to {{ $to }} &nbsp; | &nbsp; BDT (4 decimal places)</div>
<table>
@foreach($rows as $idx => $cells)
@php
 
$first = strtoupper((string)($cells[0] ?? ''));
 $section = in_array($first,['ASSET','LIABILITY','EQUITY','INCOME','CONTRA REVENUE','COGS','EXPENSE','AR','AP','CODE','DATE','TAX TYPE'],true);
 $total = str_contains($first,'TOTAL') || str_contains($first,'DIFFERENCE') || str_contains($first,'PROFIT') || str_contains($first,'ENDING ');
@endphp
<tr class="{{ $section ? 'section' : ($total ? 'total' : '') }}">
@foreach($cells as $cell)
<td>{{ $cell }}</td>
@endforeach
</tr>
@endforeach
</table>
<p class="small">Only POSTED journals affect financial statements. Reconcile to operational sources and approve closing adjustments before sign-off.</p>
</body></html>
