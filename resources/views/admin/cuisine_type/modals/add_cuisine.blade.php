<div class="modal fade progga-modal" id="addCuisineModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Cuisine Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="addCuisineForm">
            @csrf
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Cuisine Name <span class="progga-required">*</span></label><input type="text" name="name" class="progga-form-control" placeholder="e.g. Bangladeshi" required></div></div>
                    <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Origin Country</label><input type="text" name="origin_country" class="progga-form-control" placeholder="e.g. Bangladesh"></div></div>
                    <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Description</label><textarea name="description" class="progga-form-control progga-form-textarea" rows="3" placeholder="Brief description..."></textarea></div></div>
                    <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Status</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="is_active" value="1" checked data-on="Active" data-off="Inactive"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Active</span></label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
        </form>
    </div></div>
</div>
