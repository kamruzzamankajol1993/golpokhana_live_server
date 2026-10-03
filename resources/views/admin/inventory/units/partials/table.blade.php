<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table" id="unitsTable">
        <thead><tr><th style="width:70px;">SL</th><th>Unit Name</th><th>Symbol</th><th>Type</th><th>Conversion</th><th>Status</th><th style="width:100px;">Actions</th></tr></thead>
        <tbody>
        @forelse($units as $unit)
            <tr>
                <td>{{ ($units->firstItem() ?? 1) + $loop->index }}</td>
                <td><strong>{{ $unit->name }}</strong>@if($unit->is_base)<div class="small text-muted">Base unit</div>@endif</td>
                <td><span class="progga-badge progga-badge-neutral">{{ $unit->symbol }}</span></td>
                <td><span class="progga-badge progga-badge-info">{{ ucfirst(strtolower($unit->dimension)) }}</span></td>
                <td>
                    @if($unit->dimension === \App\Models\Unit::DIMENSION_PACKAGE)
                        <span class="text-muted">Set per ingredient</span>
                    @elseif($unit->is_base)
                        <strong>1 {{ $unit->symbol }} = 1 {{ $unit->symbol }}</strong>
                    @else
                        <strong>1 {{ $unit->symbol }} = {{ rtrim(rtrim(number_format((float)$unit->standard_to_base_factor,8,'.',''),'0'),'.') }} base</strong>
                    @endif
                </td>
                <td><span class="progga-badge progga-badge-{{ $unit->is_active ? 'success' : 'neutral' }}">{{ $unit->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td><div class="progga-table-actions">
                    <button type="button" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Edit" data-id="{{ $unit->id }}" data-name="{{ $unit->name }}" data-symbol="{{ $unit->symbol }}" data-dimension="{{ $unit->dimension }}" data-is-base="{{ $unit->is_base ? '1' : '0' }}" data-factor="{{ $unit->standard_to_base_factor !== null ? number_format((float) $unit->standard_to_base_factor, 2, '.', '') : '' }}" data-is-active="{{ $unit->is_active ? '1' : '0' }}" onclick="editUnit(this)"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="{{ route('inventory.units.destroy', $unit) }}" class="d-inline">@csrf @method('DELETE')<button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" title="Delete" data-unit-name="{{ $unit->name }} ({{ $unit->symbol }})" onclick="confirmDeleteUnit(this)"><i class="bi bi-trash"></i></button></form>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-4 text-muted">No units found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if($units->total() > 0)
    @php $currentPage=$units->currentPage(); $lastPage=$units->lastPage(); $startPage=max(1,$currentPage-2); $endPage=min($lastPage,$currentPage+2); @endphp
    <div class="progga-card-footer progga-units-pagination-footer">
        <span class="progga-page-info">Showing {{ $units->firstItem() ?? 0 }}–{{ $units->lastItem() ?? 0 }} of {{ $units->total() }} units</span>
        @if($lastPage > 1)
        <nav class="progga-pagination-wrap" aria-label="Units pagination"><div class="progga-pagination">
            <a href="{{ $units->url(1) }}" data-units-page="1" class="progga-page-btn {{ $units->onFirstPage() ? 'disabled' : '' }}"><i class="bi bi-chevron-double-left"></i> First</a>
            <a href="{{ $units->previousPageUrl() ?: '#' }}" data-units-page="{{ max(1,$currentPage-1) }}" class="progga-page-btn {{ $units->onFirstPage() ? 'disabled' : '' }}"><i class="bi bi-chevron-left"></i> Prev</a>
            @if($startPage>1)<a href="{{ $units->url(1) }}" data-units-page="1" class="progga-page-num">1</a>@if($startPage>2)<span class="progga-page-ellipsis">...</span>@endif @endif
            @for($page=$startPage;$page<=$endPage;$page++) @if($page==$currentPage)<span class="progga-page-num active">{{ $page }}</span>@else<a href="{{ $units->url($page) }}" data-units-page="{{ $page }}" class="progga-page-num">{{ $page }}</a>@endif @endfor
            @if($endPage<$lastPage) @if($endPage<$lastPage-1)<span class="progga-page-ellipsis">...</span>@endif <a href="{{ $units->url($lastPage) }}" data-units-page="{{ $lastPage }}" class="progga-page-num">{{ $lastPage }}</a>@endif
            <a href="{{ $units->nextPageUrl() ?: '#' }}" data-units-page="{{ min($lastPage,$currentPage+1) }}" class="progga-page-btn {{ !$units->hasMorePages() ? 'disabled' : '' }}">Next <i class="bi bi-chevron-right"></i></a>
            <a href="{{ $units->url($lastPage) }}" data-units-page="{{ $lastPage }}" class="progga-page-btn {{ $currentPage==$lastPage ? 'disabled' : '' }}">Last <i class="bi bi-chevron-double-right"></i></a>
        </div></nav>
        @endif
    </div>
@endif
