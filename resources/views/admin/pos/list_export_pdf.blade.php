<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        .subtitle { margin-bottom: 10px; font-size: 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th, td { border: 1px solid #bfbfbf; padding: 5px 6px; vertical-align: top; }
        th { background: #f1f1f1; font-weight: bold; }
        .empty { text-align: center; padding: 18px; color: #777; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="subtitle">{{ $subtitle }}</div>
    <table>
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) }}" class="empty">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
