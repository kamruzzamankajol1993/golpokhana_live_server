<div class="modal fade progga-modal" id="addCustomerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="{{ route('customer.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Full Name <span class="progga-required">*</span></label><input type="text" name="name" class="progga-form-control" required></div></div>
            <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Phone <span class="progga-required">*</span></label><input type="tel" name="phone" class="progga-form-control" required></div></div>
            <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Email Address</label><input type="email" name="email" class="progga-form-control"></div></div>
            <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Date of Birth</label><input type="date" name="dob" class="progga-form-control progga-datepicker"></div></div>
            <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Initial Points</label><input type="number" name="points" class="progga-form-control" value="0" min="0"></div></div>
            <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Address</label><textarea name="address" class="progga-form-control progga-form-textarea" rows="2"></textarea></div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Save Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>
