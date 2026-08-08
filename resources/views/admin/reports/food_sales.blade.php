@extends('admin.master.master')
@section('title', 'Food Wise Sales — TableTrack RMS')
@section('body')
<main class="progga-content">
  <div class="progga-page-header">
      <div><h1 class="progga-page-title">Food Wise Sales</h1></div>
      <div style="display:flex;gap:8px;">
          <button type="button" onclick="exportReport('pdf', 'food_sales')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
          <button type="button" onclick="exportReport('excel', 'food_sales')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button>
      </div>
  </div>

  <div class="progga-card" style="margin-bottom:16px;">
      @include('admin.reports.partials.filter_component')
  </div>

  <div class="row g-3 mb-4">
      <div class="col-md-6"><div class="progga-stat-card"><div class="progga-stat-icon primary"><i class="bi bi-basket-fill"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Food Qty Sold</div><div class="progga-stat-value" id="foodQtyLabel">{{ number_format($totalFoodQty) }}</div></div></div></div>
      <div class="col-md-6"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-currency-exchange"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Food Sales Amount</div><div class="progga-stat-value" id="foodSalesLabel">৳{{ number_format($totalFoodSales, 2) }}</div></div></div></div>
  </div>

  <div class="progga-card">
    <div class="progga-table-wrapper" style="border:none;border-radius:0;">
      <table class="progga-table">
          <thead><tr><th>Rank</th><th>Food Item</th><th>Total Qty Sold</th><th>Order Count</th><th>Total Sales</th></tr></thead>
          <tbody id="foodTableContainer">
              @include('admin.reports.partials.food_table_rows')
          </tbody>
      </table>
    </div>
    <div class="progga-card-footer" id="foodPaginationWrap" style="display:flex; justify-content:flex-end;">
         @include('admin.reports.partials.custom_pagination', ['paginator' => $foodRows])
    </div>
  </div>
</main>
<script>
function updateReportDOM(data) {
    document.getElementById('foodTableContainer').innerHTML = data.html;
    document.getElementById('foodQtyLabel').innerText = data.qty;
    document.getElementById('foodSalesLabel').innerText = data.sales;
    if (data.pagination && document.getElementById('foodPaginationWrap')) document.getElementById('foodPaginationWrap').innerHTML = data.pagination;
}
</script>
@endsection
