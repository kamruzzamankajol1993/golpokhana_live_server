<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Total Orders</th>
                <th>Loyalty Points</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $index => $customer)
            <tr>
                <td>{{ $customers->firstItem() + $index }}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($customer->name) }}&background=21352a&color=d5aa65&size=68" class="progga-table-avatar" alt="">
                        <div>
                            <strong>{{ $customer->name }}</strong>
                            @if($customer->points > 5)
                                <div><span class="progga-badge progga-badge-secondary" style="font-size:10px;"><i class="bi bi-star-fill"></i> Loyalty Member</span></div>
                            @endif
                        </div>
                    </div>
                </td>
                <td>{{ $customer->phone }}</td>
                <td>{{ $customer->email ?? '—' }}</td>
                <td><strong>{{ $customer->orders_count }}</strong></td>
                <td>
                    <span class="progga-badge {{ $customer->points > 5 ? 'progga-badge-secondary' : 'progga-badge-neutral' }}">
                        {{ $customer->points }} pts
                    </span>
                </td>
                <td>{{ $customer->created_at->format('M d, Y') }}</td>
                <td>
                    <div class="progga-table-actions">
                        <button type="button" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="View History" onclick="viewCustomerHistory({{ $customer->id }})">
    <i class="bi bi-eye"></i>
</button>

                        @can('customer-edit')
<button type="button" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick="editCustomerData({{ $customer->id }}, '{{ $customer->name }}', '{{ $customer->phone }}', '{{ $customer->email }}', '{{ $customer->dob }}', '{{ $customer->address }}', {{ $customer->points }})">
    <i class="bi bi-pencil"></i>
</button>
@endcan

                        @can('customer-delete')
                        <form action="{{ route('customer.destroy', $customer->id) }}" method="POST" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm progga-delete-btn">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center py-4">No customers found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between; padding: 15px;">
    <span class="progga-page-info">Showing {{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }} of {{ $customers->total() }} customers</span>
    <div class="progga-pagination custom-pagination-wrapper">
        {{ $customers->links('pagination::bootstrap-5') }}
    </div>
</div>
