@extends('admin.master.master')
@section('title', 'Customer Reviews — TableTrack RMS')

@section('body')
<main class="progga-content">
  <div class="progga-page-header d-flex justify-content-between align-items-center">
    <div>
      <h1 class="progga-page-title">Customer Reviews</h1>
      <div class="progga-breadcrumb">
        <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
        <span class="progga-breadcrumb-sep">/</span>
        <span class="progga-breadcrumb-item active">Reviews</span>
      </div>
    </div>

    <div>
        <a href="{{ route('order.index') }}" class="progga-btn progga-btn-outline">
            <i class="bi bi-arrow-left"></i> Back to Order List
        </a>
    </div>
  </div>

  <div class="progga-card">
    <div class="progga-table-wrapper" style="border:none; border-radius:0;">
      <table class="progga-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Rating</th>
            <th>Review/Feedback</th>
            <th>Submitted Time</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reviews as $review)
              <tr>
                <td><strong>#{{ $review->order->order_number ?? 'N/A' }}</strong></td>
                <td>
                    <div style="font-weight: 700; color: var(--progga-text);">
                        {{ $review->order->customer->name ?? 'Walk-in Customer' }}
                    </div>
                </td>
                <td>
                    <div>
                        @for($i=1; $i<=5; $i++)
                            <i class="bi {{ $i <= $review->rating ? 'bi-star-fill text-warning' : 'bi-star text-muted' }}" style="font-size: 12px;"></i>
                        @endfor
                    </div>
                </td>
                <td>
                    <div style="font-size: 14px; font-style: italic; color: #555; max-width: 400px; white-space: normal;">
                        "{{ $review->review }}"
                    </div>
                </td>
                <td><span style="font-size: 12px; color: #888;">{{ $review->created_at->format('d M, Y h:i A') }}</span></td>
                <td>
                    @if($review->order)
                    <a href="{{ route('order.details', $review->order_id) }}" class="progga-btn progga-btn-outline progga-btn-sm" title="View Order">
                        <i class="bi bi-eye"></i> View Order
                    </a>
                    @endif
                </td>
              </tr>
          @empty
              <tr><td colspan="5" class="text-center py-4 text-muted">No reviews found yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between;">
      <span class="progga-page-info">Showing {{ $reviews->firstItem() ?? 0 }}–{{ $reviews->lastItem() ?? 0 }} of {{ $reviews->total() }} reviews</span>
      <div class="progga-pagination">
          {{ $reviews->links('pagination::bootstrap-4') }}
      </div>
    </div>
  </div>
</main>
@endsection
