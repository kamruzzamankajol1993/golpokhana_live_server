@forelse($sessions as $sess)
    <tr>
        <td><strong>#{{ $sess->id }}</strong></td>
        <td>{{ $sess->user->name ?? 'N/A' }}</td>
        <td><span class="badge bg-secondary">{{ $sess->weekday }}</span></td>
        <td>{{ $sess->start_time->format('d M y - h:i A') }}</td>
        <td>{{ $sess->end_time ? $sess->end_time->format('d M y - h:i A') : '—' }}</td>
        <td>{{ $sess->duration ?? 'Running' }}</td>
        <td><strong>৳{{ round($sess->report_grand_total ?? $sess->grand_total) }}</strong></td>
        <td>
            <span class="badge {{ $sess->status == 'Open' ? 'bg-success' : 'bg-danger' }}">
                {{ $sess->status }}
            </span>
        </td>
        <td>
            <div class="d-flex gap-1 justify-content-center">
                <button type="button"
                    class="btn btn-sm btn-primary btnEditSession"
                    data-id="{{ $sess->id }}"
                    data-start="{{ $sess->start_time ? $sess->start_time->format('Y-m-d\TH:i') : '' }}"
                    data-end="{{ $sess->end_time ? $sess->end_time->format('Y-m-d\TH:i') : '' }}"
                    data-status="{{ $sess->status }}">
                    <i class="bi bi-pencil-square"></i> Edit
                </button>
                @if($sess->status == 'Closed')
                    <a href="{{ route('pos.session.report', $sess->id) }}" target="_blank" class="btn btn-sm btn-warning fw-bold">
                        <i class="bi bi-printer"></i> Print
                    </a>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="9" class="text-muted py-4">No sessions found in the last 10 business days.</td>
    </tr>
@endforelse
