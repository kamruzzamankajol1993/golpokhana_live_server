@if($paginator->total() > 0)
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $startPage = max(1, $currentPage - 2);
        $endPage = min($lastPage, $currentPage + 2);
    @endphp

    <div class="progga-card-footer inventory-pagination-footer">
        <span class="progga-page-info">
            Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} {{ $label ?? 'records' }}
        </span>

        @if($lastPage > 1)
            <nav class="progga-pagination-wrap" aria-label="{{ ucfirst($label ?? 'Records') }} pagination">
                <div class="progga-pagination">
                    <a href="{{ $paginator->url(1) }}"
                       data-inventory-page="1"
                       class="progga-page-btn {{ $paginator->onFirstPage() ? 'disabled' : '' }}"
                       aria-label="First page">
                        <i class="bi bi-chevron-double-left"></i> First
                    </a>

                    <a href="{{ $paginator->previousPageUrl() ?: '#' }}"
                       data-inventory-page="{{ max(1, $currentPage - 1) }}"
                       class="progga-page-btn {{ $paginator->onFirstPage() ? 'disabled' : '' }}"
                       aria-label="Previous page">
                        <i class="bi bi-chevron-left"></i> Prev
                    </a>

                    @if($startPage > 1)
                        <a href="{{ $paginator->url(1) }}" data-inventory-page="1" class="progga-page-num">1</a>
                        @if($startPage > 2)<span class="progga-page-ellipsis">...</span>@endif
                    @endif

                    @for($page = $startPage; $page <= $endPage; $page++)
                        @if($page === $currentPage)
                            <span class="progga-page-num active">{{ $page }}</span>
                        @else
                            <a href="{{ $paginator->url($page) }}" data-inventory-page="{{ $page }}" class="progga-page-num">{{ $page }}</a>
                        @endif
                    @endfor

                    @if($endPage < $lastPage)
                        @if($endPage < $lastPage - 1)<span class="progga-page-ellipsis">...</span>@endif
                        <a href="{{ $paginator->url($lastPage) }}" data-inventory-page="{{ $lastPage }}" class="progga-page-num">{{ $lastPage }}</a>
                    @endif

                    <a href="{{ $paginator->nextPageUrl() ?: '#' }}"
                       data-inventory-page="{{ min($lastPage, $currentPage + 1) }}"
                       class="progga-page-btn {{ !$paginator->hasMorePages() ? 'disabled' : '' }}"
                       aria-label="Next page">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="{{ $paginator->url($lastPage) }}"
                       data-inventory-page="{{ $lastPage }}"
                       class="progga-page-btn {{ $currentPage === $lastPage ? 'disabled' : '' }}"
                       aria-label="Last page">
                        Last <i class="bi bi-chevron-double-right"></i>
                    </a>
                </div>
            </nav>
        @endif
    </div>
@endif
