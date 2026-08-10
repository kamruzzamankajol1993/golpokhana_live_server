<div class="modal fade" id="attendanceTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content progga-modal-content">
            <div class="modal-header progga-modal-header">
                <div>
                    <h5 class="modal-title">Download Attendance Template</h5>
                    <div class="hr-muted">The sheet will contain all eligible employees and dates for the selected month.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" action="{{ route('hr.attendance.template.download') }}">
                <div class="modal-body">
                    <div class="progga-form-group mb-3">
                        <label class="progga-form-label">Month <span class="text-danger">*</span></label>
                        <input type="month" name="month" class="progga-form-control" value="{{ now()->format('Y-m') }}" required>
                    </div>
                    <div class="progga-form-group">
                        <label class="progga-form-label">Department</label>
                        <select name="department_id" data-placeholder="All Departments">
                            <option value="">All Departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="attendance-import-help mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Existing attendance is included in the template. Blank Status rows are ignored when uploaded.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-download"></i> Download Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>
