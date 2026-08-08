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
                <th style="width:100px;">Actions</th>
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
                        @can('table-booking-edit')
                        <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick="editBookingData({{ $booking->id }}, '{{ $booking->customer_id }}', '{{ $booking->customer->name ?? '' }}', '{{ $booking->customer->phone ?? '' }}', '{{ $booking->customer->email ?? '' }}', '{{ $booking->table_id }}', '{{ $booking->booking_date }}', '{{ $booking->booking_start_time ?: $booking->booking_time }}', '{{ $booking->booking_end_time }}', '{{ $booking->number_of_guests }}', '{{ $booking->occasion_id }}', '{{ $booking->special_request }}', '{{ $booking->status }}')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        @endcan
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
        {{ $bookings->links('pagination::bootstrap-4') }}
    </div>
</div>
