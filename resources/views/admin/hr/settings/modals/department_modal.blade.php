<div class="modal fade progga-modal" id="departmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i><span class="modal-title-text">Add Department</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="departmentForm" class="hr-ajax-form" data-entity="departments" data-tab="departments">
            @csrf<input type="hidden" name="id">
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-8"><div class="progga-form-group"><label class="progga-form-label">Department Name <span class="progga-required">*</span></label><input type="text" name="name" class="progga-form-control" placeholder="e.g. Kitchen" required></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Code</label><input type="text" name="code" class="progga-form-control" placeholder="e.g. KIT"></div></div>
                <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Description</label><textarea name="description" class="progga-form-control progga-form-textarea" rows="3" placeholder="Short description..."></textarea></div></div>
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Sort Order</label><input type="number" name="sort_order" class="progga-form-control" min="0" value="0"></div></div>
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Status</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="status" value="1" checked data-on="Active" data-off="Inactive"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Active</span></label></div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> <span class="hr-submit-text">Save Department</span></button></div>
        </form>
    </div></div>
</div>
