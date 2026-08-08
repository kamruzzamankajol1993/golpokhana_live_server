
@php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $startPage = max(1, $currentPage - 2);
    $endPage = min($lastPage, $currentPage + 2);
@endphp
<div class="report-pagination-wrap">
    <span class="progga-page-info">Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} records</span>
    <div class="report-pagination">
        <a class="report-page-link {{ $currentPage == 1 ? 'disabled' : '' }}" href="{{ $paginator->url(1) }}">First</a>
        <a class="report-page-link {{ $currentPage == 1 ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() ?: '#' }}">Prev</a>
        @if($startPage > 1)
            <span class="report-page-link disabled">...</span>
        @endif
        @for($page = $startPage; $page <= $endPage; $page++)
            <a class="report-page-link {{ $page == $currentPage ? 'active' : '' }}" href="{{ $paginator->url($page) }}">{{ $page }}</a>
        @endfor
        @if($endPage < $lastPage)
            <span class="report-page-link disabled">...</span>
        @endif
        <a class="report-page-link {{ $currentPage == $lastPage ? 'disabled' : '' }}" href="{{ $paginator->nextPageUrl() ?: '#' }}">Next</a>
        <a class="report-page-link {{ $currentPage == $lastPage ? 'disabled' : '' }}" href="{{ $paginator->url($lastPage) }}">Last</a>
    </div>
</div>
