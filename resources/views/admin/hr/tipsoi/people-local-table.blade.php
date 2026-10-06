<div class="table-responsive">
<table class="table progga-table mb-0">
<thead><tr><th style="width:38px"></th><th>SL</th><th>Employee</th><th>TIPSOI Identifier</th><th>RFID</th><th>Person ID</th><th>Fingerprints</th><th>Status</th><th>Last Sync</th><th>Action</th></tr></thead>
<tbody>
@forelse($employees as $employee)
<tr>
<td><input class="form-check-input tipsoi-person-check" type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"></td>
<td>{{ ($employees->firstItem() ?? 1)+$loop->index }}</td>
<td><div class="fw-bold">{{ $employee->name }}</div><div class="small text-muted">{{ $employee->employee_code }}</div></td>
<td>{{ $employee->tipsoi_identifier ?: '-' }}</td><td>{{ $employee->tipsoi_rfid ?: '-' }}</td><td>{{ $employee->tipsoi_person_id ?: '-' }}</td><td>{{ $employee->tipsoi_total_fingerprints ?? 0 }}</td>
<td>@if($employee->tipsoi_sync_status==='synced')<span class="badge text-bg-success">Synced</span>@elseif($employee->tipsoi_sync_status==='failed')<span class="badge text-bg-danger" title="{{ $employee->tipsoi_sync_error }}">Failed</span>@else<span class="badge text-bg-secondary">Not Synced</span>@endif</td>
<td>{{ $employee->tipsoi_synced_at?->format('d M Y h:i A') ?: '-' }}</td>
<td class="text-nowrap">@canany(['employee-create','employee-edit'])<button type="submit" formaction="{{ route('hr.tipsoi.employees.push-one',$employee) }}" formmethod="POST" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-arrow-repeat"></i> Sync</button>@endcanany</td>
</tr>
@empty<tr><td colspan="10" class="text-center py-5 text-muted">No employees found.</td></tr>@endforelse
</tbody></table></div>
@include('admin.partials.custom_pagination',['paginator'=>$employees])
