<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Group Name</th>
                <th>Permission Name</th>

                {{-- Edit বা Delete যেকোনো একটা পারমিশন থাকলেই Action কলাম দেখাবে --}}
                @if(auth()->user()->can('permission-edit') || auth()->user()->can('permission-delete'))
                    <th class="text-end">Action</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($permissions as $key => $item)
            <tr>
                <td>{{ ($permissions->currentPage() - 1) * $permissions->perPage() + $loop->iteration }}</td>
                <td><span class="progga-badge progga-badge-secondary">{{ $item->group_name }}</span></td>
                <td>{{ $item->name }}</td>

                @if(auth()->user()->can('permission-edit') || auth()->user()->can('permission-delete'))
                <td class="text-end">

                    @can('permission-edit')
                    <a href="{{ route('permission.edit', $item->id) }}" class="progga-btn progga-btn-info progga-btn-sm" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </a>
                    @endcan

                    @can('permission-delete')
                    <form action="{{ route('permission.destroy', $item->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="progga-btn progga-btn-danger progga-btn-sm progga-delete-btn" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endcan

                </td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center py-4 text-muted">No permissions found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="progga-card-footer d-flex justify-content-between align-items-center">
    <div class="progga-pagination-info" style="font-size: 13px; color: var(--progga-text-muted);">
        Showing {{ $permissions->firstItem() ?? 0 }} to {{ $permissions->lastItem() ?? 0 }} of {{ $permissions->total() }} entries
    </div>
    <div class="progga-pagination">
        @if ($permissions->hasPages())
            <ul class="pagination mb-0">
                {{-- Previous Page Link --}}
                @if ($permissions->onFirstPage())
                    <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $permissions->previousPageUrl() }}"><i class="bi bi-chevron-left"></i></a></li>
                @endif

                {{-- Page Links --}}
                @foreach ($permissions->getUrlRange(max(1, $permissions->currentPage() - 2), min($permissions->lastPage(), $permissions->currentPage() + 2)) as $page => $url)
                    <li class="page-item {{ ($page == $permissions->currentPage()) ? 'active' : '' }}">
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    </li>
                @endforeach

                {{-- Next Page Link --}}
                @if ($permissions->hasMorePages())
                    <li class="page-item"><a class="page-link" href="{{ $permissions->nextPageUrl() }}"><i class="bi bi-chevron-right"></i></a></li>
                @else
                    <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
                @endif
            </ul>
        @endif
    </div>
</div>
