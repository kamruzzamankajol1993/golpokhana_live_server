<div class="modal fade progga-modal" id="holidayModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i><span class="modal-title-text">Add Holiday</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="holidayForm" class="hr-ajax-form" data-entity="holidays" data-tab="holidays">
            @csrf<input type="hidden" name="id">
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Holiday Name <span class="progga-required">*</span></label><input type="text" name="name" class="progga-form-control" placeholder="e.g. Victory Day" required></div></div>
                <div class="col-md-3"><div class="progga-form-group"><label class="progga-form-label">Date <span class="progga-required">*</span></label><input type="text" id="hrHolidayDate" name="holiday_date" class="progga-form-control progga-datepicker" placeholder="DD-MM-YYYY" autocomplete="off" required></div></div>
                <div class="col-md-3"><div class="progga-form-group"><label class="progga-form-label">Holiday Type</label><select id="hrHolidayType" name="holiday_type" class="progga-select hr-select2"><option value="public">Public</option><option value="company">Company</option><option value="special">Special</option></select></div></div>
                <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Description</label><textarea name="description" class="progga-form-control progga-form-textarea" rows="3"></textarea></div></div>
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Paid Holiday</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="is_paid" value="1" checked data-on="Paid" data-off="Unpaid"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Paid</span></label></div></div>
                <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Status</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="status" value="1" checked data-on="Active" data-off="Inactive"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Active</span></label></div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> <span class="hr-submit-text">Save Holiday</span></button></div>
        </form>
    </div></div>
</div>
