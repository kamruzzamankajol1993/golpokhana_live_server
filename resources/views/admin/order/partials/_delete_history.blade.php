<div class="modal-header">
  <div>
    <h5 class="modal-title mb-1"><i class="bi bi-clock-history me-2"></i>Delete History — Order #{{ $order->order_number }}</h5>
    <div class="text-muted" style="font-size:12px;">
      {{ $order->table->table_number ?? 'Takeaway' }} · {{ $order->customer->name ?? 'Walk-in Customer' }}
    </div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  @if($histories->count() > 0)
    <div class="table-responsive" style="border:1px solid var(--progga-border-light); border-radius:10px; overflow:hidden;">
      <table class="table table-sm align-middle mb-0" style="font-size:13px;">
        <thead style="background:#f8f9fa;">
          <tr>
            <th style="width:52px;" class="text-center">#</th>
            <th>Product</th>
            <th class="text-center">Deleted Qty</th>
            <th class="text-center">Previous</th>
            <th class="text-center">Remaining</th>
            <th class="text-end">Removed Total</th>
            <th>Source</th>
            <th>Reason</th>
            <th>Deleted By</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          @foreach($histories as $index => $history)
            @php
              $addons = is_array($history->addons) ? $history->addons : (json_decode($history->addons ?? '[]', true) ?: []);
            @endphp
            <tr>
              <td class="text-center text-muted">{{ $index + 1 }}</td>
              <td>
                <strong>{{ $history->product_name ?? 'N/A' }}</strong>
                @if(count($addons) > 0)
                  <div class="text-muted" style="font-size:11px;">
                    + @foreach($addons as $addon) {{ $addon['name'] ?? '' }}{{ !$loop->last ? ', ' : '' }} @endforeach
                  </div>
                @endif
                @if($history->note)
                  <div style="font-size:11px; color:#d33; font-style:italic;">Note: {{ $history->note }}</div>
                @endif
              </td>
              <td class="text-center fw-bold text-danger">{{ $history->deleted_quantity }}</td>
              <td class="text-center">{{ $history->previous_quantity }}</td>
              <td class="text-center">{{ $history->remaining_quantity }}</td>
              <td class="text-end fw-bold">৳{{ number_format($history->subtotal_removed, 0) }}</td>
              <td>
                <span class="badge bg-light text-dark border">{{ str_replace('_', ' ', ucfirst($history->source ?? 'cart')) }}</span>
              </td>
              <td style="max-width:220px; white-space:normal;">
                {{ $history->reason ?: '—' }}
              </td>
              <td>{{ $history->user->name ?? 'System/User #'.($history->deleted_by ?? 'N/A') }}</td>
              <td>
                <span class="text-muted">{{ $history->created_at ? $history->created_at->format('d M Y, h:i A') : '—' }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="text-center py-5 text-muted">
      <i class="bi bi-inbox" style="font-size:36px;"></i>
      <div class="mt-2 fw-bold">No deleted item history found for this order.</div>
    </div>
  @endif
</div>

<div class="modal-footer">
  <button class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Close</button>
</div>
