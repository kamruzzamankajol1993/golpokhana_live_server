<div class="modal fade progga-modal" id="addTableModal" tabindex="-1">
  <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Table</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form action="{{ route('table.store') }}" method="POST">
            @csrf
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-6"><div class="progga-form-group"><label class="progga-form-label">Table Number <span class="progga-required">*</span></label><input type="text" name="table_number" class="progga-form-control" placeholder="e.g. T-11" required></div></div>
                <div class="col-6"><div class="progga-form-group"><label class="progga-form-label">Seating Capacity <span class="progga-required">*</span></label><input type="number" name="seating_capacity" class="progga-form-control" placeholder="e.g. 4" min="1" required></div></div>
                <div class="col-12">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Floor / Zone <span class="progga-required">*</span></label>
                        <select class="progga-select" name="zone_id" required>
                          <option value="">Select zone</option>
                          @foreach($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                          @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Initial Status</label>
                        <select class="progga-select" name="initial_status" required>
                          <option value="available">Available</option>
                          <option value="occupied">Occupied</option>
                          <option value="reserved">Reserved</option>
                        </select>
                    </div>
                </div>
                <div class="col-12"><div class="progga-form-group"><label class="progga-form-label">Notes</label><input type="text" name="notes" class="progga-form-control" placeholder="Optional notes (e.g. near window)"></div></div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Add Table</button>
            </div>
        </form>
    </div>
  </div>
</div>
