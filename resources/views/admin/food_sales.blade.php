@extends('admin.master.master')
@section('title', 'Food Wise Sales Report — TableTrack RMS')
@section('css')
<style>
.report-filter-line{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.report-filter-line .progga-form-group{margin:0}.report-filter-line .progga-form-label{margin-bottom:3px}.report-filter-line input,.report-filter-line select{min-width:145px}.filter-year-field,.filter-month-field,.filter-date-range{display:none}.filter-year-field.active,.filter-month-field.active{display:block}.filter-date-range.active{display:flex;gap:10px;flex-wrap:wrap}.progga-chart-container{position:relative;width:100%}.report-period-badge{display:inline-flex;align-items:center;gap:6px}.report-pagination-wrap{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.report-pagination{display:flex;gap:5px;align-items:center;flex-wrap:wrap}.report-page-link{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border:1px solid var(--progga-border-light);border-radius:8px;background:#fff;color:var(--progga-primary);font-size:12px;font-weight:800;text-decoration:none}.report-page-link:hover{background:rgba(33,53,42,.06);color:var(--progga-primary)}.report-page-link.active{background:var(--progga-primary);color:#fff;border-color:var(--progga-primary)}.report-page-link.disabled{opacity:.45;pointer-events:none;color:var(--progga-text-muted)}.payment-card{border:1px solid var(--progga-border-light);border-radius:14px;padding:18px;background:#fff;height:100%}.payment-card-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(33,53,42,.08);color:var(--progga-primary);font-size:22px;margin-bottom:12px}.payment-card-label{font-size:13px;font-weight:900;color:var(--progga-text-muted);text-transform:uppercase}.payment-card-amount{font-size:26px;font-weight:900;color:var(--progga-primary);margin:4px 0}.payment-card-meta{font-size:12px;font-weight:700;color:var(--progga-text-muted);display:flex;justify-content:space-between}
</style>
@endsection
@section('body')
<main class="progga-content">
  <div class="progga-page-header"><div><h1 class="progga-page-title">Food Wise Sales</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div><div style="display:flex;gap:8px;"><a href="{{ route('reports.export.pdf', array_merge(request()->all(), ['report' => 'food_sales'])) }}" target="_blank" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a><a href="{{ route('reports.export.csv', array_merge(request()->all(), ['report' => 'food_sales'])) }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a></div></div>
  <div class="progga-card" style="margin-bottom:16px;"><form action="{{ route('reports.food_sales') }}" method="GET" class="progga-card-body report-filter-line">
<div class="progga-form-group"><label class="progga-form-label">Filter Type</label><select name="filter_type" id="filterType" class="progga-select"><option value="year" {{ $filterType == 'year' ? 'selected' : '' }}>Year Wise</option><option value="month" {{ $filterType == 'month' ? 'selected' : '' }}>Month Wise</option><option value="date" {{ $filterType == 'date' ? 'selected' : '' }}>From Date - To Date</option></select></div>
<div class="progga-form-group filter-year-field {{ in_array($filterType, ['year', 'month']) ? 'active' : '' }}" id="yearField"><label class="progga-form-label">Year</label><select name="year" class="progga-select">@foreach($yearOptions as $yr)<option value="{{ $yr }}" {{ (int)$year === (int)$yr ? 'selected' : '' }}>{{ $yr }}</option>@endforeach</select></div>
<div class="progga-form-group filter-month-field {{ $filterType == 'month' ? 'active' : '' }}" id="monthField"><label class="progga-form-label">Month</label><select name="month" class="progga-select">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ (int)$month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null,$m,1)->format('F') }}</option>@endfor</select></div>
<div class="filter-date-range {{ $filterType == 'date' ? 'active' : '' }}" id="dateRangeFields"><div class="progga-form-group"><label class="progga-form-label">From Date</label><input type="date" name="start_date" class="progga-form-control" value="{{ $startDate->format('Y-m-d') }}"></div><div class="progga-form-group"><label class="progga-form-label">To Date</label><input type="date" name="end_date" class="progga-form-control" value="{{ $endDate->format('Y-m-d') }}"></div></div>
<button type="submit" class="progga-btn progga-btn-primary progga-btn-sm"><i class="bi bi-funnel"></i> Apply Filter</button><a href="{{ route('reports.food_sales') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-x-circle"></i> Clear</a></form></div>
  <div class="row g-3 mb-4"><div class="col-md-6"><div class="progga-stat-card"><div class="progga-stat-icon primary"><i class="bi bi-basket-fill"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Food Qty Sold</div><div class="progga-stat-value">{{ number_format($totalFoodQty) }}</div></div></div></div><div class="col-md-6"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-currency-exchange"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Food Sales Amount</div><div class="progga-stat-value">৳{{ number_format($totalFoodSales, 2) }}</div></div></div></div></div>
  <div class="progga-card"><div class="progga-card-header"><div class="progga-card-title">Top Selling Food Items</div><span class="progga-badge progga-badge-secondary report-period-badge"><i class="bi bi-calendar3"></i> {{ $startDate->format('d M, Y') }} - {{ $endDate->format('d M, Y') }}</span></div><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th>Rank</th><th>Food Item</th><th>Total Qty Sold</th><th>Order Count</th><th>Total Sales</th></tr></thead><tbody>@forelse($foodRows as $index => $food)<tr><td><strong>#{{ $foodRows->firstItem() + $index }}</strong></td><td>{{ $food->product_name }}</td><td><strong>{{ number_format($food->total_qty) }}</strong></td><td>{{ number_format($food->orders_count) }}</td><td><strong>৳{{ number_format($food->total_sales, 2) }}</strong></td></tr>@empty<tr><td colspan="5" class="text-center py-4 text-muted">No food sales found.</td></tr>@endforelse</tbody></table></div><div class="progga-card-footer">@include('admin.reports.partials.custom_pagination', ['paginator' => $foodRows])</div></div>
</main>
@endsection
@section('script')

<script>
document.addEventListener('DOMContentLoaded',function(){
  const filterType=document.getElementById('filterType');
  const yearField=document.getElementById('yearField');
  const monthField=document.getElementById('monthField');
  const dateRange=document.getElementById('dateRangeFields');
  function toggleFields(){
    if(!filterType) return;
    yearField?.classList.toggle('active', filterType.value==='year' || filterType.value==='month');
    monthField?.classList.toggle('active', filterType.value==='month');
    dateRange?.classList.toggle('active', filterType.value==='date');
  }
  filterType?.addEventListener('change',toggleFields);
  toggleFields();
});
</script>

@endsection
