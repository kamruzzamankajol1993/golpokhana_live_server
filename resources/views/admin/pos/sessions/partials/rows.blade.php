@forelse($sessions as $key => $sess)
<tr>
    <td><span class="report-sl-badge">{{ ($sessions->firstItem() ?? 1) + $key }}</span></td>
    <td><strong>#{{ $sess->id }}</strong></td>
    <td>{{ $sess->user->name ?? 'N/A' }}</td>
    <td><span class="progga-badge progga-badge-secondary">{{ $sess->weekday }}</span></td>
    <td>{{ $sess->start_time ? $sess->start_time->format('d M y - h:i A') : '—' }}</td>
    <td>{{ $sess->end_time ? $sess->end_time->format('d M y - h:i A') : '—' }}</td>
    <td>{{ $sess->duration ?? 'Running' }}</td>
    <td><strong>৳{{ number_format((float) ($sess->report_grand_total ?? $sess->grand_total), 0) }}</strong></td>
    <td>
        <span class="progga-badge {{ $sess->status === 'Open' ? 'progga-badge-success' : 'progga-badge-danger' }}">
            {{ $sess->status }}
        </span>
    </td>
    <td>
        <div class="d-flex gap-1 justify-content-center flex-wrap">
            <button type="button"
                    class="progga-btn progga-btn-outline progga-btn-sm btnEditSession"
                    data-id="{{ $sess->id }}"
                    data-start="{{ $sess->start_time ? $sess->start_time->format('Y-m-d\\TH:i') : '' }}"
                    data-end="{{ $sess->end_time ? $sess->end_time->format('Y-m-d\\TH:i') : '' }}"
                    data-status="{{ $sess->status }}">
                <i class="bi bi-pencil-square"></i> Edit
            </button>
            @if($sess->status === 'Closed')
                <a href="{{ route('pos.session.report', $sess->id) }}" target="_blank" class="progga-btn progga-btn-primary progga-btn-sm">
                    <i class="bi bi-printer"></i> Print
                </a>
            @endif
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="10" class="text-center text-muted" style="padding:32px 12px;">No sessions found.</td>
</tr>
@endforelse
