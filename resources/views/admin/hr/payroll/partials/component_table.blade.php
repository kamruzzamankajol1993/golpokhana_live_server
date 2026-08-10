<div class="progga-table-wrapper" style="border:0;border-radius:0">
    <table class="progga-table payroll-component-table">
        <thead><tr><th>Component</th><th>Calculation</th><th>Rate</th><th>Quantity</th><th style="width:240px">Amount</th></tr></thead>
        <tbody>
            @forelse($components as $component)
                @php
                    $isChanged = abs((float) $component->amount - (float) $component->calculated_amount) >= 0.01;
                    $calculationLabel = ucwords(str_replace('_', ' ', $component->calculation_type));
                @endphp
                <tr class="{{ $component->is_overridden ? 'payroll-overridden-row' : '' }}">
                    <td>
                        <div style="font-weight:800">{{ $component->component_name }}</div>
                        <div class="hr-person-meta">
    {{ $component->component_code ?: 'No code' }}
    @if($component->is_manual)
        · Manual
    @endif
</div>
                    </td>
                    <td><span class="hr-badge hr-badge-neutral">{{ $calculationLabel }}</span></td>
                    <td>{{ number_format((float) $component->rate, 2) }}</td>
                    <td>{{ number_format((float) $component->quantity, 2) }}</td>
                    <td>
                        @if($editable && auth()->user()->can('payroll-edit'))
                            <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" name="components[{{ $component->id }}][amount]" class="progga-form-control payroll-component-amount" value="{{ old('components.' . $component->id . '.amount', $component->amount) }}" data-type="{{ $component->component_type }}" data-calculated="{{ $component->calculated_amount }}" data-manual="{{ $component->is_manual ? 1 : 0 }}"></div>
                            <div class="payroll-calculated-note">Calculated: ৳{{ number_format((float) $component->calculated_amount, 2) }}</div>
                            <div class="payroll-override-reason-wrap mt-2 {{ (!$isChanged || $component->is_manual) ? 'd-none' : '' }}">
                                <input type="text" name="components[{{ $component->id }}][reason]" class="progga-form-control" value="{{ old('components.' . $component->id . '.reason', $component->override_reason) }}" placeholder="Reason for override">
                            </div>
                        @else
                            <strong>৳{{ number_format((float) $component->amount, 2) }}</strong>
                            @if($component->is_overridden)
    <div class="hr-person-meta text-warning">Overridden: {{ $component->override_reason }}</div>
@endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="hr-empty py-4">No component found.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
