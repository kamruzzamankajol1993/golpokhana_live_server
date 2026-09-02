<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table" id="unitsTable">
        <thead>
            <tr>
                <th style="width:70px;">SL</th>
                <th>Unit</th>
                <th>Symbol</th>
                <th>Dimension</th>
                <th>Base?</th>
                <th>Factor to Base</th>
                <th>Status</th>
                <th style="width:100px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($units as $unit)
            <tr>
                <td>{{ ($units->firstItem() ?? 1) + $loop->index }}</td>
                <td><strong>{{ $unit->name }}</strong></td>
                <td><span class="progga-badge progga-badge-neutral">{{ $unit->symbol }}</span></td>
                <td><span class="progga-badge progga-badge-info">{{ $unit->dimension }}</span></td>
                <td>
                    @if($unit->is_base)
                        <span class="progga-badge progga-badge-primary"><i class="bi bi-check-circle-fill"></i> Yes</span>
                    @else
                        <span class="progga-badge progga-badge-neutral">No</span>
                    @endif
                </td>
                <td>
                    @if($unit->standard_to_base_factor !== null)
                        <strong>{{ number_format((float) $unit->standard_to_base_factor, 2, '.', '') }}</strong>
                    @else
                        <span class="progga-badge progga-badge-neutral" title="Package conversion is defined separately for each ingredient.">
                            Ingredient specific
                        </span>
                    @endif
                </td>
                <td>
                    <span class="progga-badge progga-badge-{{ $unit->is_active ? 'success' : 'neutral' }}">
                        <i class="bi {{ $unit->is_active ? 'bi-check-circle-fill' : 'bi-dash-circle' }}"></i>
                        {{ $unit->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td>
                    <div class="progga-table-actions">
                        <button type="button"
                            class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                            title="Edit"
                            aria-label="Edit unit"
                            data-id="{{ $unit->id }}"
                            data-name="{{ $unit->name }}"
                            data-symbol="{{ $unit->symbol }}"
                            data-dimension="{{ $unit->dimension }}"
                            data-is-base="{{ $unit->is_base ? '1' : '0' }}"
                            data-factor="{{ $unit->standard_to_base_factor !== null ? number_format((float) $unit->standard_to_base_factor, 2, '.', '') : '' }}"
                            data-is-active="{{ $unit->is_active ? '1' : '0' }}"
                            onclick="editUnit(this)">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <form method="POST" action="{{ route('inventory.units.destroy', $unit) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="button"
                                    class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm"
                                    title="Delete"
                                    aria-label="Delete unit"
                                    data-unit-name="{{ $unit->name }} ({{ $unit->symbol }})"
                                    onclick="confirmDeleteUnit(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">No units found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($units->total() > 0)
    @php
        $currentPage = $units->currentPage();
        $lastPage = $units->lastPage();
        $startPage = max(1, $currentPage - 2);
        $endPage = min($lastPage, $currentPage + 2);
    @endphp

    <div class="progga-card-footer progga-units-pagination-footer">
        <span class="progga-page-info">
            Showing {{ $units->firstItem() ?? 0 }}–{{ $units->lastItem() ?? 0 }} of {{ $units->total() }} units
        </span>

        @if($lastPage > 1)
            <nav class="progga-pagination-wrap" aria-label="Units pagination">
                <div class="progga-pagination">
                    <a href="{{ $units->url(1) }}"
                       data-units-page="1"
                       class="progga-page-btn {{ $units->onFirstPage() ? 'disabled' : '' }}"
                       aria-label="First page">
                        <i class="bi bi-chevron-double-left"></i> First
                    </a>

                    <a href="{{ $units->previousPageUrl() ?: '#' }}"
                       data-units-page="{{ max(1, $currentPage - 1) }}"
                       class="progga-page-btn {{ $units->onFirstPage() ? 'disabled' : '' }}"
                       aria-label="Previous page">
                        <i class="bi bi-chevron-left"></i> Prev
                    </a>

                    @if($startPage > 1)
                        <a href="{{ $units->url(1) }}" data-units-page="1" class="progga-page-num">1</a>
                        @if($startPage > 2)
                            <span class="progga-page-ellipsis">...</span>
                        @endif
                    @endif

                    @for($page = $startPage; $page <= $endPage; $page++)
                        @if($page == $currentPage)
                            <span class="progga-page-num active">{{ $page }}</span>
                        @else
                            <a href="{{ $units->url($page) }}" data-units-page="{{ $page }}" class="progga-page-num">{{ $page }}</a>
                        @endif
                    @endfor

                    @if($endPage < $lastPage)
                        @if($endPage < $lastPage - 1)
                            <span class="progga-page-ellipsis">...</span>
                        @endif
                        <a href="{{ $units->url($lastPage) }}" data-units-page="{{ $lastPage }}" class="progga-page-num">{{ $lastPage }}</a>
                    @endif

                    <a href="{{ $units->nextPageUrl() ?: '#' }}"
                       data-units-page="{{ min($lastPage, $currentPage + 1) }}"
                       class="progga-page-btn {{ !$units->hasMorePages() ? 'disabled' : '' }}"
                       aria-label="Next page">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>

                    <a href="{{ $units->url($lastPage) }}"
                       data-units-page="{{ $lastPage }}"
                       class="progga-page-btn {{ $currentPage == $lastPage ? 'disabled' : '' }}"
                       aria-label="Last page">
                        Last <i class="bi bi-chevron-double-right"></i>
                    </a>
                </div>
            </nav>
        @endif
    </div>
@endif
