        <table class="table sales-table{{ !empty($withProducts) ? ' with-products' : '' }}">
            @if(!empty($withProducts))
            <colgroup>
                <col style="width:2%;">
                <col style="width:4%;">
                <col style="width:8%;">
                <col style="width:20%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:10%;">
                <col style="width:5%;">
                <col style="width:6%;">
                <col style="width:5%;">
                <col style="width:8%;">
            </colgroup>
            @endif
            <thead>
                <tr>
                    <th class="text-center">SL</th>
                    <th>Order #</th>
                    <th>Customer</th>
                    @if(!empty($withProducts))<th class="ordered-items-col">Ordered Item List</th>@endif
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">Honored</th>
                    <th class="text-right">Product<br>Discount</th>
                    <th class="text-right">Service</th>
                    <th class="text-right">Tips</th>
                    <th class="text-right">Given</th>
                    <th class="text-right">Change</th>
                    <th class="text-right">Grand<br>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>KOT to Pay</th>
                </tr>
            </thead>
            <tbody>
            @forelse($dataRows as $order)
                @php
                    $discountAmount = max(0, (float)($order->discount_amount ?? 0));
                    $productDiscountAmount = max(0, (float)($order->product_discount_amount ?? 0));
                    $serviceCharge = max(0, (float)($order->service_charge ?? 0));
                    $tipsAmount = max(0, (float)($order->tips_amount ?? 0));
                    $givenMoney = max(0, (float)($order->given_money ?? 0));
                    $changeAmount = max(0, (float)($order->change_amount ?? 0));
                    $orderType = strtolower((string) $order->order_type);
                    $tableText = in_array($orderType, ['takeaway', 'delivery'], true) ? ucfirst($orderType) : 'Table T-' . (optional($order->table)->table_number ?? 'N/A');

                    $paymentText = ($order->payment_type ?? '') === 'Split' ? 'Split' : $order->reportPaymentText(0, false);
                    if (($order->payment_type ?? '') === 'Split') {
                        $splits = [];
                        if((float)$order->paid_in_cash > 0) $splits[] = 'Cash: ' . number_format($order->paid_in_cash, 0);
                        if((float)$order->paid_in_card > 0) $splits[] = 'Card: ' . number_format($order->paid_in_card, 0) . (!empty($order->card_type) ? ' (' . $order->card_type . ')' : '');
                        if((float)$order->paid_in_mfc > 0) $splits[] = 'MFS: ' . number_format($order->paid_in_mfc, 0) . (!empty($order->mfs_provider) ? ' (' . $order->mfs_provider . ')' : '');
                        $paymentText = 'Split<br><span class="small-muted">' . e(implode(' | ', $splits)) . '</span>';
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $rowOffset + $loop->iteration }}</td>
                    <td><strong>#{{ $order->order_number }}</strong></td>
                    <td class="customer-col">
                        <strong>{{ optional($order->customer)->name ?? 'Walk-in' }}</strong><br>
                        <span class="small-muted">{{ $tableText }}</span>
                    </td>
                    @if(!empty($withProducts))
                        <td class="ordered-items-col">
                            @php
                                $orderedItems = $order->orderDetails->filter(fn ($item) => empty($item->is_unavailable) && (float) $item->quantity > 0);
                            @endphp
                            @forelse($orderedItems as $item)
                                @if($loop->first)<ul class="ordered-items">@endif
                                <li>
                                    <span class="ordered-item-name">{{ $item->product_name }}</span> × {{ (int) $item->quantity }}
                                    @if((float) ($item->product_discount_amount ?? 0) > 0)
                                        <span class="ordered-item-discount">
                                            — Disc:
                                            @if($item->product_discount_type === 'percentage' && (float) $item->product_discount_value > 0)
                                                {{ number_format((float) $item->product_discount_value, 2, '.', '') + 0 }}% /
                                            @endif
                                            <span class="taka-symbol">&#2547;</span>{{ number_format((float) $item->product_discount_amount, 0) }}
                                        </span>
                                    @endif
                                </li>
                                @if($loop->last)</ul>@endif
                            @empty
                                —
                            @endforelse
                        </td>
                    @endif
                    <td class="text-right money-col"><span class="taka-symbol">&#2547;</span>{{ number_format($order->subtotal, 0) }}</td>
                    <td class="text-right money-col" style="color: #b12626;"><span class="taka-symbol">&#2547;</span>{{ number_format($discountAmount, 0) }}</td>
                    <td class="text-right money-col" style="color: #b12626;"><span class="taka-symbol">&#2547;</span>{{ number_format($productDiscountAmount, 0) }}</td>
                    <td class="text-right money-col"><span class="taka-symbol">&#2547;</span>{{ number_format($serviceCharge, 0) }}</td>
                    <td class="text-right money-col" style="color: #0a7d21;"><span class="taka-symbol">&#2547;</span>{{ number_format($tipsAmount, 0) }}</td>
                    <td class="text-right money-col"><span class="taka-symbol">&#2547;</span>{{ number_format($givenMoney, 0) }}</td>
                    <td class="text-right money-col" style="color: #0a7d21;"><span class="taka-symbol">&#2547;</span>{{ number_format($changeAmount, 0) }}</td>
                    <td class="text-right money-col"><strong><span class="taka-symbol">&#2547;</span>{{ number_format($order->grand_total, 0) }}</strong></td>
                    <td class="payment-col">{!! $paymentText !!}</td>
                    <td class="status-col text-center">{{ $order->status }}</td>
                    <td class="date-col">{{ optional($order->created_at)->format('d M Y') }}</td>
                    <td class="time-col">{{ optional($order->created_at)->format('h:i A') }}</td>
                    <td class="kot-col">{{ is_null($order->kitchen_to_payment_minutes) ? '—' : $order->kitchen_to_payment_minutes . ' min' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ !empty($withProducts) ? 17 : 16 }}" class="text-center" style="padding: 18px 10px;">No completed orders found for the selected filter.</td>
                </tr>
            @endforelse
            </tbody>
            @if($showTotals ?? true)
            <tfoot>
                <tr>
                    <th colspan="{{ !empty($withProducts) ? 4 : 3 }}" class="text-right">Total ({{ $reportTotals['order_count'] ?? $dataRows->count() }} Orders)</th>
                    <th class="text-right"><span class="taka-symbol">&#2547;</span>{{ number_format(($reportTotals['subtotal'] ?? $dataRows->sum('subtotal')), 0) }}</th>
                    <th class="text-right"><span class="taka-symbol">&#2547;</span>{{ number_format(($reportTotals['discount_amount'] ?? $dataRows->sum('discount_amount')), 0) }}</th>
                    <th class="text-right"><span class="taka-symbol">&#2547;</span>{{ number_format(($reportTotals['product_discount_amount'] ?? $dataRows->sum('product_discount_amount')), 0) }}</th>
                    <th class="text-right"><span class="taka-symbol">&#2547;</span>{{ number_format(($reportTotals['service_charge'] ?? $dataRows->sum('service_charge')), 0) }}</th>
                    <th class="text-right"><span class="taka-symbol">&#2547;</span>{{ number_format(($reportTotals['tips_amount'] ?? $dataRows->sum('tips_amount')), 0) }}</th>
                    <th colspan="2"></th>
                    <th class="text-right"><span class="taka-symbol">&#2547;</span>{{ number_format($periodTotalSale, 0) }}</th>
                    <th colspan="5"></th>
                </tr>
            </tfoot>
            @endif
        </table>
