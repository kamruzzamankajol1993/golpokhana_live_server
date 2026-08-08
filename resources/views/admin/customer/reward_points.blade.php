@extends('admin.master.master')
@section('title', 'Reward Point Settings — Progga RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Reward Settings</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('customer.index') }}" class="progga-breadcrumb-item">Customers</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Reward Points</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="font-size:13px;"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="font-size:13px;"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="row justify-content-center">
        <div class="col-md-8">
            <form action="{{ route('reward-points.update') }}" method="POST">
                @csrf
                <div class="progga-card mb-4">
                    <div class="progga-card-header">
                        <div class="progga-card-title"><i class="bi bi-star-fill me-2 text-warning"></i> 1. Earn Points (পয়েন্ট পাওয়ার নিয়ম)</div>
                    </div>
                    <div class="progga-card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="progga-card" style="border: 2px solid {{ $setting->reward_type == 'order_based' ? 'var(--progga-primary)' : 'var(--progga-border)' }}; box-shadow: none;">
                                    <div class="p-3">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="reward_type" id="order_based" value="order_based" {{ $setting->reward_type == 'order_based' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="order_based">Option A: Order Based</label>
                                        </div>
                                        <div class="progga-form-group mb-0">
                                            <label class="progga-form-label">Points per 1 Order</label>
                                            <input type="number" name="points_per_order" class="progga-form-control" value="{{ $setting->points_per_order }}" min="0">
                                            <div class="text-muted mt-1" style="font-size: 11px;">Example: 1 Order = 1 Point</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="progga-card" style="border: 2px solid {{ $setting->reward_type == 'amount_based' ? 'var(--progga-primary)' : 'var(--progga-border)' }}; box-shadow: none;">
                                    <div class="p-3">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="reward_type" id="amount_based" value="amount_based" {{ $setting->reward_type == 'amount_based' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="amount_based">Option B: Amount Based</label>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="progga-form-group mb-0">
                                                    <label class="progga-form-label">Spend Amount (৳)</label>
                                                    <input type="number" name="amount_to_spend" class="progga-form-control" value="{{ $setting->amount_to_spend }}" min="0" step="0.01">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="progga-form-group mb-0">
                                                    <label class="progga-form-label">Earn Points</label>
                                                    <input type="number" name="points_per_amount" class="progga-form-control" value="{{ $setting->points_per_amount }}" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-muted mt-1" style="font-size: 11px;">Example: Spend 500৳ = Earn 1 Point</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="progga-card mb-4">
                    <div class="progga-card-header">
                        <div class="progga-card-title"><i class="bi bi-gift-fill me-2 text-success"></i> 2. Redeem Points (পয়েন্ট ভাঙ্গানোর নিয়ম)</div>
                    </div>
                    <div class="progga-card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <div class="progga-form-group mb-0">
                                    <label class="progga-form-label">Points to Redeem</label>
                                    <div class="input-group">
                                        <input type="number" name="points_to_redeem" class="progga-form-control" value="{{ $setting->points_to_redeem }}" min="1" required>
                                        <span class="input-group-text">Pts</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="fw-bold" style="font-size: 24px; color: var(--progga-text-muted); margin-top: 15px;">=</div>
                            </div>
                            <div class="col-md-5">
                                <div class="progga-form-group mb-0">
                                    <label class="progga-form-label">Discount Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input type="number" name="discount_amount" class="progga-form-control" value="{{ $setting->discount_amount }}" min="0" step="0.01" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted" style="font-size: 12px;"><i class="bi bi-info-circle me-1"></i> Example: 100 Points will give the customer a 10 Taka discount on their total bill.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    @can('reward-edit')
                    <button type="submit" class="progga-btn progga-btn-primary">
                        <i class="bi bi-check-lg"></i> Save All Settings
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</main>

@section('script')
<script>
    // কার্ড সিলেক্ট করলে বর্ডার কালার ডাইনামিক চেঞ্জ করার জন্য
    $('input[name="reward_type"]').on('change', function() {
        $('.progga-card').css('border-color', 'var(--progga-border)');
        $(this).closest('.progga-card').css('border-color', 'var(--progga-primary)');
    });
</script>
@endsection
@endsection
