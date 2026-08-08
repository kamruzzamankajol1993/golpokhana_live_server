<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Table QR Codes</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .qr-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .qr-cell {
            width: 33.33%; /* ১ সারিতে ৩টি করে QR কোড */
            text-align: center;
            padding: 30px 10px;
            border: 1px dashed #cccccc;
            vertical-align: middle;
        }
        .restaurant-name {
            font-size: 18px;
            font-weight: bold;
            color: #21352a;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .table-name {
            font-size: 16px;
            color: #444;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .table-name span {
            font-size: 12px;
            font-weight: normal;
            color: #777;
        }
        .qr-image {
            margin: 10px 0;
        }
        .scan-text {
            font-size: 13px;
            margin-top: 15px;
            color: #666;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <table class="qr-grid">
        <tr>
        @foreach($tables as $index => $table)
            @php
                // Base64 এ টেবিল আইডি এনকোড করা
                $encodedId = base64_encode($table->id);
                // ফাইনাল লিংক তৈরি
                $url = $baseUrl . '/' . $encodedId;
            @endphp

            <td class="qr-cell">
                <div class="restaurant-name">{{ $restaurant->name ?? 'Progga RMS' }}</div>
                <div class="table-name">
                    Table: {{ $table->table_number }}<br>
                    <span>({{ $table->zone->name ?? '' }})</span>
                </div>

                <div class="qr-image">
                    <img src="data:image/png;base64,{{ \DNS2D::getBarcodePNG($url, 'QRCODE', 6, 6) }}" alt="QR Code" />
                </div>

                <div class="scan-text">Scan for Menu & Order</div>
            </td>

            {{-- প্রতি ৩টি পর পর নতুন সারি (Row) শুরু হবে --}}
            @if(($index + 1) % 3 == 0)
                </tr><tr>
            @endif
        @endforeach
        </tr>
    </table>

</body>
</html>
