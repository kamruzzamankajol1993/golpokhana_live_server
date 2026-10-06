<div class="table-responsive">
<table class="table progga-table mb-0"><thead><tr><th>SL</th><th>Remote Person</th><th>Identifier</th><th>RFID</th><th>Person ID</th><th>Device ID</th><th>Fingerprints</th><th>Remote Updated</th><th>Action</th></tr></thead><tbody>
@forelse($remotePeople as $person)
<tr><td>{{ ($remotePeople->firstItem() ?? 1)+$loop->index }}</td><td><div class="fw-bold">{{ $person->name ?: 'Unnamed Person' }}</div><div class="small text-muted">{{ $person->person_type ?: 'employee' }}</div></td><td>{{ $person->identifier ?: '-' }}</td><td>{{ $person->rfid ?: '-' }}</td><td>{{ $person->tipsoi_person_id ?: '-' }}</td><td>{{ $person->id_in_device ?: '-' }}</td><td>{{ $person->total_fingerprints ?? 0 }}</td><td>{{ $person->remote_updated_at?->format('d M Y h:i A') ?: '-' }}</td><td>@can('employee-create')<a href="{{ route('hr.employees.create',['tipsoi_remote_person_id'=>$person->id]) }}" class="progga-btn progga-btn-primary progga-btn-sm"><i class="bi bi-person-plus"></i> Create in HR</a>@endcan</td></tr>
@empty<tr><td colspan="9" class="text-center py-5 text-muted">No unmatched remote people. Refresh Remote People to get the latest list.</td></tr>@endforelse
</tbody></table></div>
@include('admin.partials.custom_pagination',['paginator'=>$remotePeople])
