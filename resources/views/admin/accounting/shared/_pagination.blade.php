{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}

@php
  
$current = $paginator->currentPage();
  $last = $paginator->lastPage();
  $firstVisible = max(1, $current - 2);
  $lastVisible = min($last, $current + 2);
@endphp
<div class="acc-page-footer" aria-label="{{ $label ?? 'Accounting records' }} pagination">
  <span class="acc-page-info">Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }} {{ $label ?? 'records' }}</span>
  
@if($last > 1)
    <nav class="acc-pagination" aria-label="Pagination">
      <a class="{{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->url(1) }}" aria-label="First page"><i class="bi bi-chevron-double-left"></i> First</a>
      <a class="{{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() ?? '#' }}" aria-label="Previous page"><i class="bi bi-chevron-left"></i> Prev</a>
      
@if($firstVisible > 1)
        <a href="{{ $paginator->url(1) }}">1</a>
        
@if($firstVisible > 2)
<span class="acc-ellipsis">...</span>
@endif
      
@endif
      
@for($page=$firstVisible; $page<=$lastVisible; $page++)
        
@if($page === $current)
<span class="acc-page-num active" aria-current="page">{{ $page }}</span>
        
@else
<a href="{{ $paginator->url($page) }}">{{ $page }}</a>
@endif
      
@endfor
      
@if($lastVisible < $last)
        
@if($lastVisible < $last-1)
<span class="acc-ellipsis">...</span>
@endif
        <a href="{{ $paginator->url($last) }}">{{ $last }}</a>
      
@endif
      <a class="{{ !$paginator->hasMorePages() ? 'disabled' : '' }}" href="{{ $paginator->nextPageUrl() ?? '#' }}" aria-label="Next page">Next <i class="bi bi-chevron-right"></i></a>
      <a class="{{ $current === $last ? 'disabled' : '' }}" href="{{ $paginator->url($last) }}" aria-label="Last page">Last <i class="bi bi-chevron-double-right"></i></a>
    </nav>
  
@endif
</div>
