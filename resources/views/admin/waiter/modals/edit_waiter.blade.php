<div class="modal fade progga-modal" id="editWaiterModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Waiter</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editWaiterActualForm" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Full Name <span class="progga-required">*</span></label>
                    <input type="text" name="name" id="edit_w_name" class="progga-form-control" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Phone Number <span class="progga-required">*</span></label>
                    <input type="text" name="phone" id="edit_w_phone" class="progga-form-control" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Email Address</label>
                    <input type="email" name="email" id="edit_w_email" class="progga-form-control">
                </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Update Image</label>
                    <input type="file" name="image" class="progga-form-control" accept="image/*">
                </div>
            </div>
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">Assigned Zone <span class="progga-required">*</span></label>
                <select class="progga-select" name="zone_id" id="edit_w_zone" required>
                    @foreach($zones as $zone) <option value="{{ $zone->id }}">{{ $zone->name }}</option> @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">Shift <span class="progga-required">*</span></label>
                <select class="progga-select" name="shift_id" id="edit_w_shift" required>
                    @foreach($shifts as $shift) <option value="{{ $shift->id }}">{{ $shift->name }}</option> @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Join Date</label>
                    <input type="date" name="join_date" id="edit_w_join" class="progga-form-control">
                </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Status</label>
                    <label class="progga-toggle" style="margin-top:8px;">
                        <input type="checkbox" name="status" id="edit_w_status" value="1">
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">Active</span>
                    </label>
                </div>
            </div>
            <div class="col-12">
                <div class="progga-form-group">
                    <label class="progga-form-label">Notes</label>
                    <textarea name="notes" id="edit_w_notes" class="progga-form-control progga-form-textarea" rows="2"></textarea>
                </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Update Waiter</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// এডিট বাটনে ক্লিক করলে ডাটা ফিলাপ করার ফাংশন
function editWaiterData(id, name, phone, email, emp_id, zone_id, shift_id, join_date, status, notes) {
    let formAction = "{{ route('waiter.update', ':id') }}".replace(':id', id);
    $('#editWaiterActualForm').attr('action', formAction);

    $('#edit_w_name').val(name);
    $('#edit_w_phone').val(phone);
    $('#edit_w_email').val(email);
    $('#edit_w_zone').val(zone_id).trigger('change');
    $('#edit_w_shift').val(shift_id).trigger('change');
    $('#edit_w_join').val(join_date);
    $('#edit_w_notes').val(notes);
    $('#edit_w_status').prop('checked', status == 1);

    $('#editWaiterModal').modal('show');
}
</script>
