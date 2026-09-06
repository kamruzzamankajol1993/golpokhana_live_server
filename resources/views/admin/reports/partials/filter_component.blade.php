@php
    $showAll = (bool)($showAllFilterType ?? false);
    $showPartner = (bool)($showDeliveryPartnerFilter ?? false);
    $showPayment = (bool)($showPaymentFilter ?? false);
    $showWaiter = (bool)($showWaiterFilter ?? false);
    $showSearch = (bool)($showSearchFilter ?? false);
    $showReportView = (bool)($showReportViewFilter ?? false);
    $currentFilterType = $filterType ?? 'year';
    $currentReportView = $reportView ?? 'session';
    $reportDateValue = isset($reportDate) && $reportDate ? $reportDate->format('d-m-Y') : now()->format('d-m-Y');
    $businessDateValue = isset($businessDate) && $businessDate ? $businessDate->format('d-m-Y') : now()->format('d-m-Y');
    $rangeStartValue = isset($startDate) && $startDate ? $startDate->format('d-m-Y') : now()->startOfMonth()->format('d-m-Y');
    $rangeEndValue = isset($endDate) && $endDate ? $endDate->format('d-m-Y') : now()->format('d-m-Y');
@endphp

<style>
    .report-filter-line{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;padding:14px 16px;background:#fff;border-radius:12px}
    .report-filter-line .progga-form-group{margin:0;min-width:145px}
    .report-filter-line .progga-form-label{margin-bottom:4px;font-size:11px;font-weight:800;color:#66736c;text-transform:uppercase;letter-spacing:.025em}
    .report-filter-line .progga-form-control,.report-filter-line .progga-select{height:38px;min-width:145px;font-size:12px}
    .report-filter-line .report-search-field{min-width:260px;flex:1 1 260px}
    .report-filter-line .report-filter-actions{display:flex;align-items:center;gap:7px;margin-left:auto}
    .report-filter-period{padding:7px 11px;border-radius:9px;background:rgba(33,53,42,.055);font-size:11px;font-weight:800;color:var(--progga-primary);white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis}
    .enhanced-report-table{width:100%;border-collapse:separate!important;border-spacing:0!important}
    .enhanced-report-table thead th{white-space:nowrap;font-size:11px!important;font-weight:800!important;text-transform:uppercase;letter-spacing:.02em;background:#f5f7f6!important;color:#39433e;border-bottom:1px solid #dfe5e1!important;padding:11px 10px!important}
    .enhanced-report-table tbody td{font-size:12px;padding:10px!important;vertical-align:middle!important;border-bottom:1px solid #edf0ee!important}
    .enhanced-report-table tbody tr:nth-child(even){background:#fbfcfb}
    .enhanced-report-table tbody tr:hover{background:#f4f8f5}
    .report-table-shell{overflow-x:auto;border:1px solid #e4e9e6;border-radius:12px;background:#fff}
    .report-sl-badge{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:26px;padding:0 7px;border-radius:8px;background:#edf2ef;color:#263b31;font-size:11px;font-weight:900}
    .report-loading{opacity:.58;pointer-events:none}
    @media(max-width:767px){.report-filter-line .progga-form-group,.report-filter-line .report-search-field{width:100%;min-width:100%}.report-filter-line .progga-form-control,.report-filter-line .progga-select{width:100%}.report-filter-line .report-filter-actions{width:100%;margin-left:0}.report-filter-period{width:100%}}
</style>

<form id="reportFilterForm" class="report-filter-line" autocomplete="off" @if(!empty($combinedPreviewUrl)) data-combined-preview-url="{{ $combinedPreviewUrl }}" @endif>
    <div class="progga-form-group">
        <label class="progga-form-label">Filter Type</label>
        <select name="filter_type" id="filterType" class="progga-select">
            @if($showAll)<option value="all" {{ $currentFilterType === 'all' ? 'selected' : '' }}>All Data</option>@endif
            <option value="day" {{ $currentFilterType === 'day' ? 'selected' : '' }}>Date Wise</option>
            <option value="month" {{ $currentFilterType === 'month' ? 'selected' : '' }}>Month Wise</option>
            <option value="year" {{ $currentFilterType === 'year' ? 'selected' : '' }}>Year Wise</option>
            <option value="range" {{ $currentFilterType === 'range' ? 'selected' : '' }}>From Date - To Date</option>
            <option value="hour" {{ $currentFilterType === 'hour' ? 'selected' : '' }}>Hour Wise</option>
            <option value="business_day" {{ $currentFilterType === 'business_day' ? 'selected' : '' }}>Business Day</option>
        </select>
    </div>

    @if($showReportView)
        <div class="progga-form-group">
            <label class="progga-form-label">Report View</label>
            <select name="report_view" id="reportViewFilter" class="progga-select">
                <option value="session" {{ $currentReportView === 'session' ? 'selected' : '' }}>Session Wise</option>
                <option value="combined" {{ $currentReportView === 'combined' ? 'selected' : '' }}>Combined</option>
            </select>
        </div>
    @endif

    <div class="progga-form-group filter-field filter-year" style="display:none">
        <label class="progga-form-label">Year</label>
        <select name="year" id="filterYear" class="progga-select">
            @foreach(($yearOptions ?? range(now()->year + 1, now()->year - 10)) as $yr)
                <option value="{{ $yr }}" {{ (int)($year ?? now()->year) === (int)$yr ? 'selected' : '' }}>{{ $yr }}</option>
            @endforeach
        </select>
    </div>

    <div class="progga-form-group filter-field filter-month" style="display:none">
        <label class="progga-form-label">Month</label>
        <select name="month" id="filterMonth" class="progga-select">
            @for($m=1;$m<=12;$m++)
                <option value="{{ $m }}" {{ (int)($month ?? now()->month) === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null,$m,1)->format('F') }}</option>
            @endfor
        </select>
    </div>

    <div class="progga-form-group filter-field filter-report-date" style="display:none">
        <label class="progga-form-label">Date</label>
        <input type="text" name="report_date" id="reportDate" class="progga-form-control report-datepicker" value="{{ $reportDateValue }}" placeholder="DD-MM-YYYY">
    </div>

    <div class="progga-form-group filter-field filter-range-start" style="display:none">
        <label class="progga-form-label">From Date</label>
        <input type="text" name="start_date" id="startDate" class="progga-form-control report-datepicker" value="{{ $rangeStartValue }}" placeholder="DD-MM-YYYY">
    </div>
    <div class="progga-form-group filter-field filter-range-end" style="display:none">
        <label class="progga-form-label">To Date</label>
        <input type="text" name="end_date" id="endDate" class="progga-form-control report-datepicker" value="{{ $rangeEndValue }}" placeholder="DD-MM-YYYY">
    </div>

    <div class="progga-form-group filter-field filter-start-time" style="display:none">
        <label class="progga-form-label">Start Time</label>
        <input type="time" name="start_time" id="filterStartTime" class="progga-form-control" step="60" value="{{ $startTime ?? '00:00' }}">
    </div>
    <div class="progga-form-group filter-field filter-end-time" style="display:none">
        <label class="progga-form-label">End Time</label>
        <input type="time" name="end_time" id="filterEndTime" class="progga-form-control" step="60" value="{{ $endTime ?? '23:59' }}">
    </div>

    <div class="progga-form-group filter-field filter-business-date" style="display:none">
        <label class="progga-form-label">Business Date</label>
        <input type="text" name="business_date" id="businessDate" class="progga-form-control report-datepicker" value="{{ $businessDateValue }}" placeholder="DD-MM-YYYY">
    </div>

    @if($showPartner)
        <div class="progga-form-group">
            <label class="progga-form-label">{{ isset($deliveryPartners) ? 'Delivery Partner' : 'Order Channel' }}</label>
            <select name="delivery_partner" id="deliveryPartnerFilter" class="progga-select">
                @if(isset($deliveryPartners))
                    <option value="all" {{ ($selectedDeliveryPartnerId ?? 'all') === 'all' ? 'selected' : '' }}>ALL</option>
                    @foreach($deliveryPartners as $partner)
                        <option value="{{ $partner->id }}" {{ (string)($selectedDeliveryPartnerId ?? 'all') === (string)$partner->id ? 'selected' : '' }}>{{ $partner->name }}</option>
                    @endforeach
                @else
                    <option value="" {{ empty($deliveryPartner ?? '') ? 'selected' : '' }}>All</option>
                    @foreach(($deliveryPartnerOptions ?? []) as $partnerValue => $partnerLabel)
                        <option value="{{ $partnerValue }}" {{ ($deliveryPartner ?? '') === $partnerValue ? 'selected' : '' }}>{{ $partnerLabel }}</option>
                    @endforeach
                @endif
            </select>
        </div>
    @endif

    @if($showPayment)
        <div class="progga-form-group">
            <label class="progga-form-label">Payment Type</label>
            <select name="payment_method" id="paymentMethod" class="progga-select">
                <option value="">All Payments</option>
                <option value="Cash" {{ ($paymentMethod ?? '') === 'Cash' ? 'selected' : '' }}>Cash</option>
                <option value="Card" {{ ($paymentMethod ?? '') === 'Card' ? 'selected' : '' }}>Bank / Card</option>
                <option value="Mobile Banking" {{ ($paymentMethod ?? '') === 'Mobile Banking' ? 'selected' : '' }}>MFS</option>
                <option value="Split" {{ ($paymentMethod ?? '') === 'Split' ? 'selected' : '' }}>Split</option>
            </select>
        </div>
    @endif

    @if($showWaiter)
        <div class="progga-form-group">
            <label class="progga-form-label">Waiter</label>
            <select name="user_id" id="waiterFilter" class="progga-select">
                <option value="">All Waiters</option>
                @foreach(($waiters ?? collect()) as $waiter)
                    <option value="{{ $waiter->id }}" {{ (int)($selectedUserId ?? 0) === (int)$waiter->id ? 'selected' : '' }}>{{ $waiter->name ?: trim(($waiter->first_name ?? '').' '.($waiter->last_name ?? '')) }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if($showSearch)
        <div class="progga-form-group report-search-field">
            <label class="progga-form-label">Search</label>
            <input type="search" name="search" id="reportSearchInput" class="progga-form-control" value="{{ request('search') }}" placeholder="{{ $searchPlaceholder ?? 'Search records...' }}">
        </div>
    @endif

    <div class="report-filter-actions">
        <button type="submit" class="progga-btn progga-btn-primary progga-btn-sm" id="applyReportFilter" style="height:38px"><i class="bi bi-funnel"></i> Filter</button>
        <a href="{{ url()->current() }}" class="progga-btn progga-btn-outline progga-btn-sm" style="height:38px;display:inline-flex;align-items:center"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </div>

    <div class="report-filter-period" id="activeFilterLabel">{{ $filterLabel ?? '' }}</div>
</form>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
(function($){
    if (!$) return;
    const $form = $('#reportFilterForm');
    if (!$form.length) return;
    let reportRequest = null;
    let searchTimer = null;

    if (window.flatpickr) {
        flatpickr('.report-datepicker', {dateFormat:'d-m-Y', allowInput:true});
    }

    function toggleReportFilterFields(){
        const type = $('#filterType').val();
        $('.filter-field').hide();
        if (type === 'year') $('.filter-year').show();
        if (type === 'month') $('.filter-year,.filter-month').show();
        if (type === 'day') $('.filter-report-date').show();
        if (type === 'range') $('.filter-range-start,.filter-range-end').show();
        if (type === 'hour') $('.filter-report-date,.filter-start-time,.filter-end-time').show();
        if (type === 'business_day') $('.filter-business-date').show();
    }

    function openReportLoader(){
        if (window.Swal) {
            Swal.fire({
                title:'Loading report...',
                text:'Please wait while the filtered data is loading.',
                allowOutsideClick:false,
                allowEscapeKey:false,
                showConfirmButton:false,
                didOpen:()=>Swal.showLoading()
            });
        }
        $('[data-report-card], #salesReportCard, #sessionListCard, #kotListCard, #kotReportCard, #posSessionReportCard').addClass('report-loading');
    }
    function closeReportLoader(){
        $('[data-report-card], #salesReportCard, #sessionListCard, #kotListCard, #kotReportCard, #posSessionReportCard').removeClass('report-loading');
        if (window.Swal && Swal.isVisible()) Swal.close();
    }

    window.reportAjaxRequest = function(url, historyMode){
        historyMode = historyMode || 'push';
        if (reportRequest && reportRequest.readyState !== 4) reportRequest.abort();
        openReportLoader();
        reportRequest = $.ajax({
            url:url,
            method:'GET',
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            success:function(data){
                if (typeof window.updateReportDOM === 'function') window.updateReportDOM(data);
                if (data && data.filter_label) $('#activeFilterLabel').text(data.filter_label);
                if (historyMode === 'push') window.history.pushState({},'',url);
                if (historyMode === 'replace') window.history.replaceState({},'',url);
            },
            error:function(xhr,status){
                if (status === 'abort') return;
                if (window.Swal) Swal.fire('Unable to load report', (xhr.responseJSON && xhr.responseJSON.message) || 'Please try the filter again.', 'error');
                else console.error('Unable to load report', xhr);
            },
            complete:function(){ reportRequest=null; closeReportLoader(); }
        });
        return reportRequest;
    };

    window.reportFilterUrl = function(baseUrl){
        const target = new URL(baseUrl || window.location.pathname, window.location.origin);
        const params = new URLSearchParams($form.serialize());
        params.delete('page');
        target.search = params.toString();
        return target.toString();
    };

    window.triggerReportFetch = function(historyMode){
        return window.reportAjaxRequest(window.reportFilterUrl(window.location.pathname), historyMode || 'replace');
    };

    $form.on('submit', function(e){
        e.preventDefault();

        const combinedPreviewUrl = $form.attr('data-combined-preview-url');
        const isCombined = $('#reportViewFilter').length && $('#reportViewFilter').val() === 'combined';

        // POS Session Report: Combined + Filter opens a dedicated printable
        // Work Period Closing Report Blade instead of replacing the table via AJAX.
        if (combinedPreviewUrl && isCombined) {
            window.location.href = window.reportFilterUrl(combinedPreviewUrl);
            return;
        }

        window.triggerReportFetch('push');
    });
    $('#filterType').on('change', function(){ toggleReportFilterFields(); });
    $('#reportViewFilter').on('change', function(){
        // Combined starts on the current restaurant Business Day. All other
        // filter types remain available and can still be selected afterwards.
        if ($(this).val() === 'combined' && $('#filterType').val() === 'all') {
            $('#filterType').val('business_day');
            toggleReportFilterFields();
        }
        window.triggerReportFetch('replace');
    });
    $('#filterYear,#filterMonth,#paymentMethod,#deliveryPartnerFilter,#waiterFilter').on('change', function(){ window.triggerReportFetch('replace'); });
    $('.report-datepicker,#filterStartTime,#filterEndTime').on('change', function(){ /* Filter button applies exact date/time selection. */ });
    $('#reportSearchInput').on('input', function(){ clearTimeout(searchTimer); searchTimer=setTimeout(function(){ window.triggerReportFetch('replace'); },450); });

    toggleReportFilterFields();
})(window.jQuery);
</script>
