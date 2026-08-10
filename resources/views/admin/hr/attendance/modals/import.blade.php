<div class="modal fade" id="attendanceImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content progga-modal-content">
            <div class="modal-header progga-modal-header">
                <div>
                    <h5 class="modal-title">Import Attendance from Excel</h5>
                    <div class="hr-muted">Upload an XLSX, XLS or CSV file created from the attendance template.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="attendanceImportForm" method="POST" action="{{ route('hr.attendance.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <label class="attendance-upload-drop d-block mb-3" for="attendanceFile">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <strong>Choose attendance Excel file</strong>
                        <div class="hr-muted mt-1">Maximum size 10 MB</div>
                        <input type="file" id="attendanceFile" name="attendance_file" accept=".xlsx,.xls,.csv" class="progga-form-control mt-3" required>
                    </label>
                    <div class="progga-form-group">
                        <label class="progga-form-label">When attendance already exists</label>
                        <select name="duplicate_action" data-search="false" data-allow-clear="false">
                            <option value="update">Update existing attendance</option>
                            <option value="skip">Skip existing attendance</option>
                        </select>
                    </div>
                    <div class="attendance-import-help mt-3">
                        <div><i class="bi bi-check2 me-1"></i> Blank Status rows will be skipped.</div>
                        <div><i class="bi bi-lock me-1"></i> Approved Leave rows cannot be overwritten.</div>
                        <div><i class="bi bi-clock me-1"></i> Time format must be HH:MM, such as 09:30.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="attendanceImportSubmit" class="progga-btn progga-btn-primary"><i class="bi bi-upload"></i> Import Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>
