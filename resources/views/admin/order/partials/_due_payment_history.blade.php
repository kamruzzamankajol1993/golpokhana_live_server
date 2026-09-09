<div class="progga-card p-4 mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-1" style="font-weight:800;color:var(--progga-primary);"><i class="bi bi-clock-history me-2"></i>Due Payment History</h5>
            <div class="text-muted" style="font-size:12px;">Each due collection is recorded with the client payment date, payment type, provider/reference and remaining due.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge {{ ($order->due ?? 0) > 0 ? 'bg-danger' : 'bg-success' }} px-3 py-2">Current Due: ৳{{ number_format($order->due ?? 0, 0) }}</span>
            @can('order-edit')
                @if(($order->due ?? 0) > 0)
                    <button type="button" class="progga-btn progga-btn-primary progga-btn-sm" data-bs-toggle="modal" data-bs-target="#duePaymentModal">
                        <i class="bi bi-cash-coin"></i> Receive Due Payment
                    </button>
                @endif
            @endcan
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0" style="font-size:12px;">
            <thead class="table-light">
                <tr>
                    <th>Payment Date &amp; Time</th>
                    <th class="text-end">Paid Amount</th>
                    <th>Payment Type</th>
                    <th>Provider / Split Details</th>
                    <th>Reference</th>
                    <th>Received By</th>
                    <th class="text-end">Due Before</th>
                    <th class="text-end">Due After</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->duePayments as $duePayment)
                    @php
                        $duePaymentType = trim((string) ($duePayment->payment_type ?? ''));
                        $duePaymentMethodLabel = $duePaymentType === 'Card'
                            ? 'Bank / Card'
                            : ($duePaymentType === 'Mobile Banking' ? 'MFS' : ($duePaymentType ?: '—'));
                        $duePaymentDetails = [];
                        $duePaymentReferences = [];

                        if ($duePaymentType === 'Card') {
                            $duePaymentDetails[] = 'Card: ' . ($duePayment->card_type ?: '—');
                            if ($duePayment->transaction_reference) {
                                $duePaymentReferences[] = 'Card: ' . $duePayment->transaction_reference;
                            }
                        } elseif ($duePaymentType === 'Mobile Banking') {
                            $duePaymentDetails[] = 'MFS: ' . ($duePayment->mfs_provider ?: '—');
                            if ($duePayment->transaction_reference) {
                                $duePaymentReferences[] = 'MFS: ' . $duePayment->transaction_reference;
                            }
                        } elseif ($duePaymentType === 'Split') {
                            if ((float) ($duePayment->paid_in_cash ?? 0) > 0) {
                                $duePaymentDetails[] = 'Cash: ৳' . number_format((float) $duePayment->paid_in_cash, 0);
                            }
                            if ((float) ($duePayment->paid_in_card ?? 0) > 0) {
                                $duePaymentDetails[] = 'Bank / Card (' . ($duePayment->card_type ?: '—') . '): ৳' . number_format((float) $duePayment->paid_in_card, 0);
                            }
                            if ((float) ($duePayment->paid_in_mfc ?? 0) > 0) {
                                $duePaymentDetails[] = 'MFS (' . ($duePayment->mfs_provider ?: '—') . '): ৳' . number_format((float) $duePayment->paid_in_mfc, 0);
                            }
                            if ($duePayment->split_card_reference) {
                                $duePaymentReferences[] = 'Card: ' . $duePayment->split_card_reference;
                            }
                            if ($duePayment->split_mfs_reference) {
                                $duePaymentReferences[] = 'MFS: ' . $duePayment->split_mfs_reference;
                            }
                        } elseif ($duePaymentType === 'Cash') {
                            $duePaymentDetails[] = 'Cash';
                        }
                    @endphp
                    <tr>
                        <td>{{ optional($duePayment->paid_at)->format('d M Y, h:i A') }}</td>
                        <td class="text-end fw-bold text-success">৳{{ number_format($duePayment->amount, 0) }}</td>
                        <td><span class="badge bg-secondary">{{ $duePaymentMethodLabel }}</span></td>
                        <td>
                            @forelse($duePaymentDetails as $detail)
                                <div>{{ $detail }}</div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>
                            @forelse($duePaymentReferences as $reference)
                                <div>{{ $reference }}</div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>{{ optional($duePayment->user)->name ?? 'System/User #' . ($duePayment->received_by ?? '—') }}</td>
                        <td class="text-end">৳{{ number_format($duePayment->due_before, 0) }}</td>
                        <td class="text-end fw-bold {{ (float)$duePayment->due_after > 0 ? 'text-danger' : 'text-success' }}">৳{{ number_format($duePayment->due_after, 0) }}</td>
                        <td>{{ $duePayment->remark ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No due payment has been recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
