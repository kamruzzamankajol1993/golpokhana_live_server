<div class="progga-pos-cart-items" id="posCartItems">
    @forelse($cart as $cartId => $item)
        <div class="progga-pos-cart-item">
            <div class="progga-pos-item-name">
                {{ $item['name'] }}
                @if(!empty($item['is_complimentary']))
                    <span class="badge bg-success ms-1" style="font-size: 9px;">Complimentary</span>
                @endif
                @if(count($item['addons']) > 0)
                    <div style="font-size: 10px; color: #777; font-weight: normal; margin-top: 2px;">
                        @foreach($item['addons'] as $addon) +{{ $addon['name'] }} @endforeach
                    </div>
                @endif
            </div>
            <div class="progga-pos-item-controls">
                <button class="progga-qty-btn" type="button" onclick="updateQty('{{ $cartId }}', 'minus')">−</button>
                <input type="number"
                       class="progga-qty-display progga-qty-input"
                       value="{{ $item['qty'] }}"
                       min="0"
                       step="1"
                       onkeyup="scheduleCartQtyUpdate('{{ $cartId }}', this.value, this)"
                       onchange="setCartQty('{{ $cartId }}', this.value, this)"
                       onkeydown="if(event.key === 'Enter') { event.preventDefault(); setCartQty('{{ $cartId }}', this.value, this); }">
                <button class="progga-qty-btn" type="button" onclick="updateQty('{{ $cartId }}', 'plus')">+</button>
                <span class="progga-pos-item-total">৳{{ round(($item['price'] + $item['addon_total']) * $item['qty']) }}</span>
                <button class="progga-pos-item-remove" type="button" onclick="removeCartItem('{{ $cartId }}')">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="progga-pos-item-note">
                <input class="progga-form-control progga-pos-item-note-input"
                       placeholder="Add note..."
                       value="{{ $item['note'] ?? '' }}"
                       onchange="updateItemNote('{{ $cartId }}', this.value)">
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-5 mt-4" style="font-size: 14px;">🛒 Cart is empty!</div>
    @endforelse
</div>

@php
    $service_amount = round(($subtotal * $service_charge_rate) / 100);
    $vat_amount = round((($subtotal + $service_amount) * $vat_rate) / 100);
    $grand_total = round($subtotal + $vat_amount + $service_amount);
@endphp

<div class="progga-pos-cart-totals">
    <div class="progga-pos-total-row">
        <span>Subtotal</span>
        <span>৳{{ number_format($subtotal, 0) }}</span>
    </div>
 @if($service_charge_rate > 0)
    <div class="progga-pos-total-row">
        <span>Service Charge ({{ $taxSettingServiceCharge }}%)</span>
        <span id="display_service">৳{{ number_format($service_amount, 0) }}</span>
    </div>
    @endif
    <div class="progga-pos-total-row">
        <span>{{ $taxSettingTaxLabel }} ({{ $taxSettingVatRate }}%)</span>
        <span id="display_vat">৳{{ number_format($vat_amount, 0) }}</span>
    </div>

    <div class="progga-pos-total-row grand">
        <span>TOTAL</span>
        <span id="display_grand_total">৳{{ number_format($grand_total, 0) }}</span>
    </div>
</div>

<div class="progga-pos-cart-actions p-3">
    <div class="progga-pos-total-row d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="font-size: 13px; font-weight: 700; color: #555;">
        <span><i class="bi bi-clock-history text-warning me-1"></i> Preparation Time (Min)</span>
        <input type="number" class="form-control form-control-sm text-center" id="cart_prep_time" placeholder="20" value="20" min="1" style="width: 70px; font-weight: bold; border: 1.5px solid var(--progga-border);">
    </div>

    <button class="progga-btn progga-btn-primary w-100" id="btnSendToKitchen" style="padding:14px; font-weight:800;">
        <i class="bi bi-fire"></i> Send to Kitchen
    </button>
</div>

<style>
    .progga-qty-input {
        width: 42px;
        height: 28px;
        border: 1px solid var(--progga-border);
        border-radius: 6px;
        text-align: center;
        font-weight: 800;
        padding: 0 4px;
        background: #fff;
    }
    .progga-qty-input::-webkit-outer-spin-button,
    .progga-qty-input::-webkit-inner-spin-button {
        margin: 0;
    }
</style>

<script>
    // Dine-In, Takeaway এবং Delivery — সব POS cart একইভাবে আগে Kitchen-এ যাবে।
    $('#btnSendToKitchen').show();

    // Grand Total & Mobile FAB Logic
   function calculateGrandTotal() {
        let subtotal = parseFloat("{{ $subtotal ?? 0 }}") || 0;
        let vat_rate = parseFloat("{{ $vat_rate ?? 0 }}") || 0;
        let service_rate = parseFloat("{{ $service_charge_rate ?? 0 }}") || 0;

        let service = Math.round((subtotal * service_rate) / 100);
        let vat = Math.round(((subtotal + service) * vat_rate) / 100);
        let grand = Math.round(subtotal + vat + service);

        $('#display_grand_total').text('৳' + grand);
        $('#display_vat').text('৳' + vat);

        if(document.getElementById('display_service')) {
            $('#display_service').text('৳' + service);
        }

        if($('#fabCartTotal').length) {
            $('#fabCartTotal').text('৳' + grand);
        }

        if (typeof currentOrder !== 'undefined') {
            currentOrder.discount_type = 'fixed';
            currentOrder.discount_value = 0;
            currentOrder.grand_total = grand;
        }
    }

    // Dynamic Cart Count for Mobile FAB
    $(document).ready(function() {
        let totalQty = 0;
        @foreach($cart as $item)
            totalQty += {{ $item['qty'] }};
        @endforeach

        if(totalQty > 0) {
            $('#posCartFab').css('display', 'flex'); // Show FAB
            $('#fabCartCount').text(totalQty);
            $('#headerCartCount').text(totalQty).show();
        } else {
            $('#posCartFab').hide(); // Hide FAB if empty
            $('#headerCartCount').hide();
        }

        // Initial Calculation
        calculateGrandTotal();
    });

</script>
