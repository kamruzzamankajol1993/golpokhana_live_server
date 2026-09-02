<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $title }}</title>
<style>
    body { font-family: sans-serif; color:#1f2937; font-size:9px; }
    h1 { margin:0 0 4px; color:#21352a; font-size:20px; }
    .muted { color:#6b7280; }
    .meta { margin:8px 0 12px; line-height:1.6; }
    table { width:100%; border-collapse:collapse; }
    th { background:#21352a; color:#fff; padding:6px 4px; text-align:left; font-size:8px; }
    td { border:1px solid #e5e7eb; padding:5px 4px; vertical-align:top; word-break:break-word; }
    .footer { margin-top:12px; border-top:1px solid #d1d5db; padding-top:6px; color:#6b7280; font-size:8px; }
</style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @if(!empty($subtitle))<div class="muted">{{ $subtitle }}</div>@endif
    @if(!empty($meta))
        <div class="meta">
            @foreach($meta as $label => $value)
                <strong>{{ $label }}:</strong> {{ $value }}@if(!$loop->last) &nbsp; | &nbsp; @endif
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ max(1, count($headings)) }}" style="text-align:center;padding:18px;">No data found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated: {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
