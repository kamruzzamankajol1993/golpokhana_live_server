@extends('admin.master.master')

@section('title')
Top Selling Items — {{ $restaurantSettingName }}
@endsection

@section('css')
<style>
.top-selling-filter-card{border:1px solid var(--progga-border-light);border-radius:16px;background:linear-gradient(135deg,#fff 0%,#f8faf9 100%);overflow:hidden}.top-selling-filter-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid var(--progga-border-light);flex-wrap:wrap}.top-selling-filter-title{display:flex;align-items:center;gap:12px}.top-selling-filter-icon{width:42px;height:42px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:rgba(33,53,42,.08);color:var(--progga-primary);font-size:20px}.top-selling-filter-title strong{display:block;color:var(--progga-primary);font-size:15px}.top-selling-filter-title span{display:block;color:var(--progga-text-muted);font-size:12px;font-weight:600;margin-top:2px}.top-selling-filter-body{padding:18px 20px}.top-selling-periods{display:flex;gap:8px;flex-wrap:wrap}.top-selling-period-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 14px;border:1px solid var(--progga-border-light);border-radius:10px;background:#fff;color:var(--progga-text);font-size:12px;font-weight:800;text-decoration:none;transition:.2s ease}.top-selling-period-btn:hover{border-color:var(--progga-primary);color:var(--progga-primary);transform:translateY(-1px)}.top-selling-period-btn.active{background:var(--progga-primary);border-color:var(--progga-primary);color:#fff;box-shadow:0 6px 16px rgba(33,53,42,.16)}.top-selling-toolbar{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap}.top-selling-toolbar-right{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.top-selling-per-page{min-width:120px}.top-selling-summary{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.top-selling-card-head-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.top-selling-rank{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:30px;border-radius:8px;background:rgba(33,53,42,.07);color:var(--progga-primary);font-size:12px;font-weight:900}.top-selling-zero{color:var(--progga-text-muted)}
@media(max-width:767px){.top-selling-period-btn{flex:1 1 calc(33.333% - 8px)}.top-selling-toolbar,.top-selling-toolbar-right{align-items:stretch}.top-selling-toolbar-right{width:100%}.top-selling-toolbar-right form,.top-selling-toolbar-right .progga-btn{flex:1}.top-selling-per-page{width:100%}.top-selling-filter-head,.top-selling-filter-body{padding:14px}}
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
      <div>
        <h1 class="progga-page-title">Top Selling Items</h1>
        <div class="progga-breadcrumb">
          <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
          <span class="progga-breadcrumb-item active">Top Selling Items</span>
        </div>
      </div>
      <div class="top-selling-card-head-actions">
        <a href="{{ route('dashboard.top_selling_items.pdf', ['period' => $period]) }}" target="_blank" rel="noopener" class="progga-btn progga-btn-primary progga-btn-sm">
          <i class="bi bi-file-earmark-pdf"></i> Download PDF
        </a>
        <a href="{{ route('home') }}" class="progga-btn progga-btn-outline progga-btn-sm">
          <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
      </div>
    </div>

    <div class="top-selling-filter-card mb-4">
      <div class="top-selling-filter-head">
        <div class="top-selling-filter-title">
          <span class="top-selling-filter-icon"><i class="bi bi-funnel-fill"></i></span>
          <div>
            <strong>Filter Top Selling Items</strong>
            <span>Select a reporting period to refresh the ranking.</span>
          </div>
        </div>
        <span class="progga-badge progga-badge-secondary"><i class="bi bi-calendar3"></i> {{ $dateRangeLabel }}</span>
      </div>

      <div class="top-selling-filter-body">
        <div class="top-selling-periods">
          @foreach($periodLabels as $periodKey => $label)
            <a href="{{ route('dashboard.top_selling_items', array_merge(request()->except(['page', 'period']), ['period' => $periodKey])) }}"
               class="top-selling-period-btn {{ $period === $periodKey ? 'active' : '' }}">
              {{ $label }}
            </a>
          @endforeach
        </div>

        <div class="top-selling-toolbar">
          <div class="top-selling-summary">
            <span class="progga-badge progga-badge-primary">{{ $periodLabel }}</span>
          </div>

          <div class="top-selling-toolbar-right">
            <form action="{{ route('dashboard.top_selling_items') }}" method="GET">
              <input type="hidden" name="period" value="{{ $period }}">
              <label class="progga-form-label" for="topSellingPerPage" style="font-size:11px;margin-bottom:4px;">Items per page</label>
              <select id="topSellingPerPage" name="per_page" class="progga-select top-selling-per-page" onchange="this.form.submit()">
                @foreach([20, 50, 100] as $size)
                  <option value="{{ $size }}" {{ (int) $perPage === $size ? 'selected' : '' }}>{{ $size }} items</option>
                @endforeach
              </select>
            </form>

            <a href="{{ route('dashboard.top_selling_items.pdf', ['period' => $period]) }}" target="_blank" rel="noopener" class="progga-btn progga-btn-outline progga-btn-sm" style="min-height:38px;">
              <i class="bi bi-download"></i> Export All to PDF
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="progga-card mb-4">
      <div class="progga-card-header" style="align-items:flex-start;gap:14px;flex-wrap:wrap;">
        <div>
          <div class="progga-card-title">Top Selling Items — {{ $periodLabel }}</div>
          <div class="progga-card-subtitle">{{ $dateRangeLabel }}</div>
        </div>
        <span class="progga-badge progga-badge-secondary">{{ number_format($topSellingItems->total()) }} products</span>
      </div>

      <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
        <table class="progga-table">
          <thead>
            <tr>
              <th style="width:72px;">Rank</th>
              <th>Item</th>
              <th style="text-align:right;">Quantity Sold</th>
              <th style="text-align:right;">Sales Value</th>
              <th style="width:120px;text-align:center;">Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($topSellingItems as $index => $item)
              @php $hasSales = (int) $item->total_qty > 0; @endphp
              <tr>
                <td><span class="top-selling-rank">#{{ ($topSellingItems->firstItem() ?? 1) + $index }}</span></td>
                <td><strong>{{ $item->product_name }}</strong></td>
                <td style="text-align:right;font-weight:800;" class="{{ $hasSales ? '' : 'top-selling-zero' }}">{{ number_format((int) $item->total_qty) }}</td>
                <td style="text-align:right;font-weight:800;" class="{{ $hasSales ? '' : 'top-selling-zero' }}">৳{{ number_format((float) $item->total_amount, 0) }}</td>
                <td style="text-align:center;">
                  <span class="progga-badge progga-badge-{{ $hasSales ? 'primary' : 'neutral' }}">
                    {{ $hasSales ? 'Sold' : 'No Sales' }}
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted" style="padding:36px 12px;">No products found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($topSellingItems->hasPages())
        <div class="progga-card-footer" style="padding:0;">
          @include('admin.reports.partials.custom_pagination', ['paginator' => $topSellingItems])
        </div>
      @endif
    </div>
</main>
@endsection
