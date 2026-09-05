@forelse($sessions as $key => $sess)
<tr>
    <td><span class="report-sl-badge">{{ ($sessions->firstItem() ?? 1) + $key }}</span></td>
    <td>{{ $sess->user->name ?? 'N/A' }}</td>
    <td><span class="progga-badge progga-badge-secondary">{{ $sess->weekday }}</span></td>
    <td>{{ $sess->start_time ? $sess->start_time->format('d M y - h:i A') : '—' }}</td>
    <td>{{ $sess->end_time ? $sess->end_time->format('d M y - h:i A') : '—' }}</td>
    <td>{{ $sess->duration ?? 'Running' }}</td>
    <td><strong>৳{{ number_format((float) $sess->grand_total, 0) }}</strong></td>
    <td>
        <span class="progga-badge {{ $sess->status === 'Open' ? 'progga-badge-success' : 'progga-badge-danger' }}">
            {{ $sess->status }}
        </span>
    </td>
    <td>
        @if($sess->status === 'Closed')
            <a href="{{ route('pos.session.report', $sess->id) }}" target="_blank" class="progga-btn progga-btn-primary progga-btn-sm">
                <i class="bi bi-printer"></i> Print
            </a>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
</tr>
@empty
<tr>
    <td colspan="9" class="text-center text-muted" style="padding:32px 12px;">No POS sessions found.</td>
</tr>
@endforelse
