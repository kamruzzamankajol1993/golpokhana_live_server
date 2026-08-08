<div class="modal fade progga-modal" id="addOccasionModal" tabindex="-1" style="z-index: 1060;">
  <div class="modal-dialog modal-lg"> <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-star-fill me-2 text-warning"></i>Manage Occasions</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-0">
            <div class="row g-0">

                <div class="col-md-5 p-4 border-end">
                    <h6 class="fw-bold mb-3" id="occasionFormTitle">Add New Occasion</h6>
                    <form id="occasionAjaxForm">
                        @csrf
                        <input type="hidden" name="occasion_id" id="occasion_id" value="">

                        <div class="progga-form-group">
                            <label class="progga-form-label">Occasion Name <span class="progga-required">*</span></label>
                            <input type="text" name="name" id="occasion_name" class="progga-form-control" placeholder="e.g. Birthday, Meeting" required>
                        </div>
                        <div class="progga-form-group">
                            <label class="progga-form-label">Status</label>
                            <select name="status" id="occasion_status" class="progga-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="progga-btn progga-btn-primary flex-grow-1" id="occasionSubmitBtn"><i class="bi bi-check-lg"></i> Save</button>
                            <button type="button" class="progga-btn progga-btn-outline" id="occasionResetBtn" style="display:none;" onclick="resetOccasionForm()"><i class="bi bi-x"></i> Cancel</button>
                        </div>
                    </form>
                </div>

                <div class="col-md-7 p-4 bg-light">
                    <div class="progga-table-wrapper" id="occasionTableContent" style="min-height: 250px;">
                        <div class="text-center py-5 text-muted"><div class="spinner-border text-primary spinner-border-sm"></div> Loading...</div>
                    </div>
                </div>

            </div>
        </div>
    </div>
  </div>
</div>
