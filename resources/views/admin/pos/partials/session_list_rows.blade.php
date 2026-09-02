@forelse($sessions as $sess)
<tr>
    <td><strong>#{{ $sess->id }}</strong></td>
    <td>{{ $sess->user->name ?? 'N/A' }}</td>
    <td><span class="progga-badge progga-badge-neutral">{{ $sess->weekday }}</span></td>
    <td>{{ $sess->start_time ? $sess->start_time->format('d M y - h:i A') : '—' }}</td>
    <td>{{ $sess->end_time ? $sess->end_time->format('d M y - h:i A') : '—' }}</td>
    <td>{{ $sess->duration ?? 'Running' }}</td>
    <td><strong>৳{{ number_format((float) ($sess->report_grand_total ?? $sess->grand_total ?? 0), 0) }}</strong></td>
    <td>
        <span class="progga-badge {{ $sess->status === 'Open' ? 'progga-badge-success' : 'progga-badge-danger' }}">
            {{ $sess->status }}
        </span>
    </td>
    <td>
        <div class="d-flex gap-1 justify-content-center flex-wrap">
            <button type="button"
                    class="progga-btn progga-btn-primary progga-btn-sm btnEditSession"
                    data-id="{{ $sess->id }}"
                    data-start="{{ $sess->start_time ? $sess->start_time->format('Y-m-d\TH:i') : '' }}"
                    data-end="{{ $sess->end_time ? $sess->end_time->format('Y-m-d\TH:i') : '' }}"
                    data-status="{{ $sess->status }}">
                <i class="bi bi-pencil-square"></i> Edit
            </button>
            <a href="{{ route('pos.session.report', $sess->id) }}" target="_blank" rel="noopener" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-printer"></i> {{ $sess->status === 'Open' ? 'Live Print' : 'Print' }}
            </a>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="9" class="text-center py-5 text-muted">No POS sessions found.</td>
</tr>
@endforelse
