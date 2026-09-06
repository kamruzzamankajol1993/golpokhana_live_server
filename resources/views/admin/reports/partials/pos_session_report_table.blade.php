@if(($reportView ?? 'session') === 'combined')
    <div class="report-table-shell">
        <table class="progga-table enhanced-report-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Orders</th>
                    <th class="text-end">Sales</th>
                    <th class="text-end">Service Charge</th>
                    <th class="text-end">VAT</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-end">Cash</th>
                    <th class="text-end">Bank / Card</th>
                    <th class="text-end">MFS</th>
                </tr>
            </thead>
            <tbody>
                @if(($combinedSummary['order_count'] ?? 0) > 0)
                    <tr>
                        <td><strong>{{ $filterLabel }}</strong></td>
                        <td><span class="report-sl-badge">{{ $combinedSummary['order_count'] }}</span></td>
                        <td class="text-end"><strong>৳{{ number_format((float) $combinedSummary['sales_total'], 2) }}</strong></td>
                        <td class="text-end"><strong>৳{{ number_format((float) $combinedSummary['service_charge'], 2) }}</strong></td>
                        <td class="text-end"><strong>৳{{ number_format((float) $combinedSummary['vat_total'], 2) }}</strong></td>
                        <td class="text-end"><strong>৳{{ number_format((float) $combinedSummary['grand_total'], 2) }}</strong></td>
                        <td class="text-end"><strong>৳{{ number_format((float) $combinedSummary['cash'], 2) }}</strong></td>
                        <td class="text-end"><strong>৳{{ number_format((float) $combinedSummary['card'], 2) }}</strong></td>
                        <td class="text-end"><strong>৳{{ number_format((float) $combinedSummary['mfs'], 2) }}</strong></td>
                    </tr>
                @else
                    <tr>
                        <td colspan="9" class="text-center text-muted" style="padding:32px 12px;">No matching orders found.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
@else
    <div class="report-table-shell">
        <table class="progga-table enhanced-report-table">
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Employee</th>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration</th>
                    <th>Grand Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="posSessionReportRows">
                @include('admin.reports.partials.pos_session_report_rows')
            </tbody>
        </table>
    </div>
    <div id="posSessionReportPagination">
        @include('admin.reports.partials.custom_pagination',['paginator'=>$sessions])
    </div>
@endif
