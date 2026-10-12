<style>
.acc-shell{--navy:#122743;--teal:#117c8c} .acc-shell .acc-hero{background:linear-gradient(110deg,#122743,#214c70);color:#fff;border-radius:15px;padding:25px;margin-bottom:20px}
.acc-shell .acc-hero h1{font-size:25px;font-weight:750;margin:0 0 5px}.acc-shell .acc-hero p{opacity:.85;margin:0}
.acc-shell .acc-tabs{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:20px}.acc-shell .acc-tabs a{display:inline-block;padding:9px 13px;background:#fff;border:1px solid #dee5ed;border-radius:9px;text-decoration:none;color:#34445a;font-size:13px;font-weight:600}.acc-shell .acc-tabs a.active{background:#117c8c;color:white;border-color:#117c8c}
.acc-shell .acc-panel{background:#fff;border:1px solid #e4ebf0;border-radius:14px;padding:21px;margin-bottom:18px;box-shadow:0 3px 14px rgba(20,49,70,.035)}
.acc-shell .acc-panel h3{font-size:17px;font-weight:700;color:#172d4c;margin-bottom:17px}.acc-shell .table{font-size:13px;vertical-align:middle}.acc-shell .table th{background:#f2f7f9;color:#334c5d}.acc-shell label{font-size:13px;font-weight:600;margin-bottom:5px}.acc-shell .form-control,.acc-shell .form-select{font-size:13px;min-height:38px}
.acc-shell .acc-hint{font-size:12px;color:#617288}.acc-shell .acc-stat{font-weight:800;font-size:25px;color:#123956}.acc-shell .badge{font-size:11px}.acc-shell button{font-size:13px}

.acc-shell .acc-nav-group{margin:0 0 13px}
.acc-shell .acc-nav-heading{font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:#557084;font-weight:800;margin:0 0 7px}
.acc-shell .acc-tabs{margin-bottom:0}
.acc-shell .acc-tabs a:focus-visible{outline:2px solid #117c8c;outline-offset:2px}
.acc-shell .acc-tabs a:hover{border-color:#117c8c;color:#117c8c}
.acc-shell .acc-tabs a.active:hover{color:white}
</style>
<div class="acc-hero"><div class="d-flex justify-content-between flex-wrap gap-3 align-items-center"><div><h1><i class="bi bi-journal-text me-2"></i>Restaurant Accounting</h1><p>Single company ledger · Double-entry · Phases 1–5 / 5</p></div><span class="badge bg-light text-dark px-3 py-2">BDT · 4-decimal precision</span></div></div>
<nav aria-label="Accounting modules" class="mb-3">
@foreach(config('accounting_navigation', []) as $group => $items)
 <div class="acc-nav-group">
  <div class="acc-nav-heading">{{ $group }}</div>
  <div class="acc-tabs">
  @foreach($items as $item)
   @can($item['permission'])
    <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route']) ? 'active' : '' }}"
       @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
   @endcan
  @endforeach
  </div>
 </div>
@endforeach
</nav>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Validation error:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
