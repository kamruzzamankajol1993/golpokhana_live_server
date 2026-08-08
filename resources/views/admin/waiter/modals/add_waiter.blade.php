<div class="modal fade progga-modal" id="addWaiterModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Waiter</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="{{ route('waiter.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Full Name <span class="progga-required">*</span></label>
                    <input type="text" name="name" class="progga-form-control" placeholder="Karim Ahmed" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Phone Number <span class="progga-required">*</span></label>
                    <input type="text" name="phone" class="progga-form-control" placeholder="017XXXXXXXX" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Email Address</label>
                    <input type="email" name="email" class="progga-form-control" placeholder="waiter@example.com">
                </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Profile Image</label>
                    <input type="file" name="image" class="progga-form-control" accept="image/*">
                </div>
            </div>
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">Assigned Zone <span class="progga-required">*</span></label>
                <select class="progga-select" name="zone_id" required>
                    <option value="">Select Zone</option>
                    @foreach($zones as $zone) <option value="{{ $zone->id }}">{{ $zone->name }}</option> @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">Shift <span class="progga-required">*</span></label>
                <select class="progga-select" name="shift_id" required>
                    <option value="">Select Shift</option>
                    @foreach($shifts as $shift) <option value="{{ $shift->id }}">{{ $shift->name }}</option> @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Join Date</label>
                    <input type="date" name="join_date" class="progga-form-control" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="progga-form-group">
                    <label class="progga-form-label">Status</label>
                    <label class="progga-toggle" style="margin-top:8px;">
                        <input type="checkbox" name="status" checked>
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">Active</span>
                    </label>
                </div>
            </div>
            <div class="col-12">
                <div class="progga-card" style="background: var(--progga-bg-light); border: 1px dashed var(--progga-border);">
                    <div class="p-3">
                        <label class="progga-toggle">
                            <input type="checkbox" name="create_account" id="createAccountCheck">
                            <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                            <span class="progga-toggle-label" style="font-weight: 700;">Create Login Account for this Waiter?</span>
                        </label>
                        <div class="mt-2 text-muted" style="font-size: 11px;">If checked, a user will be created with 'waiter' role. Password will be the phone number.</div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="progga-form-group">
                    <label class="progga-form-label">Notes</label>
                    <textarea name="notes" class="progga-form-control progga-form-textarea" rows="2"></textarea>
                </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Save Waiter</button>
        </div>
      </form>
    </div>
  </div>
</div>
