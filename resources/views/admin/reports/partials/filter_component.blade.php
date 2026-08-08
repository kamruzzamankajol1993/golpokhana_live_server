<form id="reportFilterForm" class="progga-card-body report-filter-line">
    <div class="progga-form-group">
        <label class="progga-form-label">Filter Type</label>
        <select name="filter_type" id="filterType" class="progga-select">
            <option value="year" {{ $filterType == 'year' ? 'selected' : '' }}>Year Wise</option>
            <option value="month" {{ $filterType == 'month' ? 'selected' : '' }}>Month Wise</option>
            <option value="date" {{ $filterType == 'date' ? 'selected' : '' }}>Date Range</option>
        </select>
    </div>

    <div class="progga-form-group" id="yearField" style="display: {{ in_array($filterType, ['year', 'month']) ? 'block' : 'none' }};">
        <label class="progga-form-label">Year</label>
        <select name="year" id="filterYear" class="progga-select">
            @foreach($yearOptions as $yr)
                <option value="{{ $yr }}" {{ (int)$year === (int)$yr ? 'selected' : '' }}>{{ $yr }}</option>
            @endforeach
        </select>
    </div>

    <div class="progga-form-group" id="monthField" style="display: {{ $filterType == 'month' ? 'block' : 'none' }};">
        <label class="progga-form-label">Month</label>
        <select name="month" id="filterMonth" class="progga-select">
            @for($m=1; $m<=12; $m++)
                <option value="{{ $m }}" {{ (int)$month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
            @endfor
        </select>
    </div>

    <div id="dateRangeFields" style="display: {{ $filterType == 'date' ? 'flex' : 'none' }}; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <div class="progga-form-group">
            <label class="progga-form-label">From Date</label>
            <input type="text" name="start_date" id="startDate" class="progga-form-control datepicker" value="{{ $startDate->format('d-m-Y') }}" placeholder="DD-MM-YYYY" autocomplete="off">
        </div>
        <div class="progga-form-group">
            <label class="progga-form-label">To Date</label>
            <input type="text" name="end_date" id="endDate" class="progga-form-control datepicker" value="{{ $endDate->format('d-m-Y') }}" placeholder="DD-MM-YYYY" autocomplete="off">
        </div>
    </div>

    @if(isset($showPaymentFilter) && $showPaymentFilter)
    <div class="progga-form-group">
        <label class="progga-form-label">Payment Type</label>
        <select name="payment_method" id="paymentMethod" class="progga-select">
            <option value="">All Payments</option>
            <option value="Cash" {{ ($paymentMethod ?? '') == 'Cash' ? 'selected' : '' }}>Cash</option>
            <option value="Card" {{ ($paymentMethod ?? '') == 'Card' ? 'selected' : '' }}>Card</option>
            <option value="Mobile Banking" {{ ($paymentMethod ?? '') == 'Mobile Banking' ? 'selected' : '' }}>Mobile Banking</option>
            <option value="Split" {{ ($paymentMethod ?? '') == 'Split' ? 'selected' : '' }}>Split</option>
        </select>
    </div>
    @endif

    <div style="margin-top:auto;display:flex;gap:8px;">
        <button type="button" class="progga-btn progga-btn-primary progga-btn-sm" id="applyReportFilter" style="height:38px;">
            <i class="bi bi-funnel"></i> Apply
        </button>
        <a href="{{ url()->current() }}" class="progga-btn progga-btn-outline progga-btn-sm" style="height:38px; display:inline-flex; align-items:center;">
            <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
    </div>
</form>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
$(document).ready(function() {
    const $filterType = $('#filterType');
    const $yearField = $('#yearField');
    const $monthField = $('#monthField');
    const $dateRange = $('#dateRangeFields');
    const $form = $('#reportFilterForm');

    flatpickr('.datepicker', {
        dateFormat: 'd-m-Y',
        allowInput: true
    });

    function toggleFields() {
        const val = $filterType.val();

        if (val === 'year') {
            $yearField.show();
            $monthField.hide();
            $dateRange.hide();
        } else if (val === 'month') {
            $yearField.show();
            $monthField.show();
            $dateRange.hide();
        } else {
            $yearField.hide();
            $monthField.hide();
            $dateRange.css('display', 'flex');
        }
    }

    window.triggerReportFetch = function() {
        const formData = $form.serialize();
        const fetchUrl = window.location.pathname + '?' + formData;
        window.history.pushState({}, '', fetchUrl);

        $('#salesReportCard').addClass('report-loading');
        $.ajax({
            url: fetchUrl,
            type: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(data) {
                if (typeof updateReportDOM === 'function') {
                    updateReportDOM(data);
                }
            },
            error: function(err) {
                console.error('Report data loading failed.', err);
            },
            complete: function() {
                $('#salesReportCard').removeClass('report-loading');
            }
        });
    };

    $filterType.on('change', function() {
        toggleFields();
        window.triggerReportFetch();
    });

    $('#filterYear, #filterMonth, #paymentMethod').on('change', function() {
        window.triggerReportFetch();
    });

    $('#applyReportFilter').on('click', function() {
        window.triggerReportFetch();
    });

    toggleFields();
});
</script>
