@extends('admin.master.master')
@section('title', 'Payment Wise Sales — TableTrack RMS')

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    /* Premium Filter UI CSS */
    .premium-filter-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border: 1px solid rgba(33, 53, 42, 0.08);
        padding: 20px;
        margin-bottom: 20px;
    }
    .filter-header {
        font-size: 15px;
        font-weight: 700;
        color: var(--progga-primary);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .premium-filter-card .form-label {
        font-size: 12px;
        font-weight: 700;
        color: #555;
        margin-bottom: 5px;
    }
    .premium-filter-card .form-control, .premium-filter-card .form-select {
        border: 1.5px solid #eaeaea;
        border-radius: 8px;
        font-size: 13px;
        box-shadow: none;
        transition: all 0.3s ease;
    }
    .premium-filter-card .form-control:focus, .premium-filter-card .form-select:focus {
        border-color: var(--progga-primary);
        box-shadow: 0 0 0 0.2rem rgba(33, 53, 42, 0.1);
    }
</style>
@endsection

@section('body')
<main class="progga-content">
  <div class="progga-page-header">
      <div><h1 class="progga-page-title">Payment Type Wise Sales</h1></div>
      <div style="display:flex;gap:8px;">
          <button type="button" onclick="exportReport('pdf', 'payment_type_sales')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
          <button type="button" onclick="exportReport('excel', 'payment_type_sales')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button>
      </div>
  </div>

  <!-- Advanced Premium Filter -->
  <div class="premium-filter-card">
      <div class="filter-header">
          <i class="bi bi-funnel-fill"></i> Advanced Report Filter
      </div>
      <form id="reportFilterForm" class="row g-3 align-items-end">
          <div class="col-md-2 col-sm-6">
              <label class="form-label">Filter Type</label>
              <select name="filter_type" id="filterType" class="form-select">
                  <option value="year" {{ $filterType == 'year' ? 'selected' : '' }}>Year Wise</option>
                  <option value="month" {{ $filterType == 'month' ? 'selected' : '' }}>Month Wise</option>
                  <option value="date" {{ $filterType == 'date' ? 'selected' : '' }}>Date Range</option>
              </select>
          </div>

          <div class="col-md-2 col-sm-6" id="yearField" style="display: {{ in_array($filterType, ['year', 'month']) ? 'block' : 'none' }};">
              <label class="form-label">Year</label>
              <select name="year" id="filterYear" class="form-select">
                  @foreach($yearOptions as $yr)
                      <option value="{{ $yr }}" {{ (int)$year === (int)$yr ? 'selected' : '' }}>{{ $yr }}</option>
                  @endforeach
              </select>
          </div>

          <div class="col-md-2 col-sm-6" id="monthField" style="display: {{ $filterType == 'month' ? 'block' : 'none' }};">
              <label class="form-label">Month</label>
              <select name="month" id="filterMonth" class="form-select">
                  @for($m=1; $m<=12; $m++)
                      <option value="{{ $m }}" {{ (int)$month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null,$m,1)->format('F') }}</option>
                  @endfor
              </select>
          </div>

          <div class="col-md-4 col-sm-12" id="dateRangeFields" style="display: {{ $filterType == 'date' ? 'flex' : 'none' }}; gap:10px;">
              <div class="w-50">
                  <label class="form-label">From Date</label>
                  <input type="text" name="start_date" id="startDate" class="form-control datepicker bg-white" value="{{ $startDate->format('Y-m-d') }}" placeholder="YYYY-MM-DD">
              </div>
              <div class="w-50">
                  <label class="form-label">To Date</label>
                  <input type="text" name="end_date" id="endDate" class="form-control datepicker bg-white" value="{{ $endDate->format('Y-m-d') }}" placeholder="YYYY-MM-DD">
              </div>
          </div>

          <div class="col-md-3 col-sm-6">
              <label class="form-label">Payment Type</label>
              <select name="payment_method" id="paymentMethod" class="form-select">
                  <option value="">All Payments</option>
                  <option value="Cash" {{ ($paymentMethod ?? '') == 'Cash' ? 'selected' : '' }}>Cash</option>
                  <option value="Card" {{ ($paymentMethod ?? '') == 'Card' ? 'selected' : '' }}>Card</option>
                  <option value="Mobile Banking" {{ ($paymentMethod ?? '') == 'Mobile Banking' ? 'selected' : '' }}>Mobile Banking</option>
                  <!-- Split removed to divide data dynamically -->
              </select>
          </div>

          <div class="col-md-2 col-sm-6">
              <a href="{{ url()->current() }}" class="progga-btn progga-btn-outline w-100 d-flex justify-content-center align-items-center" style="height: 38px;">
                  <i class="bi bi-arrow-clockwise me-2"></i> Reset
              </a>
          </div>
      </form>
  </div>

  <div class="row g-3 mb-4" id="paymentCardsContainer">
      @include('admin.reports.partials.payment_cards')
  </div>

  <div class="progga-card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
    <div class="table-responsive">
      <table class="progga-table table table-hover mb-0" style="border:none;">
        <thead style="background: rgba(33, 53, 42, 0.05);">
          <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Table</th>
            <th>Payment Type</th>
            <th>Cash</th>
            <th>Card</th>
            <th>MFC</th>
            <th>Total Paid</th>
          </tr>
        </thead>
        <tbody id="paymentTableContainer">
          @include('admin.reports.partials.payment_table_rows')
        </tbody>
      </table>
    </div>
    <div class="progga-card-footer bg-white border-top p-3" id="paymentPaginationWrap" style="display: flex; justify-content: flex-end;">
         @include('admin.reports.partials.custom_pagination', ['paginator' => $paymentOrders])
    </div>
  </div>
</main>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
$(document).ready(function() {
    const $filterType = $('#filterType');
    const $yearField = $('#yearField');
    const $monthField = $('#monthField');
    const $dateRange = $('#dateRangeFields');
    const $form = $('#reportFilterForm');

    // Flatpickr Initialize
    flatpickr(".datepicker", {
        dateFormat: "Y-m-d",
        allowInput: true,
        onChange: function(selectedDates, dateStr, instance) {
            triggerAjaxFetch();
        }
    });

    // Toggle Fields
    function toggleFields() {
        let val = $filterType.val();

        if (val === 'year') {
            $yearField.show();
            $monthField.hide();
            $dateRange.hide().removeClass('d-flex');
        } else if (val === 'month') {
            $yearField.show();
            $monthField.show();
            $dateRange.hide().removeClass('d-flex');
        } else if (val === 'date') {
            $yearField.hide();
            $monthField.hide();
            $dateRange.css('display', 'flex');
        }
    }

    // Ajax Fetch Method
    function triggerAjaxFetch() {
        let formData = $form.serialize();
        let fetchUrl = window.location.pathname + '?' + formData;

        // Update URL dynamically
        window.history.pushState({}, '', fetchUrl);

        // Add subtle loading effect
        $('#paymentTableContainer').css('opacity', '0.4');

        $.ajax({
            url: fetchUrl,
            type: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(data) {
                if(data.html) {
                    $('#paymentTableContainer').html(data.html).css('opacity', '1');
                    $('#paymentCardsContainer').html(data.cards);
                    if (data.pagination) {
                        $('#paymentPaginationWrap').html(data.pagination);
                    }
                }
            },
            error: function(err) {
                console.error("Error loading report data: ", err);
                $('#paymentTableContainer').css('opacity', '1');
            }
        });
    }

    $filterType.on('change', function() {
        toggleFields();
        triggerAjaxFetch();
    });

    $('#filterYear, #filterMonth, #paymentMethod').on('change', function() {
        triggerAjaxFetch();
    });

    // Initial load check
    toggleFields();
});
</script>
@endsection
