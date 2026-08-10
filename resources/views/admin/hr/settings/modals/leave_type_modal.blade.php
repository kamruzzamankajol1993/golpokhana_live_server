<div class="modal fade progga-modal" id="leaveTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar2-check me-2"></i><span class="modal-title-text">Add Leave Type</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="leaveTypeForm" class="hr-ajax-form" data-entity="leave-types" data-tab="leave-types">
            @csrf<input type="hidden" name="id">
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Leave Type <span class="progga-required">*</span></label><input type="text" name="name" class="progga-form-control" placeholder="e.g. Casual Leave" required></div></div>
                <div class="col-md-3"><div class="progga-form-group"><label class="progga-form-label">Code</label><input type="text" name="code" class="progga-form-control" placeholder="CL"></div></div>
                <div class="col-md-3"><div class="progga-form-group"><label class="progga-form-label">Display Color</label><input type="color" name="color" class="progga-form-control" value="#21352a" style="height:42px;padding:4px;"></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Days Per Year</label><input type="number" step="0.5" name="days_per_year" class="progga-form-control" min="0" max="365" value="0" required></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Paid Leave</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="is_paid" value="1" checked data-on="Paid" data-off="Unpaid"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Paid</span></label></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Document Required</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="requires_document" value="1" data-on="Required" data-off="Not Required"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Not Required</span></label></div></div>
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Carry Forward</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="allow_carry_forward" value="1" data-on="Allowed" data-off="Not Allowed"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Not Allowed</span></label></div></div>
                <div class="col-md-6" id="leaveCarryForwardFields" style="display:none;"><div class="progga-form-group"><label class="progga-form-label">Maximum Carry Forward Days</label><input type="number" step="0.5" name="max_carry_forward_days" class="progga-form-control" min="0" max="365" value="0"></div></div>
                <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Description</label><textarea name="description" class="progga-form-control progga-form-textarea" rows="3"></textarea></div></div>
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Sort Order</label><input type="number" name="sort_order" class="progga-form-control" min="0" value="0"></div></div>
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Status</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="status" value="1" checked data-on="Active" data-off="Inactive"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Active</span></label></div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> <span class="hr-submit-text">Save Leave Type</span></button></div>
        </form>
    </div></div>
</div>
