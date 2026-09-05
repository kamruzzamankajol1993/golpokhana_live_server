@forelse($reportRows as $row)
    @php
        $user = $row['user'];
        $userName = $user->name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        $userName = $userName ?: ('User #' . $user->id);
    @endphp
    <tr>
        <td><span class="report-sl-badge">{{ $loop->iteration }}</span></td>
        <td><strong>{{ $userName }}</strong><div class="text-muted" style="font-size:10px">{{ $user->email ?: ($user->phone ?: 'No contact') }}</div></td>
        <td>{{ $user->user_id ?: $user->id }}</td>
        <td><strong>{{ number_format($row['total_orders']) }}</strong></td>
        <td>{{ number_format($row['completed_orders']) }}</td>
        <td>{{ number_format($row['active_orders']) }}</td>
        <td>{{ number_format($row['cancelled_orders']) }}</td>
        <td class="text-danger">৳{{ number_format($row['completed_other_discount'], 0) }}</td>
        <td class="text-danger">৳{{ number_format($row['completed_product_discount'], 0) }}</td>
        <td><strong>৳{{ number_format($row['completed_sales'], 0) }}</strong></td>
    </tr>
@empty
    <tr><td colspan="10" class="text-center py-4 text-muted">No waiter data found for the selected filter.</td></tr>
@endforelse
