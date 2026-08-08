<div class="progga-table-wrapper" style="border:none; border-radius:0;">
    <table class="progga-table align-middle">
        <thead>
            <tr>
                <th>User Details</th>
                <th>Contact Info</th>
                <th>Role</th>
                <th>Last Login</th>

                @if(auth()->user()->can('user-edit') || auth()->user()->can('user-delete'))
                    <th class="text-end">Action</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <img src="{{ $user->image ? asset('public/' . $user->image) : 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=21352a&color=d5aa65&size=80' }}"
                             alt="Avatar" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border: 1px solid var(--progga-border-light);">
                        <div>
                            <div style="font-weight:700; color:var(--progga-text); font-size:14px;">{{ $user->name }}</div>
                            <div style="font-size:12px; color:var(--progga-text-muted);">ID: #{{ $user->user_id}}</div>
                        </div>
                    </div>
                </td>

                <td>
                    <div style="font-size:13px; margin-bottom: 2px;"><i class="bi bi-envelope text-muted me-1"></i> {{ $user->email }}</div>
                    <div style="font-size:12px; color:var(--progga-text-muted);"><i class="bi bi-telephone text-muted me-1"></i> {{ $user->phone ?? 'N/A' }}</div>
                </td>

                <td>
                    @foreach($user->roles as $role)
                        @if($role->name === 'Super Admin')
                            <span class="progga-badge progga-badge-danger" style="font-size:11px;">{{ $role->name }}</span>
                        @else
                            <span class="progga-badge progga-badge-secondary" style="font-size:11px;">{{ $role->name }}</span>
                        @endif
                    @endforeach
                </td>

                <td>
                    @if($user->last_login)
                        <div style="font-size:13px;">{{ $user->last_login->format('d M, Y') }}</div>
                        <div style="font-size:12px; color:var(--progga-text-muted);">{{ $user->last_login->format('h:i A') }}</div>
                    @else
                        <span class="text-muted" style="font-size: 12px; font-style: italic;">Never logged in</span>
                    @endif
                </td>

                @if(auth()->user()->can('user-view') || auth()->user()->can('user-edit') || auth()->user()->can('user-delete'))
<td class="text-end">

    @can('user-view')
    <a href="{{ route('user.show', $user->id) }}" class="progga-btn progga-btn-secondary progga-btn-sm" title="View Profile">
        <i class="bi bi-eye"></i>
    </a>
    @endcan

    @can('user-edit')
    <a href="{{ route('user.edit', $user->id) }}" class="progga-btn progga-btn-info progga-btn-sm" title="Edit User">
        <i class="bi bi-pencil"></i>
    </a>
    @endcan

    @can('user-delete')
    @if(auth()->id() !== $user->id)
    <form action="{{ route('user.destroy', $user->id) }}" method="POST" style="display:inline;">
        @csrf
        @method('DELETE')
        <button type="button" class="progga-btn progga-btn-danger progga-btn-sm progga-delete-btn" title="Delete User">
            <i class="bi bi-trash"></i>
        </button>
    </form>
    @else
        <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" style="cursor: not-allowed; opacity:0.5;" title="Cannot delete yourself">
            <i class="bi bi-trash"></i>
        </button>
    @endif
    @endcan

</td>
@endif
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center py-5 text-muted">
                    <i class="bi bi-people-fill d-block mb-2" style="font-size: 24px; opacity: 0.5;"></i>
                    No users found in the system.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="progga-card-footer d-flex justify-content-between align-items-center">
    <div class="progga-pagination-info" style="font-size: 13px; color: var(--progga-text-muted);">
        Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users
    </div>
    <div class="progga-pagination">
        @if ($users->hasPages())
            <ul class="pagination mb-0">
                @if ($users->onFirstPage())
                    <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $users->previousPageUrl() }}"><i class="bi bi-chevron-left"></i></a></li>
                @endif

                @foreach ($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                    <li class="page-item {{ ($page == $users->currentPage()) ? 'active' : '' }}">
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    </li>
                @endforeach

                @if ($users->hasMorePages())
                    <li class="page-item"><a class="page-link" href="{{ $users->nextPageUrl() }}"><i class="bi bi-chevron-right"></i></a></li>
                @else
                    <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
                @endif
            </ul>
        @endif
    </div>
</div>
