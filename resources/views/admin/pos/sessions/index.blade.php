@extends('admin.master.master')
@section('title', 'POS Session List — ' . $restaurantSettingName)

@section('css')
<style>
    .pos-list-table th { white-space:nowrap; font-size:11px; }
    .pos-list-table td { font-size:12px; vertical-align:middle; }
    .pos-list-loading { opacity:.55; pointer-events:none; }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">POS Session List</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item">POS System</span>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Session List</span>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('pos.sessions.pdf') }}" target="_blank" rel="noopener" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <a href="{{ route('pos.sessions.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm" style="border-color:#198754;color:#198754;background:#f8fff9;"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>
    </div>

    <div class="progga-card" id="sessionListCard">
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">Work Period Sessions</div>
                <div class="progga-card-subtitle">Same POS session history list with AJAX pagination</div>
            </div>
        </div>

        <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
            <table class="progga-table pos-list-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Day</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Grand Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sessionRows">
                    @include('admin.pos.sessions.partials.rows')
                </tbody>
            </table>
        </div>
        <div id="sessionPagination">
            @include('admin.reports.partials.custom_pagination', ['paginator' => $sessions])
        </div>
    </div>
</main>

<div class="modal fade" id="editSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="editSessionTitle"><i class="bi bi-pencil-square me-2"></i>Edit Session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSessionForm">
                <div class="modal-body">
                    <input type="hidden" name="session_id" id="editSessionId">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="progga-form-label">Start Time</label>
                            <input type="datetime-local" name="start_time" id="editSessionStart" class="progga-form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">End Time</label>
                            <input type="datetime-local" name="end_time" id="editSessionEnd" class="progga-form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">Status</label>
                            <select name="status" id="editSessionStatus" class="progga-select" required>
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function() {
    function loadSessionPage(url) {
        $('#sessionListCard').addClass('pos-list-loading');
        $.ajax({
            url: url,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function(data) {
                $('#sessionRows').html(data.html || '');
                $('#sessionPagination').html(data.pagination || '');
                window.history.pushState({}, '', url);
            },
            complete: function() { $('#sessionListCard').removeClass('pos-list-loading'); }
        });
    }

    $(document).on('click', '#sessionPagination a', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if (!url || url === '#' || $(this).hasClass('disabled')) return;
        loadSessionPage(url);
    });

    $(document).on('click', '.btnEditSession', function() {
        $('#editSessionId').val($(this).data('id'));
        $('#editSessionStart').val($(this).attr('data-start'));
        $('#editSessionEnd').val($(this).attr('data-end'));
        $('#editSessionStatus').val($(this).data('status'));
        $('#editSessionTitle').html('<i class="bi bi-pencil-square me-2"></i>Edit Session #' + $(this).data('id'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editSessionModal')).show();
    });

    $('#editSessionForm').on('submit', function(e) {
        e.preventDefault();
        const $button = $(this).find('button[type="submit"]');
        const oldHtml = $button.html();
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

        $.post("{{ route('pos.session.update') }}", $(this).serialize())
            .done(function(res) {
                if (res.status === 'success') {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editSessionModal')).hide();
                    Swal.fire({icon:'success', title:'Updated', text:res.message, timer:1400, showConfirmButton:false});
                    loadSessionPage(window.location.href);
                }
            })
            .fail(function(xhr) {
                const response = xhr.responseJSON || {};
                const message = response.message || 'Something went wrong while updating the session.';
                const title = response.code === 'session_close_blocked' ? 'Cannot Close Session' : 'Error';
                Swal.fire(title, message, 'error');
            })
            .always(function() { $button.prop('disabled', false).html(oldHtml); });
    });
})();
</script>
@endsection
