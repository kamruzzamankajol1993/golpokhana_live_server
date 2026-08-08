<div class="progga-table-wrapper" style="border:none;">
    <table class="progga-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Role Name</th>
                <th>Permissions</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roles as $role)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td style="font-weight: 700;">{{ $role->name }}</td>
                <td>
                    @foreach($role->permissions as $perm)
                        <span class="progga-badge progga-badge-secondary" style="font-size: 10px;">{{ $perm->name }}</span>
                    @endforeach
                </td>
                <td class="text-end">
                    @can('role-edit')
                    <a href="{{ route('role.edit', $role->id) }}" class="progga-btn progga-btn-info progga-btn-sm"><i class="bi bi-pencil"></i></a>
                    @endcan
                    @can('role-delete')
                    <form action="{{ route('role.destroy', $role->id) }}" method="POST" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="button" class="progga-btn progga-btn-danger progga-btn-sm progga-delete-btn"><i class="bi bi-trash"></i></button>
                    </form>
                    @endcan
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
