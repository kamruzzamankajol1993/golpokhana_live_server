@extends('admin.master.master')
@section('title','Purchase Voucher Approval History')
@section('body')
@php
    $currentRevision = (int)$voucher->revision_no;
    $history = $voucher->approvals->sortByDesc('id');
    $currentHistory = $history->where('revision_no',$currentRevision);
    $approvalEnabled = $approvalSetting?->is_enabled ?? true;
    $canDispatch = $voucher->canDispatchApproval() && $approvalEnabled;
@endphp
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Approval History / Exchange</h1>
            <p class="text-muted mb-0">{{ $voucher->voucher_no }} · Revision {{ $voucher->revision_no }} · {{ $voucher->vendor?->name }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('inventory.purchase-vouchers.show',$voucher) }}" class="progga-btn progga-btn-outline"><i class="bi bi-eye"></i> Voucher</a>
            <a href="{{ route('inventory.purchase-vouchers.index') }}" class="progga-btn progga-btn-outline">Back</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="progga-card h-100"><div class="p-3"><small class="text-muted d-block">Voucher Status</small><strong>{{ str_replace('_',' ',$voucher->status) }}</strong></div></div></div>
        <div class="col-md-3"><div class="progga-card h-100"><div class="p-3"><small class="text-muted d-block">Required Approval</small><strong>{{ $approvalProgress['minimum'] }}</strong></div></div></div>
        <div class="col-md-3"><div class="progga-card h-100"><div class="p-3"><small class="text-muted d-block">Current Valid Approval</small><strong>{{ $approvalProgress['approved'] }}</strong></div></div></div>
        <div class="col-md-3"><div class="progga-card h-100"><div class="p-3"><small class="text-muted d-block">Current Revision Sends</small><strong>{{ $currentHistory->count() }}</strong></div></div></div>
    </div>

    @if(!$approvalEnabled)
        <div class="alert alert-info">Purchase approval is disabled in Settings. Officer dispatch is not required.</div>
    @elseif($canDispatch)
    <div class="progga-card mb-4">
        <div class="progga-card-header">
            <div><strong>Send / Re-send for Approval</strong><div class="small text-muted">Select only the user(s) you want to receive this voucher now. Earlier approve/reject records remain unchanged in history.</div></div>
        </div>
        <div class="p-4">
            <form method="POST" action="{{ $voucher->status==='DRAFT' ? route('inventory.purchase-vouchers.submit',$voucher) : route('inventory.purchase-vouchers.dispatch-approval',$voucher) }}" id="approvalDispatchForm">
                @csrf
                <div class="row g-2 mb-3">
                    @forelse($approvalApprovers as $approvalApprover)
                        @php
                            $uid=(int)$approvalApprover->user_id;
                            $pending=in_array($uid,$pendingUserIds,true);
                        @endphp
                        <div class="col-md-6 col-xl-4">
                            <label class="border rounded p-3 d-flex gap-2 h-100 {{ $pending ? 'bg-light text-muted' : '' }}" style="cursor:{{ $pending ? 'not-allowed' : 'pointer' }}">
                                <input type="checkbox" class="form-check-input mt-1 approval-user" name="approver_ids[]" value="{{ $uid }}" @disabled($pending)>
                                <span>
                                    <strong>{{ $approvalApprover->user?->name ?: 'Unavailable user' }}</strong>
                                    <small class="d-block text-muted">{{ $approvalApprover->user?->email }}</small>
                                    @if($pending)<span class="badge bg-warning text-dark mt-1">Already Pending</span>@endif
                                </span>
                            </label>
                        </div>
                    @empty
                        <div class="col-12"><div class="alert alert-warning mb-0">No approval user is assigned in Settings > Purchase Approval.</div></div>
                    @endforelse
                </div>
                <label class="progga-form-label">Send Note <span class="progga-required">*</span></label>
                <textarea class="progga-form-control" name="approval_note" id="approvalDispatchNote" rows="3" maxlength="2000" required placeholder="Why are you sending this voucher to these user(s)?">{{ old('approval_note') }}</textarea>
                <div class="small text-muted mt-1 mb-3">Every dispatch keeps its own sender, recipients, time and note.</div>
                <button type="button" class="progga-btn progga-btn-primary" onclick="submitApprovalDispatch()"><i class="bi bi-send-check"></i> Send to Selected User(s)</button>
            </form>
        </div>
    </div>
    @elseif(in_array($voucher->status,['APPROVED','SENT_TO_VENDOR','COMPLETED'],true))
        <div class="alert alert-success">The minimum approval requirement has been completed. This revision is no longer open for additional approval dispatch.</div>
    @endif

    <div class="progga-card mb-4">
        <div class="progga-card-header"><strong>Full Approval Exchange History</strong><span class="small text-muted">Newest first</span></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Revision / Batch</th><th>Sent</th><th>From</th><th>To</th><th>Send Note</th><th>Decision</th><th>Decision Note / Time</th></tr></thead>
                <tbody>
                @forelse($history as $approval)
                    @php
                        $statusClass = $approval->status==='APPROVED' ? 'success' : ($approval->status==='REJECTED' ? 'danger' : ($approval->status==='PENDING' ? 'warning' : 'secondary'));
                    @endphp
                    <tr>
                        <td><strong>R{{ $approval->revision_no }}</strong><br><small class="text-muted">Batch #{{ $approval->batch_no ?: 1 }}</small></td>
                        <td>{{ ($approval->assigned_at ?: $approval->created_at)?->format('d M Y h:i A') ?: '—' }}</td>
                        <td>{{ $approval->assigner?->name ?: 'System / Legacy' }}</td>
                        <td><strong>{{ $approval->approver?->name ?: $approval->approver_name ?: 'Former / unavailable user' }}</strong><br><small class="text-muted">{{ $approval->approver?->email ?: $approval->approver_email }}</small></td>
                        <td style="min-width:220px">{{ $approval->dispatch_note ?: '—' }}</td>
                        <td><span class="badge bg-{{ $statusClass }} {{ $statusClass==='warning'?'text-dark':'' }}">{{ $approval->status }}</span></td>
                        <td style="min-width:220px">{{ $approval->comment ?: '—' }}@if($approval->acted_at)<br><small class="text-muted">{{ $approval->acted_at->format('d M Y h:i A') }}</small>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">No approval exchange history yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($voucher->revisions->isNotEmpty())
    <div class="progga-card">
        <div class="progga-card-header"><strong>Voucher Revision Archive</strong></div>
        <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Revision</th><th>Reason</th><th>Archived</th><th>Archived By</th></tr></thead><tbody>
            @foreach($voucher->revisions as $revision)
                <tr><td>R{{ $revision->revision_no }}</td><td>{{ $revision->reason ?: '—' }}</td><td>{{ $revision->created_at?->format('d M Y h:i A') }}</td><td>{{ $revision->archivedBy?->name ?: 'System' }}</td></tr>
            @endforeach
        </tbody></table></div>
    </div>
    @endif
</main>
@endsection
@section('script')
<script>
function submitApprovalDispatch(){
    const users=[...document.querySelectorAll('.approval-user:checked')];
    const note=(document.getElementById('approvalDispatchNote')?.value||'').trim();
    if(!users.length){if(typeof Swal!=='undefined')Swal.fire('Select user','Select at least one approval user.','warning');else alert('Select at least one approval user.');return;}
    if(!note){if(typeof Swal!=='undefined')Swal.fire('Note required','Enter a note for this approval dispatch.','warning');else alert('Enter a note for this approval dispatch.');return;}
    const form=document.getElementById('approvalDispatchForm');
    if(typeof Swal==='undefined'){if(confirm('Send this voucher to the selected user(s)?'))form.submit();return;}
    Swal.fire({title:'Send approval request?',text:'The selected user(s) will receive a new approval task with this note.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, send'}).then(r=>{if(r.isConfirmed)form.submit();});
}
</script>
@endsection
