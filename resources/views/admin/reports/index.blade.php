@extends('admin.master.master')
@section('title', 'Sales & Order Report — TableTrack RMS')
@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.report-filter-line{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.report-filter-line .progga-form-group{margin:0}.report-filter-line .progga-form-label{margin-bottom:3px}.report-filter-line input,.report-filter-line select{min-width:145px}.filter-year-field,.filter-month-field,.filter-date-range{display:none}.filter-year-field.active,.filter-month-field.active{display:block}.filter-date-range.active{display:flex;gap:10px;flex-wrap:wrap}.report-period-badge{display:inline-flex;align-items:center;gap:6px}.report-pagination-wrap{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.report-pagination{margin-left:auto;display:flex;gap:5px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.report-page-link{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border:1px solid var(--progga-border-light);border-radius:8px;background:#fff;color:var(--progga-primary);font-size:12px;font-weight:800;text-decoration:none}.report-page-link:hover{background:rgba(33,53,42,.06);color:var(--progga-primary)}.report-page-link.active{background:var(--progga-primary);color:#fff;border-color:var(--progga-primary)}.report-page-link.disabled{opacity:.45;pointer-events:none;color:var(--progga-text-muted)}.report-loading{opacity:.55;pointer-events:none}.payment-card{border:1px solid var(--progga-border-light);border-radius:14px;padding:18px;background:#fff;height:100%}.payment-card-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(33,53,42,.08);color:var(--progga-primary);font-size:22px;margin-bottom:12px}.payment-card-label{font-size:13px;font-weight:900;color:var(--progga-text-muted);text-transform:uppercase}.payment-card-amount{font-size:26px;font-weight:900;color:var(--progga-primary);margin:4px 0}.payment-card-meta{font-size:12px;font-weight:700;color:var(--progga-text-muted);display:flex;justify-content:space-between}
</style>
@endsection
@section('body')
<main class="progga-content">
  <div class="progga-page-header"><div><h1 class="progga-page-title">Sales &amp; Order Report</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div><div style="display:flex;gap:8px;"><a href="{{ route('reports.export.pdf', array_merge(request()->all(), ['report' => 'sales_order'])) }}" target="_blank" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a><a href="{{ route('reports.export.csv', array_merge(request()->all(), ['report' => 'sales_order'])) }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a></div></div>
  <div class="progga-card" style="margin-bottom:16px;"><form action="{{ route('reports.sales_order') }}" method="GET" class="progga-card-body report-filter-line js-report-filter-form">
<div class="progga-form-group"><label class="progga-form-label">Filter Type</label><select name="filter_type" class="progga-select"><option value="year" {{ $filterType == 'year' ? 'selected' : '' }}>Year Wise</option><option value="month" {{ $filterType == 'month' ? 'selected' : '' }}>Month Wise</option><option value="date" {{ $filterType == 'date' ? 'selected' : '' }}>From Date - To Date</option></select></div>
<div class="progga-form-group filter-year-field {{ in_array($filterType, ['year', 'month']) ? 'active' : '' }}" data-filter-field="year"><label class="progga-form-label">Year</label><select name="year" class="progga-select">@foreach($yearOptions as $yr)<option value="{{ $yr }}" {{ (int)$year === (int)$yr ? 'selected' : '' }}>{{ $yr }}</option>@endforeach</select></div>
<div class="progga-form-group filter-month-field {{ $filterType == 'month' ? 'active' : '' }}" data-filter-field="month"><label class="progga-form-label">Month</label><select name="month" class="progga-select">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ (int)$month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null,$m,1)->format('F') }}</option>@endfor</select></div>
<div class="filter-date-range {{ $filterType == 'date' ? 'active' : '' }}" data-filter-field="date-range"><div class="progga-form-group"><label class="progga-form-label">From Date</label><input type="text" name="start_date" class="progga-form-control js-report-date" value="{{ $startDate->format('Y-m-d') }}" autocomplete="off"></div><div class="progga-form-group"><label class="progga-form-label">To Date</label><input type="text" name="end_date" class="progga-form-control js-report-date" value="{{ $endDate->format('Y-m-d') }}" autocomplete="off"></div></div>
<a href="{{ route('reports.sales_order') }}" class="progga-btn progga-btn-outline progga-btn-sm js-report-reset"><i class="bi bi-x-circle"></i> Reset</a></form></div>
  <div class="row g-3 mb-4"><div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-currency-dollar"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Revenue</div><div class="progga-stat-value">৳{{ number_format($totalRevenue, 0) }}</div></div></div></div><div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon primary"><i class="bi bi-receipt"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Orders</div><div class="progga-stat-value">{{ $totalOrders }}</div></div></div></div><div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon success"><i class="bi bi-graph-up"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Avg. Order Value</div><div class="progga-stat-value">৳{{ number_format($avgOrderValue, 0) }}</div></div></div></div><div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon warning"><i class="bi bi-people"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Unique Customers</div><div class="progga-stat-value">{{ $uniqueCustomers }}</div></div></div></div></div>
  <div class="progga-card"><div class="progga-card-header"><div class="progga-card-title">Sales &amp; Order Summary</div><span class="progga-badge progga-badge-secondary report-period-badge"><i class="bi bi-calendar3"></i> {{ $startDate->format('d M, Y') }} - {{ $endDate->format('d M, Y') }}</span></div><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th>Period</th><th>Total Sale</th><th>Discount Report</th><th>Total Order</th></tr></thead><tbody>@forelse($periodRows as $row)<tr><td><strong>{{ $row['period'] }}</strong></td><td><strong>৳{{ number_format($row['total_sale'], 0) }}</strong></td><td><strong>৳{{ number_format($row['total_discount'] ?? 0, 0) }}</strong></td><td>{{ number_format($row['total_order']) }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4 text-muted">No sales/order data found.</td></tr>@endforelse</tbody><tfoot><tr><th>Total</th><th>৳{{ number_format($periodTotalSale, 0) }}</th><th>৳{{ number_format($periodTotalDiscount ?? collect($periodRows)->sum('total_discount'), 0) }}</th><th>{{ number_format($periodTotalOrder) }}</th></tr></tfoot></table></div><div class="progga-card-footer">@include('admin.reports.partials.custom_pagination', ['paginator' => $periodRows])</div></div>
</main>
@endsection
@section('script')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
(function(){
  let reportAjaxTimer = null;

  function mainContent(){
    return document.querySelector('main.progga-content');
  }

  function toggleReportFields(form){
    const filterType = form.querySelector('[name="filter_type"]');
    const yearField = form.querySelector('[data-filter-field="year"]');
    const monthField = form.querySelector('[data-filter-field="month"]');
    const dateRange = form.querySelector('[data-filter-field="date-range"]');
    if(!filterType) return;
    yearField?.classList.toggle('active', filterType.value === 'year' || filterType.value === 'month');
    monthField?.classList.toggle('active', filterType.value === 'month');
    dateRange?.classList.toggle('active', filterType.value === 'date');
  }

  function initFlatpickr(form){
    form.querySelectorAll('.js-report-date').forEach(function(input){
      if(input._flatpickr) return;
      flatpickr(input, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        onChange: function(){
          const start = form.querySelector('[name="start_date"]')?.value;
          const end = form.querySelector('[name="end_date"]')?.value;
          if(start && end) submitReportForm(form);
        }
      });
    });
  }

  function initReportForms(){
    document.querySelectorAll('.js-report-filter-form').forEach(function(form){
      toggleReportFields(form);
      initFlatpickr(form);
    });
  }

  function submitReportForm(form){
    clearTimeout(reportAjaxTimer);
    reportAjaxTimer = setTimeout(function(){
      const params = new URLSearchParams(new FormData(form));
      params.delete('page');
      const url = form.action + '?' + params.toString();
      loadReportUrl(url, true);
    }, 180);
  }

  function loadReportUrl(url, pushState){
    const content = mainContent();
    if(content) content.classList.add('report-loading');

    fetch(url, {
      headers: {'X-Requested-With': 'XMLHttpRequest'},
      credentials: 'same-origin'
    })
    .then(function(response){ return response.text(); })
    .then(function(html){
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const freshContent = doc.querySelector('main.progga-content');
      const currentContent = mainContent();
      if(freshContent && currentContent){
        currentContent.replaceWith(freshContent);
        if(pushState) window.history.pushState({}, '', url);
        initReportForms();
      } else {
        window.location.href = url;
      }
    })
    .catch(function(){ window.location.href = url; })
    .finally(function(){
      const updatedContent = mainContent();
      if(updatedContent) updatedContent.classList.remove('report-loading');
    });
  }

  document.addEventListener('change', function(event){
    const field = event.target.closest('.js-report-filter-form select');
    if(!field) return;
    const form = field.closest('.js-report-filter-form');
    toggleReportFields(form);
    submitReportForm(form);
  });

  document.addEventListener('input', function(event){
    const field = event.target.closest('.js-report-filter-form .js-report-date');
    if(!field) return;
    const form = field.closest('.js-report-filter-form');
    const start = form.querySelector('[name="start_date"]')?.value;
    const end = form.querySelector('[name="end_date"]')?.value;
    if(start && end) submitReportForm(form);
  });

  document.addEventListener('click', function(event){
    const resetLink = event.target.closest('.js-report-reset');
    if(resetLink){
      event.preventDefault();
      loadReportUrl(resetLink.href, true);
      return;
    }

    const pageLink = event.target.closest('.report-pagination a:not(.disabled)');
    if(pageLink){
      event.preventDefault();
      loadReportUrl(pageLink.href, true);
    }
  });

  window.addEventListener('popstate', function(){ loadReportUrl(window.location.href, false); });
  document.addEventListener('DOMContentLoaded', initReportForms);
})();
</script>
@endsection
