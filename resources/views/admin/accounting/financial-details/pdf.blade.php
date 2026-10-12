<!DOCTYPE html>
<html>
<head><meta charset="utf-8">
<style>
body { font-family: dejavusans, sans-serif; font-size: 10px; color: #1d3044; }
h1 { font-size: 17px; margin: 0 0 7px; }
.small { color: #526375; font-size: 9px; margin-bottom: 15px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #d7dfe8; padding: 7px; text-align: left; vertical-align: top; word-wrap: break-word; }
th { background: #eaf1f4; font-weight: bold; }
tr:nth-child(even) td { background: #f9fbfc; }
</style>
</head>
<body>
<h1>{{ ucwords(str_replace('-', ' ', $type)) }}</h1>
<div class="small">Reporting period: {{ $from }} to {{ $to }} · BDT · POSTED source-linked journals only</div>
<table>
@foreach($pdfRows as $rowIndex => $row)
<tr>
@foreach($row as $cell)
@if(($row[0] ?? null) === 'Date')
<th>{{ $cell }}</th>
@else
<td>{{ $cell }}</td>
@endif
@endforeach
</tr>
@endforeach
</table>
</body>
</html>
