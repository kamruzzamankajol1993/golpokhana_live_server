@php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $startPage = max(1, $currentPage - 2);
    $endPage = min($lastPage, $currentPage + 2);
@endphp

<div class="report-pagination-wrap" style="display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 10px 15px;">
    <span class="progga-page-info text-muted" style="font-size: 13px; font-weight: 600;">
        Showing <span class="text-dark">{{ $paginator->firstItem() ?? 0 }}</span> to <span class="text-dark">{{ $paginator->lastItem() ?? 0 }}</span> of <span class="text-dark">{{ $paginator->total() }}</span> records
    </span>

    <div class="progga-pagination-links" style="display: flex; gap: 6px; margin-left: auto;">
        <a class="progga-page-btn {{ $currentPage == 1 ? 'disabled' : '' }}" href="{{ $paginator->url(1) }}"><i class="bi bi-chevron-double-left"></i></a>
        <a class="progga-page-btn {{ $currentPage == 1 ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() ?: '#' }}"><i class="bi bi-chevron-left"></i></a>

        @if($startPage > 1)
            <span class="progga-page-btn disabled border-0">...</span>
        @endif

        @for($page = $startPage; $page <= $endPage; $page++)
            <a class="progga-page-btn {{ $page == $currentPage ? 'active' : '' }}" href="{{ $paginator->url($page) }}">{{ $page }}</a>
        @endfor

        @if($endPage < $lastPage)
            <span class="progga-page-btn disabled border-0">...</span>
        @endif

        <a class="progga-page-btn {{ $currentPage == $lastPage ? 'disabled' : '' }}" href="{{ $paginator->nextPageUrl() ?: '#' }}"><i class="bi bi-chevron-right"></i></a>
        <a class="progga-page-btn {{ $currentPage == $lastPage ? 'disabled' : '' }}" href="{{ $paginator->url($lastPage) }}"><i class="bi bi-chevron-double-right"></i></a>
    </div>
</div>

<style>
    .progga-page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #4a5568;
        font-size: 13px;
        font-weight: 700;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .progga-page-btn:hover:not(.disabled):not(.active) {
        background: rgba(33, 53, 42, 0.05);
        color: var(--progga-primary);
        border-color: var(--progga-primary);
    }
    .progga-page-btn.active {
        background: var(--progga-primary);
        color: #fff;
        border-color: var(--progga-primary);
        box-shadow: 0 4px 10px rgba(33, 53, 42, 0.2);
    }
    .progga-page-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f8f9fa;
    }
</style>
