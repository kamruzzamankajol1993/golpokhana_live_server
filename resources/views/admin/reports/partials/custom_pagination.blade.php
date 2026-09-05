@php
    $currentPage = $paginator->currentPage();
    $lastPage = max(1, $paginator->lastPage());
    $startPage = max(1, $currentPage - 2);
    $endPage = min($lastPage, $currentPage + 2);
@endphp

<div class="progga-card-footer progga-order-pagination-footer report-pagination-footer">
    <span class="progga-page-info">
        Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} records
    </span>

    @if($lastPage > 1)
        <nav class="progga-pagination-wrap" aria-label="Report pagination">
            <div class="progga-pagination">
                <a href="{{ $paginator->url(1) }}" class="progga-page-btn {{ $paginator->onFirstPage() ? 'disabled' : '' }}" aria-label="First page"><i class="bi bi-chevron-double-left"></i> First</a>
                <a href="{{ $paginator->previousPageUrl() ?: '#' }}" class="progga-page-btn {{ $paginator->onFirstPage() ? 'disabled' : '' }}" aria-label="Previous page"><i class="bi bi-chevron-left"></i> Prev</a>

                @if($startPage > 1)
                    <a href="{{ $paginator->url(1) }}" class="progga-page-num">1</a>
                    @if($startPage > 2)<span class="progga-page-ellipsis">...</span>@endif
                @endif

                @for($page=$startPage;$page<=$endPage;$page++)
                    @if($page === $currentPage)
                        <span class="progga-page-num active">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="progga-page-num">{{ $page }}</a>
                    @endif
                @endfor

                @if($endPage < $lastPage)
                    @if($endPage < $lastPage - 1)<span class="progga-page-ellipsis">...</span>@endif
                    <a href="{{ $paginator->url($lastPage) }}" class="progga-page-num">{{ $lastPage }}</a>
                @endif

                <a href="{{ $paginator->nextPageUrl() ?: '#' }}" class="progga-page-btn {{ !$paginator->hasMorePages() ? 'disabled' : '' }}" aria-label="Next page">Next <i class="bi bi-chevron-right"></i></a>
                <a href="{{ $paginator->url($lastPage) }}" class="progga-page-btn {{ $currentPage === $lastPage ? 'disabled' : '' }}" aria-label="Last page">Last <i class="bi bi-chevron-double-right"></i></a>
            </div>
        </nav>
    @endif
</div>

<style>
.report-pagination-footer{display:flex!important;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:12px 16px!important;border-top:1px solid #e8ecea;background:#fff}
.report-pagination-footer .progga-page-info{font-size:12px;font-weight:700;color:#6b756f}
.report-pagination-footer .progga-pagination{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.report-pagination-footer .progga-page-btn,.report-pagination-footer .progga-page-num{display:inline-flex;align-items:center;justify-content:center;height:34px;min-width:34px;padding:0 10px;border:1px solid #dfe5e1;border-radius:8px;background:#fff;color:#48534d;text-decoration:none;font-size:12px;font-weight:800;transition:.15s ease}
.report-pagination-footer .progga-page-num{padding:0 9px}
.report-pagination-footer .progga-page-btn:hover:not(.disabled),.report-pagination-footer .progga-page-num:hover{border-color:var(--progga-primary);color:var(--progga-primary);background:#f6faf7}
.report-pagination-footer .progga-page-num.active{background:var(--progga-primary);border-color:var(--progga-primary);color:#fff}
.report-pagination-footer .progga-page-btn.disabled{opacity:.45;pointer-events:none}
.report-pagination-footer .progga-page-ellipsis{padding:0 2px;color:#8b948f;font-weight:800}
@media(max-width:767px){.report-pagination-footer{align-items:flex-start}.report-pagination-footer .progga-pagination-wrap{width:100%;overflow-x:auto;padding-bottom:2px}.report-pagination-footer .progga-pagination{width:max-content;flex-wrap:nowrap}}
</style>
