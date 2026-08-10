<div class="progga-table-wrapper" style="border:0;border-radius:0">
    <table class="progga-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Payroll Month</th>
                <th>Employees</th>
                <th>Gross Salary</th>
                <th>Deduction</th>
                <th>Net Salary</th>
                <th>Paid</th>
                <th>Status</th>
                <th style="width:145px">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($runs as $index => $run)
                <tr>
                    <td>{{ $runs->firstItem() + $index }}</td>
                    <td>
                        <a href="{{ route('hr.payroll.show', $run) }}" class="hr-person-name text-decoration-none">{{ $run->month_label }}</a>
                        <div class="hr-person-meta">{{ $run->payroll_code }} · {{ $run->period_start->format('d-m-Y') }} to {{ $run->period_end->format('d-m-Y') }}</div>
                    </td>
                    <td><strong>{{ $run->items_count }}</strong></td>
                    <td>৳{{ number_format((float) $run->total_gross, 2) }}</td>
                    <td class="text-danger">৳{{ number_format((float) $run->total_deduction, 2) }}</td>
                    <td><strong>৳{{ number_format((float) $run->total_net, 2) }}</strong></td>
                    <td>৳{{ number_format((float) $run->total_paid, 2) }}</td>
                    <td>
                        @php
                            $statusClass = match($run->status) { 'paid' => 'hr-badge-success', 'approved' => 'hr-badge-info', 'draft' => 'hr-badge-warning', default => 'hr-badge-neutral' };
                        @endphp
                        <span class="hr-badge {{ $statusClass }}">{{ $run->status }}</span>
                    </td>
                    <td>
                        <div class="progga-table-actions">
                            <a href="{{ route('hr.payroll.show', $run) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Open payroll"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('hr.payroll.summary-pdf', $run) }}" target="_blank" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Open PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                            @if($run->status === 'draft' && (int) $run->non_draft_items_count === 0)
                                @can('payroll-delete')
                                    <button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm payroll-delete-btn" data-url="{{ route('hr.payroll.destroy', $run) }}" title="Delete draft"><i class="bi bi-trash"></i></button>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="hr-empty"><i class="bi bi-wallet2"></i>No payroll run found.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('admin.partials.custom_pagination', ['paginator' => $runs])
