<div class="modal fade progga-modal" id="zoneModal" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-geo-alt me-2"></i>Manage Zones</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="zoneForm" class="mb-4">
            @csrf
            <input type="hidden" id="zone_id" name="id">
            <div class="d-flex align-items-center gap-2">
                <div style="flex-grow: 1;">
                    <input type="text" name="name" id="zone_name" class="progga-form-control" placeholder="Zone Name" required>
                </div>
                <div>
                    <label class="progga-toggle" style="margin-bottom: 0;">
                        <input type="checkbox" name="status" id="zone_status" checked>
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                    </label>
                </div>
                <div>
                    <button type="submit" class="progga-btn progga-btn-primary" id="zoneSubmitBtn">Save</button>
                </div>
            </div>
        </form>

        <div class="progga-table-wrapper" style="max-height: 300px; overflow-y: auto;">
            <table class="progga-table table-sm">
                <thead><tr><th>Name</th><th>Status</th><th>Action</th></tr></thead>
                <tbody id="zoneTableBody">
                    </tbody>
            </table>
        </div>
      </div>
    </div>
  </div>
</div>
