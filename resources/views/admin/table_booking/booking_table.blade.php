<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table">
        <thead>
            <tr>
                <th style="width:110px;">Booking ID</th>
                <th>Customer</th>
                <th>Table</th>
                <th>Date & Start-End Time</th>
                <th style="width:80px;">Guests</th>
                <th>Occasion</th>
                <th>Special Requests</th>
                <th>Status</th>
                <th style="width:150px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $booking)
            <tr>
                <td><span class="progga-badge progga-badge-neutral">{{ $booking->booking_id }}</span></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($booking->customer->name ?? 'G') }}&background=21352a&color=d5aa65&size=68&bold=true" style="width:34px;height:34px;border-radius:50%;" alt="">
                        <div>
                            <div style="font-weight:600;color:var(--progga-primary);">{{ $booking->customer->name ?? 'Walk-in' }}</div>
                            <div style="font-size:11px;color:var(--progga-text-muted);">{{ $booking->customer->phone ?? '' }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="progga-badge progga-badge-neutral">{{ $booking->table->table_number ?? 'N/A' }}</span>
                    <div style="font-size:11px;color:var(--progga-text-muted);">{{ $booking->table->zone->name ?? '' }}</div>
                </td>
                <td>
                    <div style="font-weight:600;font-size:13px;">{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}</div>
                    @php
                        $bookingStartTime = $booking->booking_start_time ?: $booking->booking_time;
                        $bookingEndTime = $booking->booking_end_time;
                    @endphp
                    <div style="font-size:11px;color:var(--progga-text-muted);">
                        {{ $bookingStartTime ? \Carbon\Carbon::parse($bookingStartTime)->format('h:i A') : '—' }}
                        @if($bookingEndTime)
                            - {{ \Carbon\Carbon::parse($bookingEndTime)->format('h:i A') }}
                        @endif
                    </div>
                </td>
                <td><i class="bi bi-people-fill"></i> {{ $booking->number_of_guests }}</td>
                <td>
                    @if($booking->occasion)
                        <span class="progga-badge progga-badge-info">{{ $booking->occasion->name }}</span>
                    @else
                        <span class="progga-badge progga-badge-neutral">—</span>
                    @endif
                </td>
                <td style="font-size:12px;color:var(--progga-text-muted);">{{ Str::limit($booking->special_request, 30) }}</td>
                <td>
                    @if($booking->status == 'upcoming') <span class="progga-badge progga-badge-primary">Upcoming</span>
                    @elseif($booking->status == 'confirmed') <span class="progga-badge progga-badge-success">Confirmed</span>
                    @elseif($booking->status == 'completed') <span class="progga-badge progga-badge-neutral">Completed</span>
                    @elseif($booking->status == 'cancelled') <span class="progga-badge progga-badge-danger">Cancelled</span>
                    @endif
                </td>
                <td>
                    <div class="progga-table-actions">
                        <a class="progga-btn progga-btn-outline progga-btn-sm" href="{{ route("pos.index", ["table_id" => $booking->table_id, "customer_id" => $booking->customer_id, "table_booking_id" => $booking->id]) }}" title="Go to POS"><i class="bi bi-cart-plus"></i> POS</a>
                        @can('table-booking-edit')
                        <a class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" href="{{ route("table-booking.edit", $booking->id) }}"><i class="bi bi-pencil"></i></a>
                        @endcan
                        @if(in_array($booking->status, ['upcoming','confirmed']))
                        <form method="POST" action="{{ route('table-booking.update', $booking->id) }}" style="display:inline;">
                            @csrf @method('PUT')
                            <input type="hidden" name="table_id" value="{{ $booking->table_id }}">
                            <input type="hidden" name="customer_id" value="{{ $booking->customer_id }}">
                            <input type="hidden" name="number_of_guests" value="{{ $booking->number_of_guests }}">
                            <input type="hidden" name="booking_date" value="{{ $booking->booking_date->format('Y-m-d') }}">
                            <input type="hidden" name="booking_start_time" value="{{ $booking->booking_start_time ? $booking->booking_start_time->format('H:i') : $booking->booking_time }}">
                            <input type="hidden" name="booking_end_time" value="{{ $booking->booking_end_time ? $booking->booking_end_time->format('H:i') : '' }}">
                            <input type="hidden" name="status" value="completed">
                            <button class="progga-btn progga-btn-outline progga-btn-sm" title="Complete Booking">Complete</button>
                        </form>
                        @endif
                        @can('table-booking-delete')
                        <button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick="deleteBooking({{ $booking->id }}, '{{ $booking->booking_id }}')" title="Cancel/Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center py-4">No bookings found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="progga-card-footer d-flex justify-content-between align-items-center">
    <span class="progga-page-info">Showing {{ $bookings->firstItem() ?? 0 }} to {{ $bookings->lastItem() ?? 0 }} of {{ $bookings->total() }} bookings</span>
    <div class="booking-pagination">
        
    </div>
</div>
