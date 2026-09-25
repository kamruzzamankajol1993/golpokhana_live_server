@extends('admin.master.master')
@section('title', 'Due Order List — ' . $restaurantSettingName)


@section('css')
<style>
.progga-order-pagination-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border-top:1px solid var(--progga-border-light);flex-wrap:wrap;}
.progga-pagination-wrap{display:flex;justify-content:flex-end;}
.progga-pagination{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.progga-page-btn,.progga-page-num{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:7px 11px;border:1px solid var(--progga-border-light);border-radius:8px;background:#fff;color:var(--progga-text);font-size:12px;font-weight:800;text-decoration:none;}
.progga-page-num{min-width:34px;}
.progga-page-num.active{background:var(--progga-primary);color:#fff;border-color:var(--progga-primary);}
.progga-page-ellipsis{display:inline-flex;align-items:center;height:34px;color:var(--progga-text-muted);font-weight:800;}
</style>
@endsection

@section('body')
<main class="progga-content due-list-page">
  <div class="progga-page-header">
    <div>
      <h1 class="progga-page-title">Due Order List</h1>
      <div class="progga-breadcrumb">
        <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
        <span class="progga-breadcrumb-sep">/</span>
        <span class="progga-breadcrumb-item active">Due Order List</span>
      </div>
    </div>
  </div>

  <div class="progga-card">
    <div class="progga-card-header">
      <div>
        <div class="progga-card-title">Due Orders</div>
        <div class="text-muted small">Only orders with remaining due amount are shown here.</div>
      </div>
      <div class="d-flex flex-column align-items-end gap-2">
        <div class="d-flex gap-2">
          <a id="duePdfBtn" href="{{ route('due_orders.export_pdf', array_merge(request()->all(), ['due_only'=>1])) }}" class="progga-btn progga-btn-outline progga-btn-sm" title="Download PDF" download>
            <i class="bi bi-file-earmark-pdf"></i> PDF
          </a>
          <a id="dueExcelBtn" href="{{ route('due_orders.export_excel', array_merge(request()->all(), ['due_only'=>1])) }}" class="progga-btn progga-btn-outline progga-btn-sm" title="Download Excel">
            <i class="bi bi-file-earmark-excel"></i>
          </a>
        </div>
        <div class="d-flex gap-2">
          <input type="text" id="dueDateFrom" class="form-control form-control-sm progga-datepicker" style="width:110px;" placeholder="From date">
          <input type="text" id="dueDateTo" class="form-control form-control-sm progga-datepicker" style="width:110px;" placeholder="To date">
        </div>
        <input type="search" id="searchDueOrder" class="form-control form-control-sm" style="width:220px;" placeholder="Search due orders...">
      </div>
    </div>

    <div id="order_data_container">
      @include('admin.order.partials._order_table', ['orders' => $orders, 'showPagination' => true, 'dueListPage' => true])
    </div>
  </div>
</main>
@endsection


@section('script')
<script>
$(document).ready(function(){
    let timer;

    function fetchDueOrders(url){
        let search = $('#searchDueOrder').val();
        let date_from = $('#dueDateFrom').val();
        let date_to = $('#dueDateTo').val();
        $('#order_data_container').css('opacity','0.5');

        updateExportLinks(search, date_from, date_to);

        $.ajax({
            url: url || "{{ route('order.due_list') }}",
            data: {search: search, date_from: date_from, date_to: date_to},
            success:function(data){
                $('#order_data_container').html(data).css('opacity','1');
            },
            error:function(){
                $('#order_data_container').css('opacity','1');
            }
        });
    }

    function updateExportLinks(search, from, to){
        let basePdf = "{{ route('due_orders.export_pdf') }}";
        let baseExcel = "{{ route('due_orders.export_excel') }}";
        let params = new URLSearchParams({due_only:1});
        if(search) params.set('search', search);
        if(from) params.set('date_from', from);
        if(to) params.set('date_to', to);
        $('#duePdfBtn').attr('href', basePdf + '?' + params.toString());
        $('#dueExcelBtn').attr('href', baseExcel + '?' + params.toString());
    }

    flatpickr('.progga-datepicker', {
        dateFormat: 'd-m-Y',
        allowInput: true,
        onChange: function(){
            fetchDueOrders();
        }
    });

    $('#searchDueOrder').on('keyup', function(){
        clearTimeout(timer);
        timer=setTimeout(function(){ fetchDueOrders(); },500);
    });

    $(document).on('click','.progga-pagination a',function(e){
        e.preventDefault();
        fetchDueOrders($(this).attr('href'));
    });
});
</script>
@endsection
