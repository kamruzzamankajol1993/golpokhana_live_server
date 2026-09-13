<div class="progga-table-wrapper" style="border:0">
    <table class="progga-table">
        <thead>
            <tr>
                <th>SL</th>
                <th>Employee</th>
                <th>Loan Date</th>
                <th>Loan Amount (Principal)</th>
                <th>Interest</th>
                <th>Total Payable</th>
                <th>No. of Installments</th>
                <th>Monthly Installment</th>
                <th>Repaid</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loans as $i => $l)
                @php
                    $repaid = (float) ($l->repayments_sum_amount ?? 0);
                    $balance = max(0, (float) $l->total_payable - $repaid);
                    $installmentCount = (int) ($l->number_of_installments ?? 0);
                    if ($installmentCount < 1) {
                        $installmentCount = (float) $l->installment_amount > 0
                            ? max(1, (int) ceil((float) $l->total_payable / (float) $l->installment_amount))
                            : 1;
                    }
                @endphp
                <tr>
                    <td>{{ ($loans->firstItem() ?? 1) + $i }}</td>
                    <td>
                        <strong>{{ $l->employee?->name }}</strong>
                        <div class="hr-person-meta">{{ $l->employee?->employee_code }} · {{ $l->employee?->department?->name }}</div>
                    </td>
                    <td>{{ $l->loan_date?->format('d M Y') }}</td>
                    <td>৳{{ number_format((float) $l->principal_amount, 2) }}</td>
                    <td>
                        {{ $l->interest_type === 'none'
                            ? 'No Interest'
                            : ($l->interest_type === 'flat_percent'
                                ? number_format((float) $l->interest_value, 2, '.', '').'%' 
                                : '৳'.number_format((float) $l->total_interest, 2)) }}
                    </td>
                    <td><strong>৳{{ number_format((float) $l->total_payable, 2) }}</strong></td>
                    <td>{{ $installmentCount }}</td>
                    <td>৳{{ number_format((float) $l->installment_amount, 2) }}</td>
                    <td>৳{{ number_format($repaid, 2) }}</td>
                    <td><strong>৳{{ number_format($balance, 2) }}</strong></td>
                    <td>
                        <span class="hr-badge {{ $l->status === 'active' ? 'hr-badge-warning' : ($l->status === 'completed' ? 'hr-badge-success' : 'hr-badge-neutral') }}">
                            {{ ucfirst($l->status) }}
                        </span>
                    </td>
                    <td>
                        <div class="progga-table-actions">
                            @can('loan-edit')
                                @if($l->status === 'active')
                                    <button class="progga-btn progga-btn-outline progga-btn-sm loan-repay" data-id="{{ $l->id }}" data-outstanding="{{ number_format($balance, 2, '.', '') }}" title="Record Repayment">
                                        <i class="bi bi-arrow-return-left"></i>
                                    </button>
                                @endif
                                @if($repaid <= 0)
                                    <button class="progga-btn progga-btn-outline progga-btn-sm loan-edit" data-record='@json($l)' title="Edit Loan">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endif
                            @endcan
                            @can('loan-delete')
                                @if($repaid <= 0)
                                    <button class="progga-btn progga-btn-danger progga-btn-sm loan-delete" data-id="{{ $l->id }}" title="Delete Loan">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12"><div class="hr-empty py-5">No loans found.</div></td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($loans->hasPages())
    <div class="p-3">{{ $loans->links('admin.pagination.custom') }}</div>
@endif
