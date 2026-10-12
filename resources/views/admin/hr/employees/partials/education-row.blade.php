@php $row = (array) ($row ?? []); @endphp
<div class="border rounded-3 p-3 mb-2 employee-education-row">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <strong class="small">Education Record</strong>
        <button type="button" class="progga-btn progga-btn-outline progga-btn-sm employee-remove-row" aria-label="Remove education"><i class="bi bi-trash"></i> Remove</button>
    </div>
    @if(!empty($row['id']))<input type="hidden" name="educations[{{ $rowIndex }}][id]" value="{{ $row['id'] }}">@endif
    <div class="row g-2">
        <div class="col-md-4"><label class="progga-form-label">Degree / Exam <span class="text-danger">*</span></label><input class="progga-form-control" name="educations[{{ $rowIndex }}][qualification]" maxlength="180" required value="{{ $row['qualification'] ?? '' }}" placeholder="e.g. BBA, HSC, SSC"></div>
        <div class="col-md-4"><label class="progga-form-label">Institution</label><input class="progga-form-control" name="educations[{{ $rowIndex }}][institution]" maxlength="255" value="{{ $row['institution'] ?? '' }}" placeholder="Institution name"></div>
        <div class="col-md-4"><label class="progga-form-label">Subject / Group</label><input class="progga-form-control" name="educations[{{ $rowIndex }}][field_of_study]" maxlength="180" value="{{ $row['field_of_study'] ?? '' }}"></div>
        <div class="col-md-3"><label class="progga-form-label">Passing Year</label><input type="number" min="1950" max="2100" step="1" class="progga-form-control" name="educations[{{ $rowIndex }}][passing_year]" value="{{ $row['passing_year'] ?? '' }}" placeholder="2024"></div>
        <div class="col-md-4"><label class="progga-form-label">Result / Grade</label><input class="progga-form-control" name="educations[{{ $rowIndex }}][result]" maxlength="100" value="{{ $row['result'] ?? '' }}" placeholder="CGPA / Grade"></div>
    </div>
</div>
