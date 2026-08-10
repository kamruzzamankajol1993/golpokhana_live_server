@forelse($foodRows as $index => $food)
<tr>
    <td><strong>#{{ $foodRows->firstItem() + $index }}</strong></td>
    <td>{{ $food->product_name }}</td>
    <td><strong>{{ number_format($food->total_qty) }}</strong></td>
    <td>{{ number_format($food->orders_count) }}</td>
    <td><strong>৳{{ number_format($food->total_sales, 2) }}</strong></td>
    <td class="text-danger">-৳{{ number_format($food->product_discount ?? 0, 2) }}</td>
    <td><strong>৳{{ number_format($food->net_sales ?? (($food->total_sales ?? 0) - ($food->product_discount ?? 0)), 2) }}</strong></td>
</tr>
@empty
<tr><td colspan="7" class="text-center py-4 text-muted">No records found.</td></tr>
@endforelse
