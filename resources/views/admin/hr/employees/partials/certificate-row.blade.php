@php $row = (array) ($row ?? []); @endphp
<div class="border rounded-3 p-3 mb-2 employee-certificate-row">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <strong class="small">Certificate</strong>
        <button type="button" class="progga-btn progga-btn-outline progga-btn-sm employee-remove-row" aria-label="Remove certificate"><i class="bi bi-trash"></i> Remove</button>
    </div>
    @if(!empty($row['id']))<input type="hidden" name="certificates[{{ $rowIndex }}][id]" value="{{ $row['id'] }}">@endif
    <div class="row g-2 align-items-end">
        <div class="col-md-5"><label class="progga-form-label">Title <span class="text-danger">*</span></label><input type="text" class="progga-form-control" name="certificates[{{ $rowIndex }}][title]" maxlength="255" required value="{{ $row['title'] ?? '' }}" placeholder="Certificate title"></div>
        <div class="col-md-7">
            <label class="progga-form-label">File {{ !empty($row['file_path']) ? '(optional: replace existing)' : '*' }}</label>
            <input type="file" class="progga-form-control" name="certificates[{{ $rowIndex }}][file]" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" {{ empty($row['file_path']) ? 'required' : '' }}>
            @if(!empty($row['file_path']))
                <a class="small d-inline-block mt-1" target="_blank" rel="noopener noreferrer" href="{{ asset('public/' . ltrim($row['file_path'], '/')) }}"><i class="bi bi-file-earmark-arrow-down"></i> View existing file</a>
            @endif
        </div>
        <div class="col-12">
            <label class="progga-form-label">Description <span class="hr-muted">(optional)</span></label>
            <textarea class="progga-form-control" name="certificates[{{ $rowIndex }}][description]" rows="2" maxlength="2000" placeholder="Certificate description (optional)">{{ $row['description'] ?? '' }}</textarea>
        </div>
    </div>
    <div class="hr-muted mt-1">PDF, image, DOC or DOCX · maximum 5 MB.</div>
</div>
