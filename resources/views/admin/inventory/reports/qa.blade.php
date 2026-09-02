@extends('admin.master.master')
@section('title','Inventory Rollout QA')
@section('body')
<main class="progga-content">
<div class="progga-page-header"><div><h1 class="progga-page-title">Phase 10 Inventory Rollout QA</h1><p class="text-muted mb-0">Read-only live verification of branch isolation, ledger linkage, idempotency, recipe readiness and exception coverage.</p></div><a href="{{ route('reports.inventory.index') }}" class="progga-btn progga-btn-light">Report Home</a></div>
<div class="row g-3 mb-4"><div class="col-md-6"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Checks Passed</div><div class="fs-3 fw-bold text-success">{{ $passed }}</div></div></div></div><div class="col-md-6"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Checks Needing Attention</div><div class="fs-3 fw-bold {{ $failed ? 'text-danger':'text-success' }}">{{ $failed }}</div></div></div></div></div>
<div class="progga-card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Check</th><th>Status</th><th class="text-end">Issues</th><th>Meaning</th></tr></thead><tbody>@foreach($checks as $check)<tr><td><strong>{{ $check['name'] }}</strong></td><td>@if($check['passed'])<span class="badge bg-success">PASS</span>@else<span class="badge bg-danger">ACTION REQUIRED</span>@endif</td><td class="text-end">{{ number_format($check['issues']) }}</td><td class="text-muted">{{ $check['description'] }}</td></tr>@endforeach</tbody></table></div></div>
<div class="alert alert-warning mt-4 mb-0"><strong>Deployment rule:</strong> resolve failed checks before enabling inventory tracking broadly. Opening balances must be posted through OPENING_STOCK movements after physical stock sign-off; do not seed balances by direct updates.</div>
</main>
@endsection
