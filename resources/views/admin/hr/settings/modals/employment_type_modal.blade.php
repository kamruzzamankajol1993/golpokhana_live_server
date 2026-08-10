<div class="modal fade progga-modal" id="employmentTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-check me-2"></i><span class="modal-title-text">Add Employment Type</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="employmentTypeForm" class="hr-ajax-form" data-entity="employment-types" data-tab="employment-types">
            @csrf<input type="hidden" name="id">
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-8"><div class="progga-form-group"><label class="progga-form-label">Employment Type <span class="progga-required">*</span></label><input type="text" name="name" class="progga-form-control" placeholder="e.g. Permanent" required></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Code</label><input type="text" name="code" class="progga-form-control" placeholder="e.g. PERM"></div></div>
                <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Description</label><textarea name="description" class="progga-form-control progga-form-textarea" rows="3"></textarea></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Hourly Salary</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="is_hourly" value="1" data-on="Hourly" data-off="Monthly/Fixed"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Monthly/Fixed</span></label></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Sort Order</label><input type="number" name="sort_order" class="progga-form-control" min="0" value="0"></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Status</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="status" value="1" checked data-on="Active" data-off="Inactive"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Active</span></label></div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> <span class="hr-submit-text">Save Employment Type</span></button></div>
        </form>
    </div></div>
</div>
