<div class="modal fade progga-modal" id="editCustomerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editCustomerForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Full Name <span class="progga-required">*</span></label><input type="text" name="name" id="edit_c_name" class="progga-form-control" required></div></div>
            <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Phone <span class="progga-required">*</span></label><input type="tel" name="phone" id="edit_c_phone" class="progga-form-control" required></div></div>
            <div class="col-6"><div class="progga-form-group"><label class="progga-form-label">Email Address</label><input type="email" name="email" id="edit_c_email" class="progga-form-control"></div></div>
            <div class="col-md-6"><div class="progga-form-group"><label class="progga-form-label">Date of Birth</label><input type="date" name="dob" id="edit_c_dob" class="progga-form-control"></div></div>
            <div class="col-md-6">
    <div class="progga-form-group">
        <label class="progga-form-label d-flex justify-content-between align-items-center">
            <span>Points Adjustment</span>
            <span class="progga-badge progga-badge-secondary" style="font-size: 11px;">
                Current: <strong id="current_points_display">0</strong> pts
            </span>
        </label>
        <input type="number" name="point_adjustment" class="progga-form-control" placeholder="e.g. 10 or -5" value="0">

        <div class="text-muted mt-1" style="font-size: 11px; line-height: 1.4;">
            <i class="bi bi-info-circle text-primary"></i>
            পয়েন্ট <strong>বাড়াতে</strong> শুধু সংখ্যা লিখুন (যেমন: 10)। আর পয়েন্ট <strong>কমাতে</strong> আগে মাইনাস দিন (যেমন: -5)।
        </div>
    </div>
</div>
<div class="col-md-6">
    <div class="progga-form-group">
        <label class="progga-form-label">Adjustment Note</label>
        <input type="text" name="point_note" class="progga-form-control" placeholder="e.g. Returned item">
    </div>
</div>
            <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Address</label><textarea name="address" id="edit_c_address" class="progga-form-control progga-form-textarea" rows="2"></textarea></div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Update Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>
