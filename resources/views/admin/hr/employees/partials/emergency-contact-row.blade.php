@php $row = (array) ($row ?? []); @endphp
<div class="border rounded-3 p-3 mb-2 employee-emergency-contact-row">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <strong class="small">Emergency Contact</strong>
        <button type="button" class="progga-btn progga-btn-outline progga-btn-sm employee-remove-row" aria-label="Remove emergency contact"><i class="bi bi-trash"></i> Remove</button>
    </div>
    @if(!empty($row['id']))<input type="hidden" name="emergency_contacts[{{ $rowIndex }}][id]" value="{{ $row['id'] }}">@endif
    <div class="row g-2">
        <div class="col-md-6">
            <label class="progga-form-label">Name <span class="text-danger">*</span></label>
            <input type="text" class="progga-form-control" name="emergency_contacts[{{ $rowIndex }}][name]" maxlength="180" required value="{{ $row['name'] ?? '' }}" placeholder="Contact person's name">
        </div>
        <div class="col-md-6">
            <label class="progga-form-label">Phone <span class="text-danger">*</span></label>
            <input type="text" class="progga-form-control" name="emergency_contacts[{{ $rowIndex }}][phone]" maxlength="40" required value="{{ $row['phone'] ?? '' }}" placeholder="Phone number">
        </div>
    </div>
</div>
