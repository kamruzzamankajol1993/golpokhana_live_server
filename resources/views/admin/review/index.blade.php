@extends('admin.master.master')
@section('title', 'Feedback List — ' . $restaurantSettingName)

@section('body')
@php
    $ratingLabels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Great'];
    $heardLabels = [
        'family_friends' => 'Family and Friends',
        'social_media' => 'Social Media',
        'passing_by' => 'Passing-by',
    ];
@endphp
<main class="progga-content">
  <div class="progga-page-header d-flex justify-content-between align-items-center">
    <div>
      <h1 class="progga-page-title">Feedback List</h1>
      <div class="progga-breadcrumb">
        <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
        <span class="progga-breadcrumb-sep">/</span>
        <a href="{{ route('customer.index') }}" class="progga-breadcrumb-item">Customers</a>
        <span class="progga-breadcrumb-sep">/</span>
        <span class="progga-breadcrumb-item active">Feedback List</span>
      </div>
    </div>
  </div>

  <div class="progga-card">
    <div class="progga-table-wrapper" style="border:none; border-radius:0; overflow-x:auto;">
      <table class="progga-table" style="min-width:1320px;">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Name</th>
            <th>Ratings</th>
            <th>Comment</th>
            <th>Heard About Us</th>
            <th>Contact</th>
            <th>Submitted</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reviews as $review)
              @php
                  $order = $review->order;
                  $heardFrom = json_decode($review->heard_from ?? '[]', true);
                  $heardFrom = is_array($heardFrom) ? $heardFrom : [];
                  $isStructured = !is_null($review->overall_experience_rating);
              @endphp
              <tr>
                <td><strong>#{{ $order->order_number ?? 'N/A' }}</strong></td>
                <td>
                    <div style="font-weight:700;color:var(--progga-text);">{{ $review->guest_name ?: '—' }}</div>
                    <div class="text-muted" style="font-size:11px;">Order ID: {{ $review->order_id }}</div>
                </td>
                <td style="min-width:235px;">
                    @if($isStructured)
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px 12px;font-size:12px;">
                            <span>Food: <strong>{{ $ratingLabels[$review->food_rating] ?? '—' }}</strong></span>
                            <span>Service: <strong>{{ $ratingLabels[$review->service_rating] ?? '—' }}</strong></span>
                            <span>Cleanliness: <strong>{{ $ratingLabels[$review->cleanliness_rating] ?? '—' }}</strong></span>
                            <span>Atmosphere: <strong>{{ $ratingLabels[$review->atmosphere_rating] ?? '—' }}</strong></span>
                            <span>Value: <strong>{{ $ratingLabels[$review->value_rating] ?? '—' }}</strong></span>
                            <span>Overall: <strong>{{ $ratingLabels[$review->overall_experience_rating] ?? '—' }}</strong></span>
                        </div>
                    @else
                        <div style="font-size:12px;">
                            <strong>Legacy Review:</strong> {{ $review->rating ?? '—' }}/5
                        </div>
                        <div class="text-muted" style="font-size:11px;max-width:220px;white-space:normal;">{{ $review->review }}</div>
                    @endif
                </td>
                <td style="min-width:220px;max-width:320px;">
                    <div style="font-size:12px;line-height:1.45;white-space:pre-wrap;word-break:break-word;">{{ $review->comment ?: '—' }}</div>
                </td>
                <td>
                    @forelse($heardFrom as $heard)
                        <span class="progga-badge progga-badge-neutral" style="font-size:10px;margin:1px;">{{ $heardLabels[$heard] ?? $heard }}</span>
                    @empty
                        <span class="text-muted">—</span>
                    @endforelse
                </td>
                <td style="min-width:180px;">
                    @if($review->guest_email)
                        <div style="font-size:12px;"><i class="bi bi-envelope me-1"></i>{{ $review->guest_email }}</div>
                    @endif
                    @if($review->guest_phone)
                        <div style="font-size:12px;"><i class="bi bi-telephone me-1"></i>{{ $review->guest_phone }}</div>
                    @endif
                    @if(!$review->guest_email && !$review->guest_phone)
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td><span style="font-size:12px;color:#666;">{{ ($isStructured ? $review->updated_at : $review->created_at)->format('d M, Y h:i A') }}</span></td>
                <td>
                    @if($order)
                    <a href="{{ route('order.details', $review->order_id) }}" class="progga-btn progga-btn-outline progga-btn-sm" title="View Order">
                        <i class="bi bi-eye"></i> View Order
                    </a>
                    @endif
                </td>
              </tr>
          @empty
              <tr><td colspan="8" class="text-center py-4 text-muted">No feedback found yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <span class="progga-page-info">Showing {{ $reviews->firstItem() ?? 0 }}–{{ $reviews->lastItem() ?? 0 }} of {{ $reviews->total() }} feedback entries</span>
      <div class="progga-pagination">
          {{ $reviews->links('pagination::bootstrap-4') }}
      </div>
    </div>
  </div>
</main>
@endsection
