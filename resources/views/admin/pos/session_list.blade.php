@extends('admin.master.master')
@section('title', 'POS Session List — ' . $restaurantSettingName)

@section('css')
<style>
    .pos-list-table th { white-space: nowrap; font-size: 11px; }
    .pos-list-table td { vertical-align: middle; font-size: 12px; }
    .pos-list-loading { opacity: .55; pointer-events: none; }
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
                <span class="progga-breadcrumb-item active">POS Session List</span>
            </div>
        </div>
        <a href="{{ route('pos.index') }}" class="progga-btn progga-btn-primary progga-btn-sm">
            <i class="bi bi-display"></i> Open POS
        </a>
    </div>

    <div class="progga-card" id="sessionListCard">
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">Work Period Sessions</div>
                <div class="progga-card-subtitle">Same session history previously available from the POS header.</div>
            </div>
            <span class="progga-badge progga-badge-secondary">{{ number_format($sessions->total()) }} sessions</span>
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
                <tbody id="sessionListRows">
                    @include('admin.pos.partials.session_list_rows')
                </tbody>
            </table>
        </div>

        <div id="sessionListPagination">
            @include('admin.reports.partials.custom_pagination', ['paginator' => $sessions])
        </div>
    </div>
</main>

<div class="modal fade progga-modal" id="editSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit POS Session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSessionForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="editSessionId" name="session_id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="progga-form-label">Start Time</label>
                            <input type="datetime-local" id="editStartTime" name="start_time" class="progga-form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">End Time</label>
                            <input type="datetime-local" id="editEndTime" name="end_time" class="progga-form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">Status</label>
                            <select id="editStatus" name="status" class="progga-select">
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary" id="saveSessionButton">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function() {
    function loadSessionPage(url, pushState) {
        $('#sessionListCard').addClass('pos-list-loading');
        $.ajax({
            url: url,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function(data) {
                $('#sessionListRows').html(data.html || '');
                $('#sessionListPagination').html(data.pagination || '');
                if (pushState) window.history.pushState({}, '', url);
            },
            complete: function() {
                $('#sessionListCard').removeClass('pos-list-loading');
            }
        });
    }

    $(document).on('click', '#sessionListPagination a', function(event) {
        event.preventDefault();
        if ($(this).hasClass('disabled') || $(this).attr('aria-disabled') === 'true') return;
        const url = $(this).attr('href');
        if (!url || url === '#') return;
        loadSessionPage(url, true);
    });

    $(document).on('click', '.btnEditSession', function() {
        $('#editSessionId').val($(this).data('id'));
        $('#editStartTime').val($(this).attr('data-start') || '');
        $('#editEndTime').val($(this).attr('data-end') || '');
        $('#editStatus').val(String($(this).data('status') || 'Open'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editSessionModal')).show();
    });

    $('#editSessionForm').on('submit', function(event) {
        event.preventDefault();
        const $button = $('#saveSessionButton');
        const original = $button.html();
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

        $.post("{{ route('pos.session.update') }}", $(this).serialize())
            .done(function(response) {
                if (response.status === 'success') {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editSessionModal')).hide();
                    Swal.fire('Updated!', response.message, 'success');
                    loadSessionPage(window.location.href, false);
                } else {
                    Swal.fire('Error', response.message || 'Unable to update session.', 'error');
                }
            })
            .fail(function(xhr) {
                const response = xhr.responseJSON || {};
                const message = response.message || 'Something went wrong while updating the session.';
                const title = response.code === 'session_close_blocked' ? 'Cannot Close Session' : 'Error';
                Swal.fire(title, message, 'error');
            })
            .always(function() {
                $button.prop('disabled', false).html(original);
            });
    });
})();
</script>
@endsection
