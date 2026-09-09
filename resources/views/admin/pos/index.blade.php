@extends('admin.pos.master')

@section('title', 'POS System — ' . $restaurantSettingName)

@section('css')
<style>
    .modal-backdrop { display: none !important; }
    body.modal-open { overflow: auto !important; padding-right: 0 !important; }

    /* Keep Select2 selections fixed inside POS modals: hide the clear (x) control. */
    .modal .select2-selection__clear {
        display: none !important;
    }

    .swal2-container {
        z-index: 99999 !important;
    }

    .pos-type-wrap:has(#posTypeDineIn:checked) label[for="posTypeDineIn"],
    .pos-type-wrap:has(#posTypeTakeaway:checked) label[for="posTypeTakeaway"],
    .pos-type-wrap:has(#posTypeDelivery:checked) label[for="posTypeDelivery"] {
      background: var(--progga-primary) !important;
      color: var(--progga-secondary) !important;
    }


    .progga-session-status {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
      border: 1px solid rgba(25, 135, 84, 0.25);
      background: rgba(25, 135, 84, 0.10);
      color: #198754;
      white-space: nowrap;
    }

    .progga-session-status.no-session {
      border-color: rgba(220, 53, 69, 0.25);
      background: rgba(220, 53, 69, 0.10);
      color: #dc3545;
    }

    .progga-session-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: #198754;
      box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.14);
    }

    .progga-session-status.no-session .progga-session-dot {
      background: #dc3545;
      box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.14);
    }



    .progga-pos-cat-name {
      font-size: 11px !important;
      line-height: 1.2 !important;
      font-weight: 900 !important;
    }

    .pos-closed-screen {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: #f4f6f9;
    }

    .pos-closed-card {
      width: min(560px, 100%);
      background: #ffffff;
      border: 1px solid rgba(220, 53, 69, 0.18);
      border-radius: 20px;
      box-shadow: 0 18px 45px rgba(31, 41, 55, 0.12);
      padding: 42px 30px;
      text-align: center;
    }

    .pos-closed-icon {
      width: 76px;
      height: 76px;
      margin: 0 auto 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(220, 53, 69, 0.10);
      color: #dc3545;
      font-size: 34px;
    }

    .pos-closed-title {
      margin: 0 0 10px;
      font-size: 28px;
      font-weight: 900;
      color: #1f2937;
    }

    .pos-closed-message {
      margin: 0;
      font-size: 20px;
      font-weight: 800;
      color: #dc3545;
    }

    .pos-closed-window {
      margin-top: 14px;
      font-size: 13px;
      color: #6b7280;
    }

    .pos-closed-actions {
      margin-top: 24px;
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    @media (max-width: 767.98px) {
      .progga-session-status {
        padding: 6px 9px;
        font-size: 11px;
      }
      .progga-session-start-label {
        display: none;
      }

      /* New Order modal: keep Dine-In / Takeaway / Delivery inside the screen. */
      #newOrderModal .pos-type-wrap {
        width: 100%;
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0 !important;
        overflow: hidden;
      }
      #newOrderModal .pos-type-wrap label {
        width: 100%;
        min-width: 0;
        padding: 11px 5px;
        gap: 4px;
        font-size: 12px;
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
      }
      #newOrderModal .pos-type-wrap label + label {
        border-left: 1px solid var(--progga-border-light);
      }
      #newOrderModal .pos-type-wrap label i {
        flex: 0 0 auto;
        font-size: 14px;
      }
    }

    @media (max-width: 420px) {
      #newOrderModal .modal-body {
        padding: 18px 14px !important;
      }
      #newOrderModal .pos-type-wrap label {
        padding-left: 3px;
        padding-right: 3px;
        font-size: 11px;
      }
    }
</style>
@endsection

@section('body')
@if(!($isPosOrderTimeOpen ?? true))
<div class="pos-closed-screen" aria-hidden="true">
    <div class="pos-closed-icon"><i class="bi bi-clock-history"></i></div>
</div>
@else
<div class="progga-pos-wrapper">
   <div class="progga-pos-header">
      <div class="progga-pos-logo">
        @if(!empty($restaurantSettingIconName))
            <img src="{{ asset('public/'.$restaurantSettingIconName) }}" alt="Icon" style="width: 60px; height: 60px; object-fit: contain;">
        @else
            {{ strtoupper(substr($restaurantSettingName ?? 'P', 0, 1)) }}
        @endif
        <span>{{ $restaurantSettingName }}</span>
      </div>

      <div class="progga-pos-step-indicator">
        <div class="progga-pos-step active" id="indStep1"><span class="progga-pos-step-num">1</span> Table</div>
        <span class="progga-pos-step-divider">›</span>
        <div class="progga-pos-step" id="indStep2"><span class="progga-pos-step-num">2</span> Order</div>
      </div>

      <div class="d-flex align-items-center pos-header-desktop-actions" style="gap: 15px;">
        @if($randomHalfOrderButtonVisible ?? false)
            <form action="{{ route('pos.random_half_order.activate') }}" method="POST" class="m-0 p-0">
                @csrf
                <button type="submit" class="progga-btn progga-btn-primary progga-btn-sm text-decoration-none" title="Apply saved order hide percentage">
                    <i class="bi bi-shuffle"></i> Go
                </button>
            </form>
        @endif

        @if($activeSession)
            <div class="progga-session-status" title="Current POS session is running">
                <span class="progga-session-dot"></span>
                <span>Session Running</span>
                <span class="progga-session-start-label">• Started {{ $activeSession->start_time->format('h:i A') }}</span>
                <span>• <span id="posSessionTimer" data-pos-session-timer data-start="{{ $activeSession->start_time->format('Y-m-d H:i:s') }}">00h 00m</span></span>
            </div>
            <button type="button" class="progga-btn progga-btn-danger progga-btn-sm js-end-pos-session" data-session-id="{{ $activeSession->id }}">
                <i class="bi bi-stop-circle-fill"></i> End Session
            </button>
        @else
            <div class="progga-session-status no-session" title="Start a POS session before taking orders">
                <span class="progga-session-dot"></span>
                <span>No Active Session</span>
            </div>
            <button type="button" class="progga-btn progga-btn-primary progga-btn-sm js-start-pos-session">
                <i class="bi bi-play-circle-fill"></i> Start Session
            </button>
        @endif

        @if(!auth()->user()->hasRole('waiter'))
            <button type="button" class="progga-btn progga-btn-secondary progga-btn-sm text-decoration-none" data-bs-toggle="modal" data-bs-target="#sessionHistoryModal">
                <i class="bi bi-history"></i> Session History
            </button>
            <a href="{{ route('home') }}" class="progga-pos-close m-0"><i class="bi bi-house"></i></a>
        @else
            <form action="{{ route('logout') }}" method="POST" class="m-0 p-0" data-pos-logout="1">
                @csrf
                <button type="submit" class="progga-btn progga-btn-danger progga-btn-sm" title="Logout from POS">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </form>
        @endif
      </div>

      <button type="button"
              class="progga-pos-close pos-header-mobile-trigger m-0"
              data-bs-toggle="offcanvas"
              data-bs-target="#posMobileHeaderMenu"
              aria-controls="posMobileHeaderMenu"
              aria-label="Open POS menu">
          <i class="bi bi-house"></i>
      </button>
    </div>

    <div class="progga-pos-body">
        @include('admin.pos.step1_tables')
        @include('admin.pos.step2_order')
    </div>
</div>

<div class="offcanvas offcanvas-end pos-mobile-header-offcanvas" tabindex="-1" id="posMobileHeaderMenu" aria-labelledby="posMobileHeaderMenuLabel">
    <div class="offcanvas-header pos-mobile-header-offcanvas-head">
        <div>
            <div class="pos-mobile-menu-kicker">POS MENU</div>
            <h5 class="offcanvas-title" id="posMobileHeaderMenuLabel">{{ $restaurantSettingName }}</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body pos-mobile-header-offcanvas-body">
        @if($randomHalfOrderButtonVisible ?? false)
            <form action="{{ route('pos.random_half_order.activate') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="progga-btn progga-btn-primary pos-mobile-menu-action w-100" title="Apply saved order hide percentage">
                    <i class="bi bi-shuffle"></i> Go
                </button>
            </form>
        @endif

        @if($activeSession)
            <div class="pos-mobile-session-card" title="Current POS session is running">
                <div class="pos-mobile-session-title">
                    <span class="progga-session-dot"></span>
                    <span>Session Running</span>
                </div>
                <div class="pos-mobile-session-line">• Started {{ $activeSession->start_time->format('h:i A') }}</div>
                <div class="pos-mobile-session-line">• <span id="posMobileSessionTimer" data-pos-session-timer data-start="{{ $activeSession->start_time->format('Y-m-d H:i:s') }}">00h 00m</span></div>
            </div>
            <button type="button" class="progga-btn progga-btn-danger pos-mobile-menu-action w-100 js-end-pos-session" data-session-id="{{ $activeSession->id }}">
                <i class="bi bi-stop-circle-fill"></i> End Session
            </button>
        @else
            <div class="pos-mobile-session-card no-session" title="Start a POS session before taking orders">
                <div class="pos-mobile-session-title">
                    <span class="progga-session-dot"></span>
                    <span>No Active Session</span>
                </div>
            </div>
            <button type="button" class="progga-btn progga-btn-primary pos-mobile-menu-action w-100 js-start-pos-session">
                <i class="bi bi-play-circle-fill"></i> Start Session
            </button>
        @endif

        @if(!auth()->user()->hasRole('waiter'))
            <button type="button" class="progga-btn progga-btn-secondary pos-mobile-menu-action w-100 js-mobile-session-history">
                <i class="bi bi-history"></i> Session History
            </button>
        @endif

        <a href="{{ route('home') }}" class="progga-btn progga-btn-secondary pos-mobile-menu-action w-100 text-decoration-none">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>

        <form action="{{ route('logout') }}" method="POST" class="m-0" data-pos-logout="1">
            @csrf
            <button type="submit" class="progga-btn progga-btn-danger pos-mobile-menu-action w-100">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </form>
    </div>
</div>

@include('admin.pos.modals.new_order')
@include('admin.pos.modals.addon')
@include('admin.pos.modals.payment')
@include('admin.pos.partials.offcanvas_wrapper')
@include('admin.pos.partials.print_preview_modal')

<div class="modal fade progga-modal" id="sessionHistoryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="sessionModalTitle">
                    <i class="bi bi-table me-2"></i>Work Period Sessions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" style="max-height: 500px; overflow-y: auto;">

                <div id="sessionListView">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle text-center" style="font-size: 13px;">
                            <thead class="table-dark">
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
                            <tbody>
                                @forelse($sessions ?? [] as $key => $sess)
                                    <tr>
                                        <td><strong>#{{ $key+1 }}</strong></td>
                                        <td>{{ $sess->user->name ?? 'N/A' }}</td>
                                        <td><span class="badge bg-secondary">{{ $sess->weekday }}</span></td>
                                        <td>{{ $sess->start_time->format('d M y - h:i A') }}</td>
                                        <td>{{ $sess->end_time ? $sess->end_time->format('d M y - h:i A') : '—' }}</td>
                                        <td>{{ $sess->duration ?? 'Running' }}</td>
                                        <td><strong>৳{{ round($sess->report_grand_total ?? $sess->grand_total) }}</strong></td>
                                        <td>
                                            <span class="badge {{ $sess->status == 'Open' ? 'bg-success' : 'bg-danger' }}">
                                                {{ $sess->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 justify-content-center">
                                                <button type="button"
                                                    class="btn btn-sm btn-primary btnEditSession"
                                                    data-id="{{ $sess->id }}"
                                                    data-start="{{ $sess->start_time ? $sess->start_time->format('Y-m-d\TH:i') : '' }}"
                                                    data-end="{{ $sess->end_time ? $sess->end_time->format('Y-m-d\TH:i') : '' }}"
                                                    data-status="{{ $sess->status }}">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                @if($sess->status == 'Closed')
                                                    <a href="{{ route('pos.session.report', $sess->id) }}" target="_blank" class="btn btn-sm btn-warning fw-bold">
                                                        <i class="bi bi-printer"></i> Print
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-muted py-4">No sessions found!</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="sessionEditView" style="display: none;">
                    <form id="inlineEditSessionForm">
                        <input type="hidden" id="inlineEditSessionId" name="session_id">

                        <div class="row mt-2">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold" style="font-size: 13px;">Start Time</label>
                                <input type="datetime-local" id="inlineEditStartTime" name="start_time" class="form-control" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold" style="font-size: 13px;">End Time</label>
                                <input type="datetime-local" id="inlineEditEndTime" name="end_time" class="form-control">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold" style="font-size: 13px;">Status</label>
                                <select id="inlineEditStatus" name="status" class="form-control">
                                    <option value="Open">Open</option>
                                    <option value="Closed">Closed</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-3 border-top pt-3">
                            <button type="button" class="btn btn-secondary fw-bold" id="btnCancelEdit">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </button>
                            <button type="submit" class="btn btn-primary fw-bold">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

@endif
@endsection

@section('script')
@if($isPosOrderTimeOpen ?? true)

<script>
    @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: @json(session('success')), timer: 1800, showConfirmButton: false });
    @endif
    @if(session('error'))
        Swal.fire({ icon: 'error', title: 'Error', text: @json(session('error')) });
    @endif

(function bootPosScriptWhenJqueryReady() {
    if (!window.jQuery) {
        setTimeout(bootPosScriptWhenJqueryReady, 50);
        return;
    }


    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const serverOpenPosSessionId = @json($activeSession ? $activeSession->id : null);
    const serverOpenPosSessionStart = @json($activeSession && $activeSession->start_time ? $activeSession->start_time->format('Y-m-d H:i:s') : null);
    const forceUnfinishedSessionPrompt = @json((bool) ($forceUnfinishedSessionPrompt ?? false));
    const posSessionAckStorageKey = 'pos_acknowledged_session_id';
    const posSessionStatusUrl = @json(route('pos.session.status'));
    const posSessionStatusPollMs = 10000;

    function getAcknowledgedPosSessionId() {
        try {
            return window.sessionStorage.getItem(posSessionAckStorageKey);
        } catch (ignore) {
            return null;
        }
    }

    function acknowledgePosSession(sessionId) {
        if (!sessionId) return;
        try {
            window.sessionStorage.setItem(posSessionAckStorageKey, String(sessionId));
        } catch (ignore) {}
    }

    function clearAcknowledgedPosSession() {
        try {
            window.sessionStorage.removeItem(posSessionAckStorageKey);
        } catch (ignore) {}
    }

    // A server-known Manager-owned work period is shared across users/browsers.
    // Do not require this browser to acknowledge/start the same session again.
    let hasActivePosSession = !!serverOpenPosSessionId && !forceUnfinishedSessionPrompt;
    let knownPosSessionId = serverOpenPosSessionId ? String(serverOpenPosSessionId) : null;
    let posSessionStatusRequestInFlight = false;
    let posSessionStatusReloading = false;

    if (hasActivePosSession) {
        acknowledgePosSession(serverOpenPosSessionId);
    }

    function reloadPosForSharedSessionChange(title, message, icon, delay) {
        if (posSessionStatusReloading) return;
        posSessionStatusReloading = true;

        if (!window.Swal) {
            window.location.reload();
            return;
        }

        window.Swal.fire({
            icon: icon || 'info',
            title: title,
            text: message,
            timer: delay || 1400,
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(function() {
            window.location.reload();
        });
    }

    function refreshSharedPosSessionStatus() {
        if (!posSessionStatusUrl || posSessionStatusRequestInFlight || posSessionStatusReloading) {
            return;
        }

        posSessionStatusRequestInFlight = true;

        $.ajax({
            url: posSessionStatusUrl,
            method: 'GET',
            dataType: 'json',
            cache: false
        }).done(function(res) {
            if (!res || res.status !== 'success') return;

            const nextSessionId = res.active && res.session_id ? String(res.session_id) : null;
            const previousSessionId = knownPosSessionId;

            if (nextSessionId === previousSessionId) {
                hasActivePosSession = !!nextSessionId;
                return;
            }

            knownPosSessionId = nextSessionId;
            hasActivePosSession = !!nextSessionId;

            if (nextSessionId) {
                acknowledgePosSession(nextSessionId);
            } else {
                clearAcknowledgedPosSession();
            }

            if (previousSessionId && !nextSessionId) {
                reloadPosForSharedSessionChange(
                    'POS Session Ended',
                    'The shared POS session was ended from another browser. POS will refresh now.',
                    'warning',
                    1600
                );
                return;
            }

            if (!previousSessionId && nextSessionId) {
                reloadPosForSharedSessionChange(
                    'POS Session Started',
                    'A shared POS session was started from another browser. POS will refresh now.',
                    'success',
                    1200
                );
                return;
            }

            reloadPosForSharedSessionChange(
                'POS Session Changed',
                'The shared POS work period changed in another browser. POS will refresh now.',
                'info',
                1200
            );
        }).always(function() {
            posSessionStatusRequestInFlight = false;
        });
    }

    // Keep every logged-in POS browser synchronized with the shared Manager session.
    // First check happens quickly after load, then every 10 seconds.
    setTimeout(refreshSharedPosSessionStatus, 2500);
    setInterval(refreshSharedPosSessionStatus, posSessionStatusPollMs);

    function formatUnfinishedSessionStart(startText) {
        if (!startText) return '';
        const parsed = new Date(String(startText).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return startText;
        return parsed.toLocaleString([], {
            year: 'numeric', month: 'short', day: '2-digit',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function showUnfinishedPosSessionPrompt(sessionInfo, tableIdAfterStart) {
        sessionInfo = sessionInfo || {};
        const sessionId = sessionInfo.session_id || serverOpenPosSessionId;
        const startText = sessionInfo.start_time || serverOpenPosSessionStart;
        const readableStart = formatUnfinishedSessionStart(startText);

        return window.Swal.fire({
            icon: 'warning',
            title: 'Unfinished POS Session',
            html: 'A previous POS session is still open.'
                + (readableStart ? '<br><strong>Started:</strong> ' + readableStart : '')
                + '<br><br>Do you want to continue it or start a new session?',
            showCancelButton: false,
            showDenyButton: true,
            confirmButtonText: '<i class="bi bi-arrow-repeat"></i> Continue Previous Session',
            denyButtonText: '<i class="bi bi-play-circle-fill"></i> Start New Session',
            allowOutsideClick: false,
            allowEscapeKey: false,
            reverseButtons: false
        }).then(function(result) {
            if (result.isConfirmed) {
                requestPosSessionStart({ action: 'continue', tableId: tableIdAfterStart || null });
            } else if (result.isDenied) {
                requestPosSessionStart({ action: 'new', tableId: tableIdAfterStart || null });
            }
        });
    }

    function requestPosSessionStart(options) {
        options = options || {};
        const tableIdAfterStart = options.tableId || null;
        const action = options.action || 'start';
        const $buttons = $('.js-start-pos-session');
        const originalHtml = $buttons.first().html();

        $buttons.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Starting...');

        $.post(@json(route('pos.session.start')), { action: action })
            .done(function(res) {
                if (res && res.status === 'unfinished') {
                    $buttons.prop('disabled', false).html(originalHtml);
                    showUnfinishedPosSessionPrompt(res, tableIdAfterStart);
                    return;
                }

                if (!res || res.status !== 'success') {
                    window.Swal.fire('Error', (res && res.message) || 'Could not start the POS session.', 'error');
                    $buttons.prop('disabled', false).html(originalHtml);
                    return;
                }

                acknowledgePosSession(res.session_id);
                knownPosSessionId = res.session_id ? String(res.session_id) : knownPosSessionId;
                hasActivePosSession = true;

                if (tableIdAfterStart) {
                    try {
                        window.sessionStorage.setItem('pos_auto_open_table_after_session_start', String(tableIdAfterStart));
                    } catch (ignore) {}
                }

                window.Swal.fire({
                    icon: 'success',
                    title: res.already_active ? 'Session Continued' : 'Session Started',
                    text: res.message || 'POS session is ready.',
                    timer: 900,
                    showConfirmButton: false
                }).then(function() {
                    window.location.reload();
                });
            })
            .fail(function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Could not start the POS session.';
                window.Swal.fire('Error', message, 'error');
                $buttons.prop('disabled', false).html(originalHtml);
            });
    }

    function promptToStartPosSession(tableId) {
        window.Swal.fire({
            icon: 'warning',
            title: 'Start POS Session First',
            text: 'Please start your POS session before taking an order.',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-play-circle-fill"></i> Start Session',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                requestPosSessionStart({ tableId: tableId || null });
            }
        });
    }

    $(document).on('click', '.js-start-pos-session', function(e) {
        e.preventDefault();
        requestPosSessionStart();
    });

    $(document).on('click', '.js-end-pos-session', function(e) {
        e.preventDefault();
        const sessionId = $(this).data('session-id');
        if (!sessionId) return;

        window.Swal.fire({
            icon: 'question',
            title: 'End POS Session?',
            text: 'The current work period will be closed now.',
            showCancelButton: true,
            confirmButtonText: 'Yes, End Session',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            $('.js-end-pos-session').prop('disabled', true);
            $.post(@json(route('pos.session.end')), { session_id: sessionId })
                .done(function(res) {
                    if (res && res.status === 'success') {
                        clearAcknowledgedPosSession();
                        knownPosSessionId = null;
                        hasActivePosSession = false;
                        window.Swal.fire({
                            icon: 'success',
                            title: 'Session Ended',
                            text: res.message || 'POS session ended successfully.',
                            timer: 900,
                            showConfirmButton: false
                        }).then(function() {
                            window.location.reload();
                        });
                        return;
                    }

                    $('.js-end-pos-session').prop('disabled', false);
                    window.Swal.fire('Error', (res && res.message) || 'Could not end the POS session.', 'error');
                })
                .fail(function(xhr) {
                    $('.js-end-pos-session').prop('disabled', false);
                    const response = xhr.responseJSON || {};
                    const message = response.message || 'Could not end the POS session.';

                    if (response.code === 'session_close_blocked') {
                        window.Swal.fire({
                            icon: 'warning',
                            title: 'Cannot End Session',
                            text: message,
                            confirmButtonText: 'OK'
                        });
                        return;
                    }

                    window.Swal.fire('Error', message, 'error');
                });
        });
    });

    // Takeaway/Delivery order creation also requires an explicit POS session.
    $(document).on('click', '#modeTakeaway, #modeDelivery', function(e) {
        if (hasActivePosSession) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        promptToStartPosSession(null);
    });

    let currentOrder = {
        order_type: 'dine_in', table_id: null, table_name: '',
        waiter_id: null, waiter_name: '', customer_id: null, customer_name: '',
        customer_phone: '', is_walk_in: 1, order_notes: '', delivery_partner: '', delivery_partner_name: '', is_complimentary_order: 0,
        table_booking_id: null
    };
    window.currentOrder = currentOrder;
    let currentCat = '';
    let isComplimentaryMode = false;
    let posComplimentaryActionPassword = '';
    let posComplimentaryNote = '';
    let isOffcanvasComplimentaryMode = false;
    let isWaiter = @json(auth()->user()->hasRole('waiter'));
    const complimentaryNoteRequired = @json((bool) ($posSetting->complimentary_note_required ?? false));
    const dineInWaiterRequired = @json((bool) ($posSetting->dine_in_waiter_required ?? false));

    function updatePosSessionTimer() {
        var timers = document.querySelectorAll('[data-pos-session-timer]');
        if (!timers.length) return;

        timers.forEach(function(timer) {
            var startText = timer.getAttribute('data-start');
            if (!startText) return;

            var startTime = new Date(String(startText).replace(' ', 'T'));
            var now = new Date();
            var diffMs = now - startTime;
            if (diffMs < 0) diffMs = 0;

            var totalMinutes = Math.floor(diffMs / 60000);
            var hours = Math.floor(totalMinutes / 60);
            var minutes = totalMinutes % 60;

            timer.textContent = String(hours).padStart(2, '0') + 'h ' + String(minutes).padStart(2, '0') + 'm';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updatePosSessionTimer);
    } else {
        updatePosSessionTimer();
    }
    setInterval(updatePosSessionTimer, 60000);

    $(document).ready(function() {
        if (serverOpenPosSessionId && !hasActivePosSession) {
            setTimeout(function() {
                showUnfinishedPosSessionPrompt({
                    session_id: serverOpenPosSessionId,
                    start_time: serverOpenPosSessionStart
                }, null);
            }, 120);
        }
        @if(empty($selectedTableId))
        if (hasActivePosSession) {
            let autoOpenTableId = null;
            try {
                autoOpenTableId = window.sessionStorage.getItem('pos_auto_open_table_after_session_start');
                if (autoOpenTableId) {
                    window.sessionStorage.removeItem('pos_auto_open_table_after_session_start');
                }
            } catch (ignore) {}

            if (autoOpenTableId) {
                setTimeout(function() {
                    const $tableCard = $('.progga-pos-table-card[data-table-id="' + autoOpenTableId + '"]');
                    if ($tableCard.length) {
                        $tableCard.first().trigger('click');
                    }
                }, 350);
            }
        }
        @endif
        $(document).on('click', '.js-mobile-session-history', function() {
            var offcanvasEl = document.getElementById('posMobileHeaderMenu');
            var modalEl = document.getElementById('sessionHistoryModal');
            if (!modalEl) return;

            var showSessionModal = function() {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            };

            if (offcanvasEl && offcanvasEl.classList.contains('show')) {
                offcanvasEl.addEventListener('hidden.bs.offcanvas', showSessionModal, { once: true });
                bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).hide();
            } else {
                showSessionModal();
            }
        });

        // POS workflow note.
        if(isWaiter) {
            $('#btnSendToKitchen').html('<i class="bi bi-send"></i> Send to Front Desk');
        }

        loadFoods('');
        refreshLiveTableReservationStatuses();

        // Open POS order modal automatically when coming from Table Booking,
        // but only after the user explicitly starts/resumes a POS session.
        @if(!empty($selectedTableId))
        if (hasActivePosSession) {
            openDineInTableModal(
                {{ $selectedTableId }},
                @json($selectedTable->table_number ?? 'Table'),
                @json(request()->get('customer_id')),
                @json($selectedBookingId)
            );
        } else {
            promptToStartPosSession({{ $selectedTableId }});
        }
        @endif
    });

    function getCartParams() {
        return {
            order_id: currentOrder.order_id || null,
            order_type: currentOrder.order_type,
            table_id: currentOrder.table_id
        };
    }

    function setPosTableStatus(tableId, status) {
        if (!tableId) return;

        const normalized = String(status || 'available').toLowerCase();
        const label = normalized.charAt(0).toUpperCase() + normalized.slice(1);
        const $card = $('.progga-pos-table-card[data-table-id="' + tableId + '"]');

        if ($card.length) {
            if (normalized !== 'occupied') {
                $card.removeClass('bill-printed')
                    .attr('data-bill-printed', '0')
                    .data('bill-printed', 0);
            }

            $card.removeClass('available occupied reserved')
                .addClass(normalized)
                .attr('data-status', normalized)
                .data('status', normalized);

            const isBillPrinted = normalized === 'occupied'
                && String($card.attr('data-bill-printed') || '0') === '1';

            $card.find('.progga-badge')
                .removeClass('progga-status-available progga-status-occupied progga-status-reserved progga-status-bill-printed')
                .addClass(isBillPrinted ? 'progga-status-bill-printed' : ('progga-status-' + normalized))
                .text(isBillPrinted ? 'Bill Printed' : label);
        }

        const $option = $('#modalTableSelect option[value="' + tableId + '"]');
        if ($option.length) {
            let baseText = $option.data('table-name') || $option.text();
            baseText = String(baseText).replace(/\s+—\s+(Occupied|Reserved|Available)$/i, '');
            $option.attr('data-status', normalized).data('status', normalized);

            if (normalized === 'available') {
                $option.prop('disabled', false).text(baseText);
            } else {
                $option.prop('disabled', true).text(baseText + ' — ' + label);
            }
        }

        const activeFilter = $('.progga-pos-filter-btn.active').data('table-filter');
        if (activeFilter && activeFilter !== 'all') {
            $('.progga-pos-table-card').hide();
            $('.progga-pos-table-card[data-status="' + activeFilter + '"]').show();
        }
    }


    function setPosTableBillPrinted(tableId, isPrinted) {
        if (!tableId) return;

        const $card = $('.progga-pos-table-card[data-table-id="' + tableId + '"]');
        if (!$card.length) return;

        const currentlyOccupied = String($card.attr('data-status') || '').toLowerCase() === 'occupied';
        const printed = Boolean(isPrinted) && currentlyOccupied;

        $card.toggleClass('bill-printed', printed)
            .attr('data-bill-printed', printed ? '1' : '0')
            .data('bill-printed', printed ? 1 : 0);

        const $badge = $card.find('.progga-badge');
        $badge.removeClass('progga-status-bill-printed progga-status-occupied');

        if (printed) {
            $badge.addClass('progga-status-bill-printed').text('Bill Printed');
        } else if (currentlyOccupied) {
            $badge.addClass('progga-status-occupied').text('Occupied');
        }
    }

    function refreshLiveTableReservationStatuses() {
        $.get("{{ route('pos.table_reservation_statuses') }}")
            .done(function(response) {
                if (!response || response.status !== 'success' || !Array.isArray(response.tables)) return;

                response.tables.forEach(function(row) {
                    const tableId = String(row.table_id || '');
                    if (!tableId) return;

                    const $card = $('.progga-pos-table-card[data-table-id="' + tableId + '"]');
                    if (!$card.length) return;

                    // Do not interrupt an order currently being opened/edited in the browser.
                    if ($card.data('opening-order') === true) return;

                    setPosTableStatus(tableId, row.status || 'available');
                    setPosTableBillPrinted(tableId, Boolean(row.bill_printed));

                    if (String(row.status || '').toLowerCase() === 'reserved') {
                        $card.attr('data-reserved-booking-id', row.booking_id || '');
                        $card.attr('data-reserved-customer-id', row.customer_id || '');
                    } else {
                        $card.attr('data-reserved-booking-id', '');
                        $card.attr('data-reserved-customer-id', '');
                    }
                });
            });
    }

    // Server time is authoritative for reservation windows. Polling once per minute
    // makes 3:00 PM -> Reserved and 5:00 PM/payment -> Available happen without reload.
    setInterval(refreshLiveTableReservationStatuses, 60000);


    let newOrderModalMode = 'all';
    // POS workflow note.
    // POS workflow note.
    let newOrderStartedFromModal = false;

    function resetNewOrderModalCommon() {
        $('#posWalkIn').prop('checked', true).trigger('change');
        $('#order_notes').val('');
        $('#posDeliveryPartnerSelect').val('');
        $('#deliveryPartnerSection').hide();
        currentOrder.delivery_partner = '';
        currentOrder.delivery_partner_name = '';
        $('#posComplimentaryOrder').prop('checked', false);
        currentOrder.is_complimentary_order = 0;
        isComplimentaryMode = false;
        posComplimentaryActionPassword = '';
        posComplimentaryNote = '';
        isOffcanvasComplimentaryMode = false;
        $('#newCustomerForm').hide();
        $('#customerSearchContainer').show();
        $('#new_cus_name, #new_cus_phone').val('');
        $('#showNewCustomerFormBtn').text('+ Add New Customer').removeClass('progga-btn-danger').addClass('progga-btn-primary');
    }

    function openAllTypeOrderModal() {
        newOrderModalMode = 'all';
        currentOrder.table_id = null;
        currentOrder.table_name = '';
        currentOrder.order_type = 'dine_in';
        currentOrder.order_id = null;
        currentOrder.table_booking_id = null;

        $('#labelDineIn, #labelTakeaway, #labelDelivery').show();
        $('#posTypeDineIn, #posTypeTakeaway, #posTypeDelivery').prop('disabled', false);
        $('#posTypeDineIn').prop('checked', true);
        $('#posTypeTakeaway, #posTypeDelivery').prop('checked', false);
        $('#modalTableSelect').prop('selectedIndex', 0).val('').removeClass('is-invalid').trigger('change');
        $('#modalSelectedTableNum').text('T-00');
        $('#modalTableSelectSection').show();
        $('#modalTableDisplaySection').hide();
        resetNewOrderModalCommon();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newOrderModal')).show();
    }

    function resetNewOrderModalAfterClose() {
        newOrderModalMode = 'all';
        currentOrder.table_id = null;
        currentOrder.table_name = '';
        currentOrder.order_type = 'dine_in';
        currentOrder.order_id = null;
        currentOrder.table_booking_id = null;

        $('#labelDineIn, #labelTakeaway, #labelDelivery').show();
        $('#posTypeDineIn, #posTypeTakeaway, #posTypeDelivery').prop('disabled', false);
        $('#posTypeDineIn').prop('checked', true);
        $('#posTypeTakeaway, #posTypeDelivery').prop('checked', false);

        // POS workflow note.
        $('#modalTableSelect').prop('selectedIndex', 0).val('').removeClass('is-invalid').trigger('change');
        $('#modalSelectedTableNum').text('T-00');
        $('#modalTableSelectSection').show();
        $('#modalTableDisplaySection').hide();

        resetNewOrderModalCommon();
    }

    $('#newOrderModal').on('hidden.bs.modal', function () {
        // POS workflow note.
        if (newOrderStartedFromModal) {
            newOrderStartedFromModal = false;
            return;
        }

        resetNewOrderModalAfterClose();
    });

    function openDineInTableModal(tableId, tableName, customerId = null, bookingId = null) {
        newOrderModalMode = 'table';
        currentOrder.table_id = tableId;
        currentOrder.table_name = tableName;
        currentOrder.order_type = 'dine_in';
        currentOrder.order_id = null;
        currentOrder.table_booking_id = bookingId || null;
        currentOrder.customer_id = customerId || null;
        currentOrder.is_walk_in = customerId ? 0 : 1;

        $('#labelDineIn').show();
        $('#labelTakeaway, #labelDelivery').hide();
        $('#posTypeDineIn').prop('disabled', false).prop('checked', true);
        $('#posTypeTakeaway, #posTypeDelivery').prop('disabled', true);
        $('#modalTableSelect').val(tableId);
        $('#modalTableSelectSection').hide();
        $('#modalSelectedTableNum').text(tableName);
        $('#modalTableDisplaySection').slideDown();
        resetNewOrderModalCommon();

        if (customerId) {
            $('#posWalkIn').prop('checked', false).trigger('change');
            $('#posCustomerSelect').val(customerId).trigger('change');
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('newOrderModal')).show();
    }

    function showStep(step) {
        $('.progga-pos-screen').removeClass('active');
        $('#posStep' + step).addClass('active');
        $('.progga-pos-step').removeClass('active');
        $('#indStep' + step).addClass('active');

        if(step === 2) {
            let tableMetaHtml = currentOrder.table_name;
            if(currentOrder.order_type === 'takeaway') tableMetaHtml = '<span class="text-danger">Takeaway</span>';
            let deliveryPartnerText = '';
            if(currentOrder.order_type === 'delivery') {
                deliveryPartnerText = currentOrder.delivery_partner_name || $('#posDeliveryPartnerSelect option[value="' + (currentOrder.delivery_partner || '') + '"]').text();
                if(!deliveryPartnerText || deliveryPartnerText.indexOf('Select Delivery Partner') !== -1) deliveryPartnerText = 'Not selected';
                currentOrder.delivery_partner_name = deliveryPartnerText;
                tableMetaHtml = '<span class="text-warning">Delivery - ' + $('<div>').text(deliveryPartnerText).html() + '</span>';
            }
            $('#posSelectedTableMeta').html(tableMetaHtml);

            let typeText = 'Dine-In';
            if(currentOrder.order_type === 'takeaway') typeText = 'Takeaway';
            if(currentOrder.order_type === 'delivery') typeText = 'Delivery';
            $('#metaType').text(typeText);

            if(currentOrder.order_type === 'delivery') {
                $('#metaDeliveryPartnerName').text(deliveryPartnerText || currentOrder.delivery_partner_name || 'Not selected');
                $('#metaDeliveryPartner').show();
            } else {
                $('#metaDeliveryPartnerName').text('—');
                $('#metaDeliveryPartner').hide();
            }

            let customerText = currentOrder.is_walk_in === 1 ? 'Walk-in Customer' : (currentOrder.customer_name ? currentOrder.customer_name : 'Registered Customer');
            $('#metaCustomer').text(customerText);

            let waiterText = currentOrder.waiter_id ? currentOrder.waiter_name : 'Unassigned';
            $('#metaWaiter').html('<i class="bi bi-person-badge"></i> ' + waiterText);

            if(currentOrder.is_complimentary_order === 1) {
                $('#posSelectedTableMeta').append(' <span class="badge bg-success ms-1" style="font-size:10px;">Complimentary</span>');
            }

            loadFoods('');
            loadCart();
        }
    }

    $('#posBackToTables').click(function() { showStep(1); });


    function cleanupActiveOrderMetaModal() {
        const modalEl = document.getElementById('activeOrderMetaModal');
        if (!modalEl) return;

        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) {
            modalInstance.dispose();
        }

        $(modalEl).find('.js-oc-select2').each(function() {
            const $select = $(this);
            if ($select.hasClass('select2-hidden-accessible') && $.fn.select2) {
                $select.select2('destroy');
            }
        });

        const wasShown = $(modalEl).hasClass('show');
        $(modalEl).remove();
        $('.select2-container--open').remove();

        if (wasShown && !document.querySelector('.modal.show')) {
            $('body').removeClass('modal-open').css('padding-right', '');
        }
    }

    function mountActiveOrderMetaModal() {
        const $modal = $('#ocBody').find('#activeOrderMetaModal');
        if (!$modal.length) return;

        $modal.appendTo(document.body);

        if ($.fn.select2) {
            $modal.find('.js-oc-select2').each(function() {
                const $select = $(this);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }

                const searchEnabled = String($select.attr('data-search') || 'true') !== 'false';
                $select.select2({
                    width: '100%',
                    dropdownParent: $modal,
                    allowClear: false,
                    placeholder: $select.attr('data-placeholder') || undefined,
                    minimumResultsForSearch: searchEnabled ? 0 : Infinity
                });
            });
        }
    }

    function reloadActiveOrderOffcanvas(orderId, tableId, orderType) {
        const normalizedType = String(orderType || '')
            .toLowerCase()
            .replace(/[\s-]+/g, '_');
        const isDineIn = normalizedType === 'dine_in' || normalizedType === 'dinein';
        const url = isDineIn && tableId
            ? "{{ route('pos.get_table_order', ':id') }}".replace(':id', tableId)
            : "{{ route('pos.get_pos_order', ':id') }}".replace(':id', orderId);

        return $.get(url, function(res) {
            if (typeof res === 'object' && res !== null) {
                if (res.status === 'load_cart') {
                    bootstrap.Offcanvas.getInstance(document.getElementById('tableOrderOffcanvas'))?.hide();
                    window.loadHeldQrOrderToPos(res.order_data);
                    return;
                }

                if (res.status === 'error') {
                    Swal.fire('Notice', res.message || 'Could not reload the active order.', 'info');
                    return;
                }
            }

            cleanupActiveOrderMetaModal();
            $('#ocBody').html(res);
            mountActiveOrderMetaModal();
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('tableOrderOffcanvas')).show();
        });
    }

    $(document).on('click', '.progga-pos-table-card', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const $card = $(this);
        const tId = $card.attr('data-table-id');
        const tNum = $card.attr('data-table-num');
        const reservedCustomerId = $card.attr('data-reserved-customer-id') || null;
        const reservedBookingId = $card.attr('data-reserved-booking-id') || null;

        if(!tId || $card.data('opening-order') === true) {
            return;
        }

        if (!hasActivePosSession) {
            promptToStartPosSession(tId);
            return;
        }

        $card.data('opening-order', true);

        // Always verify from server first.
        // After table swap, frontend card status can be stale for a moment;
        // server check prevents New Order modal and active order offcanvas from opening together.
        $.get("{{ route('pos.get_table_order', ':id') }}".replace(':id', tId), function(res) {
            $card.data('opening-order', false);

            if(typeof res === 'object' && res !== null) {
                if(res.status === 'load_cart') {
                    bootstrap.Modal.getInstance(document.getElementById('newOrderModal'))?.hide();
                    window.loadHeldQrOrderToPos(res.order_data);
                    return;
                }

                if(res.status === 'error') {
                    // Keep an active reservation visibly Reserved until an order is submitted.
                    // Non-reserved stale cards can safely fall back to Available.
                    if (!reservedBookingId) {
                        setPosTableStatus(tId, 'available');
                    }
                    openDineInTableModal(tId, tNum, reservedCustomerId, reservedBookingId);
                    return;
                }
            }

            // HTML response means active order exists, so only offcanvas should open.
            bootstrap.Modal.getInstance(document.getElementById('newOrderModal'))?.hide();
            setPosTableStatus(tId, 'occupied');
            cleanupActiveOrderMetaModal();
            $('#ocBody').html(res);
            mountActiveOrderMetaModal();
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('tableOrderOffcanvas')).show();
        }).fail(function() {
            $card.data('opening-order', false);

            const uiStatus = String($card.attr('data-status') || '').toLowerCase();
            if(uiStatus === 'occupied' || $card.hasClass('occupied')) {
                Swal.fire('Error', 'Could not load active table order. Please try again.', 'error');
                return;
            }

            openDineInTableModal(tId, tNum, reservedCustomerId, reservedBookingId);
        });
    });

   $('#modeTakeaway').click(function() {
        openAllTypeOrderModal();
    });

    $('#modeTakeawayDeliveryList').click(function() {
        $('#posTableGrid').hide();
        $('#posTableSection').hide();
        $('#posTakeawayDeliveryPanel').fadeIn('fast');
    });

    $('#btnCompletePendingTakeawayDelivery').click(function() {
        const $btn = $(this);
        const originalHtml = $btn.html();
        const pendingCards = $('.progga-pos-running-order-card').filter(function() {
            return String($(this).attr('data-order-status') || '').toLowerCase() === 'pending';
        });

        if(pendingCards.length < 1) {
            Swal.fire('No Pending Order', 'No pending Takeaway / Delivery order found.', 'info');
            return;
        }

        Swal.fire({
            title: 'Complete pending payments?',
            text: pendingCards.length + ' pending Takeaway / Delivery order(s) will be marked as paid and completed.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, complete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if(!result.isConfirmed) return;

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

            $.post("{{ route('pos.takeaway_delivery.complete_pending') }}", {}, function(res) {
                if(res.status === 'success') {
                    const completedIds = (res.completed_ids || []).map(function(id) { return String(id); });

                    completedIds.forEach(function(id) {
                        const $card = $('.progga-pos-running-order-card[data-order-id="' + id + '"]');
                        $card.attr('data-order-status', 'completed').addClass('completed');
                        $card.find('.js-td-order-status-badge')
                             .removeClass('progga-status-occupied')
                             .addClass('progga-status-available')
                             .text('Completed');
                    });

                    const remainingPending = $('.progga-pos-running-order-card').filter(function() {
                        return String($(this).attr('data-order-status') || '').toLowerCase() === 'pending';
                    }).length;

                    $btn.html('<i class="bi bi-check2-circle"></i> Complete Pending Payment (' + remainingPending + ')');
                    Swal.fire('Done', res.message || 'Pending payments completed successfully.', 'success');
                } else {
                    $btn.html(originalHtml);
                    Swal.fire('Error', res.message || 'Payment completion failed.', 'error');
                }
            }).fail(function(xhr) {
                $btn.html(originalHtml);
                Swal.fire('Error', xhr.responseJSON?.message || 'Payment completion failed.', 'error');
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });
    });

    $('#btnBackToTablesFromTdOrders').click(function() {
        $('#posTakeawayDeliveryPanel').hide();
        $('#posTableSection').show();
        $('#posTableGrid').fadeIn('fast');
    });

    $(document).on('click', '.progga-pos-running-order-card', function() {
        if(String($(this).attr('data-order-status') || '').toLowerCase() === 'completed') {
            Swal.fire('Completed', 'This Takeaway / Delivery order is already completed.', 'success');
            return;
        }

        const orderId = $(this).data('order-id');

        $.get("{{ route('pos.get_pos_order', ':id') }}".replace(':id', orderId), function(res) {
            if(res.status === 'load_cart') {
                window.loadHeldQrOrderToPos(res.order_data);
            } else if(res.status === 'error') {
                Swal.fire('Notice', res.message, 'info');
            } else {
                cleanupActiveOrderMetaModal();
                $('#ocBody').html(res);
                mountActiveOrderMetaModal();
                bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('tableOrderOffcanvas')).show();
            }
        });
    });

   $('input[name="orderType"]').on('change', function() {
        let type = $(this).val();
        currentOrder.order_type = type;
        currentOrder.order_id = null;

        if(type === 'takeaway' || type === 'delivery') {
            $('#modalTableSelectSection').slideUp();
            $('#modalTableDisplaySection').slideUp();
            $('#modalTableSelect').prop('selectedIndex', 0).val('').removeClass('is-invalid').trigger('change');
            currentOrder.table_id = null;
            currentOrder.table_name = type === 'takeaway' ? 'Takeaway' : 'Delivery';

            if(type === 'delivery') {
                $('#deliveryPartnerSection').slideDown();
            } else {
                $('#deliveryPartnerSection').slideUp();
                $('#posDeliveryPartnerSelect').val('');
                currentOrder.delivery_partner = '';
            }
        } else {
            $('#deliveryPartnerSection').slideUp();
            $('#posDeliveryPartnerSelect').val('');
            currentOrder.delivery_partner = '';
            if(newOrderModalMode === 'table') {
                $('#modalTableSelectSection').hide();
                $('#modalSelectedTableNum').text(currentOrder.table_name);
                $('#modalTableDisplaySection').slideDown();
            } else {
                $('#modalTableDisplaySection').hide();
                $('#modalTableSelectSection').slideDown();
                let selectedOption = $('#modalTableSelect option:selected');
                currentOrder.table_id = $('#modalTableSelect').val() || null;
                currentOrder.table_name = currentOrder.table_id ? (selectedOption.data('table-name') || selectedOption.text()) : '';
            }
        }
    });

    $('#modalTableSelect').on('change', function() {
        let selectedOption = $(this).find('option:selected');
        $(this).removeClass('is-invalid');
        currentOrder.table_id = $(this).val() || null;
        currentOrder.table_name = currentOrder.table_id ? (selectedOption.data('table-name') || selectedOption.text()) : '';
        if(currentOrder.table_id) {
            $('#modalSelectedTableNum').text(currentOrder.table_name);
        }
    });

    $('#posWalkIn').on('change', function() {
        if($(this).is(':checked')) { $('#posCustomerFields').slideUp(); }
        else { $('#posCustomerFields').slideDown(); }
    });

    $('#showNewCustomerFormBtn').on('click', function() {
        let isNewFormVisible = $('#newCustomerForm').is(':hidden');
        if(isNewFormVisible) {
            $('#newCustomerForm').slideDown();
            $('#customerSearchContainer').hide();
            $(this).text('- Cancel New Customer').removeClass('progga-btn progga-btn-primary').addClass('progga-btn progga-btn-danger');
        } else {
            $('#newCustomerForm').slideUp();
            $('#customerSearchContainer').show();
            $(this).text('+ Add New Customer').removeClass('progga-btn progga-btn-danger').addClass('progga-btn progga-btn-primary');
            $('#new_cus_name').val('');
            $('#new_cus_phone').val('');
        }
    });

    $('#posComplimentaryOrder').on('change', function() {
        const $toggle = $(this);

        if (!$toggle.is(':checked')) {
            posComplimentaryActionPassword = '';
            posComplimentaryNote = '';
            isOffcanvasComplimentaryMode = false;
            return;
        }

        // Whole-order complimentary keeps the existing password-only flow.
        // The note rule is for single-food/offcanvas complimentary actions.
        posComplimentaryNote = '';
        isOffcanvasComplimentaryMode = false;
        $toggle.prop('checked', false);
        getPosActionPassword(function(pass) {
            posComplimentaryActionPassword = pass;
            $toggle.prop('checked', true);
        }, function() {
            posComplimentaryActionPassword = '';
            posComplimentaryNote = '';
            isOffcanvasComplimentaryMode = false;
            $toggle.prop('checked', false);
        });
    });

    function getValidDineInTableSelection() {
        // When the order modal was opened from a table card, that table is already the explicit selection.
        if(newOrderModalMode === 'table') {
            if(!currentOrder.table_id) return null;
            return {
                id: String(currentOrder.table_id),
                name: currentOrder.table_name || ''
            };
        }

        // For New Order -> Dine-In, always validate the table currently selected in the modal.
        // Never rely on a stale currentOrder.table_id from a previous order.
        const $tableSelect = $('#modalTableSelect');
        const tableId = String($tableSelect.val() || '').trim();
        const $selectedOption = $tableSelect.find('option:selected');
        const tableStatus = String($selectedOption.data('status') || '').trim().toLowerCase();

        if(
            !tableId ||
            !$selectedOption.length ||
            $selectedOption.prop('disabled') ||
            tableStatus === 'occupied' ||
            tableStatus === 'reserved'
        ) {
            return null;
        }

        return {
            id: tableId,
            name: $selectedOption.data('table-name') || $selectedOption.text().trim()
        };
    }

    $('#posStartOrderBtn').click(function(e) {
        // This is the only path that may move the New Order modal to the food screen.
        // Stop any other click handler/default action from bypassing the validation below.
        e.preventDefault();
        e.stopImmediatePropagation();

        let selectedOrderType = $('input[name="orderType"]:checked').val() || 'dine_in';
        currentOrder.order_type = selectedOrderType;

        if(selectedOrderType === 'dine_in') {
            const selectedTable = getValidDineInTableSelection();

            if(!selectedTable) {
                currentOrder.table_id = null;
                currentOrder.table_name = '';

                if(newOrderModalMode === 'all') {
                    $('#modalTableSelect').addClass('is-invalid').focus();
                }

                Swal.fire('Table Required', 'Please select an available table before starting a Dine-In order.', 'warning');
                return false;
            }

            currentOrder.table_id = selectedTable.id;
            currentOrder.table_name = selectedTable.name;
            $('#modalTableSelect').removeClass('is-invalid');
        } else {
            currentOrder.table_id = null;
            currentOrder.table_name = selectedOrderType === 'takeaway' ? 'Takeaway' : 'Delivery';
        }

        if(selectedOrderType === 'delivery') {
            currentOrder.delivery_partner = $('#posDeliveryPartnerSelect').val() || '';
            currentOrder.delivery_partner_name = $('#posDeliveryPartnerSelect option:selected').text().trim();
            if(!currentOrder.delivery_partner) {
                Swal.fire('Notice', 'Please select a delivery partner.', 'info');
                return;
            }
        } else {
            currentOrder.delivery_partner = '';
            currentOrder.delivery_partner_name = '';
        }

        const selectedWaiterId = $('#posWaiterSelect').val();
        if (selectedOrderType === 'dine_in' && dineInWaiterRequired && !selectedWaiterId) {
            $('#posWaiterSelect').addClass('is-invalid').focus();
            Swal.fire('Waiter Required', 'Please assign a waiter before starting a Dine-In order.', 'warning');
            return false;
        }
        $('#posWaiterSelect').removeClass('is-invalid');
        currentOrder.waiter_id = selectedWaiterId || null;
        currentOrder.waiter_name = currentOrder.waiter_id ? $('#posWaiterSelect option:selected').text() : 'Unassigned';
        currentOrder.is_walk_in = $('#posWalkIn').is(':checked') ? 1 : 0;
        currentOrder.order_notes = $('#order_notes').val() || '';
        currentOrder.is_complimentary_order = $('#posComplimentaryOrder').is(':checked') ? 1 : 0;
        isComplimentaryMode = currentOrder.is_complimentary_order === 1;

        if(currentOrder.is_walk_in === 0) {
            currentOrder.customer_id = $('#posCustomerSelect').val();
            let newName = $('#new_cus_name').val();
            if (newName) {
                currentOrder.customer_name = newName;
            } else if (currentOrder.customer_id) {
                currentOrder.customer_name = $('#posCustomerSelect option:selected').text().split(' - ')[0];
            } else {
                currentOrder.customer_name = 'Registered Customer';
            }
            currentOrder.customer_phone = $('#new_cus_phone').val();
        } else {
            currentOrder.customer_name = 'Walk-in Customer';
            currentOrder.customer_id = null;
        }

        newOrderStartedFromModal = true;
        bootstrap.Modal.getInstance(document.getElementById('newOrderModal')).hide();
        showStep(2);
    });

    function loadFoods(catId = '', search = '') {
        currentCat = catId;
        $.get("{{ route('pos.get_foods') }}", { category_id: catId, search: search }, function(res) {
            $('#posFoodGrid').html(res);
        });
    }

    $(document).on('click', '.progga-pos-cat-item', function() {
        $('.progga-pos-cat-item').removeClass('active');
        $(this).addClass('active');
        loadFoods($(this).data('cat-id'), $('#posFoodSearch').val());
    });
    $('#posFoodSearch').on('keyup', function() { loadFoods(currentCat, $(this).val()); });

    window.checkAddonAndCart = function(foodId, hasAddon) {
        if(hasAddon == 1) {
            $.get("{{ route('pos.get_addons', ':id') }}".replace(':id', foodId), function(res) {
                $('#addonModalFoodName').text(res.food.name);
                $('#addonModalFoodId').val(foodId);
                let addonHtml = '';
                res.food.addons.forEach(addon => {
                    addonHtml += `<label class="d-block border p-2 mb-2"><input type="checkbox" name="addons[]" value="${addon.id}"> ${addon.name} (+৳${addon.price})</label>`;
                });
                $('#addonListDiv').html(addonHtml);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('addonModal')).show();
            });
        } else {
            addToCart(foodId, []);
        }
    }

    $('#addonForm').on('submit', function(e) {
        e.preventDefault();
        let foodId = $('#addonModalFoodId').val();
        let addons = [];
        $('input[name="addons[]"]:checked').each(function() { addons.push($(this).val()); });
        addToCart(foodId, addons);
        bootstrap.Modal.getInstance(document.getElementById('addonModal')).hide();
    });

    function addToCart(foodId, addons) {
        // Complimentary Mode needs authorization only here. The complimentary Note is
        // entered product-wise from each cart row, not in the authorization popup.
        if (isComplimentaryMode && !posComplimentaryActionPassword) {
            getPosActionPassword(function(pass) {
                posComplimentaryActionPassword = pass;
                addToCart(foodId, addons);
            });
            return;
        }

        let payload = getCartParams();
        payload.food_id = foodId;
        payload.addons = addons;
        payload.qty = 1;
        payload.is_complimentary = isComplimentaryMode ? 1 : 0;
        if (isComplimentaryMode) {
            payload.action_password = posComplimentaryActionPassword;
        }

        $.post("{{ route('pos.cart.add') }}", payload, function(res) {
            if(res.status === 'success') loadCart();
        }).fail(function(xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'The restaurant is currently closed. Orders will be accepted from 12:01 PM.';
            window.Swal.fire('Error', message, 'warning');
        });
    }

    function loadCart(preserveScroll = false) {
        let $cartBody = $('#posCartBody');
        let $cartItems = $('#posCartItems');

        let cartBodyScrollTop = preserveScroll ? ($cartBody.scrollTop() || 0) : 0;
        let cartItemsScrollTop = preserveScroll ? ($cartItems.scrollTop() || 0) : 0;
        let windowScrollTop = preserveScroll ? ($(window).scrollTop() || 0) : 0;

        function restoreCartScrollPosition() {
            if(!preserveScroll) return;

            $('#posCartBody').scrollTop(cartBodyScrollTop);
            $('#posCartItems').scrollTop(cartItemsScrollTop);
            $(window).scrollTop(windowScrollTop);
        }

        $.get("{{ route('pos.cart.get') }}", getCartParams(), function(res) {
            $('#posCartBody').html(res);

            // POS workflow note.
            // POS workflow note.
            restoreCartScrollPosition();
            requestAnimationFrame(restoreCartScrollPosition);
            setTimeout(restoreCartScrollPosition, 0);
            setTimeout(restoreCartScrollPosition, 80);

            // POS workflow note.
            if(isWaiter) {
                $('#btnSendToKitchen').html('<i class="bi bi-send"></i> Send to Front Desk');
            } else {
                $('#btnSendToKitchen').html('<i class="bi bi-send"></i> Send to Kitchen');
            }
        });
    }

    window.loadHeldQrOrderToPos = function(data) {
        currentOrder.order_id = data.order_id || null;
        currentOrder.order_type = data.order_type || 'dine_in';
        currentOrder.table_id = data.table_id || null;
        currentOrder.table_name = data.table_number || (currentOrder.order_type === 'delivery' ? 'Delivery' : (currentOrder.order_type === 'takeaway' ? 'Takeaway' : ('Table ' + (data.table_id || ''))));
        currentOrder.waiter_id = data.waiter_id || null;
        currentOrder.waiter_name = data.waiter_name || 'Unassigned';
        currentOrder.customer_id = data.customer_id || null;
        currentOrder.customer_name = data.customer_name || 'Walk-in Customer';
        currentOrder.customer_phone = data.customer_phone || '';
        currentOrder.is_walk_in = parseInt(typeof data.is_walk_in !== 'undefined' ? data.is_walk_in : (data.customer_id ? 0 : 1));
        currentOrder.order_notes = data.notes || '';
        currentOrder.delivery_partner = data.delivery_partner || (currentOrder.order_type === 'delivery' ? 'inhouse' : '');
        currentOrder.delivery_partner_name = data.delivery_partner_name || '';
        if(currentOrder.order_type === 'delivery') {
            $('#posDeliveryPartnerSelect').val(currentOrder.delivery_partner);
            if(!currentOrder.delivery_partner_name) {
                currentOrder.delivery_partner_name = $('#posDeliveryPartnerSelect option:selected').text().trim();
            }
        } else {
            currentOrder.delivery_partner_name = '';
        }
        currentOrder.is_complimentary_order = 0;
        isComplimentaryMode = false;
        posComplimentaryActionPassword = '';
        posComplimentaryNote = '';
        isOffcanvasComplimentaryMode = false;

        if(currentOrder.order_type === 'dine_in' && currentOrder.table_id) {
            let tableCard = $('.progga-pos-table-card[data-table-id="' + currentOrder.table_id + '"]');
            if(tableCard.length) {
                tableCard.removeClass('available reserved').addClass('occupied');
                tableCard.attr('data-status', 'occupied');
                tableCard.find('.progga-badge')
                    .removeClass('progga-status-available progga-status-reserved')
                    .addClass('progga-status-occupied')
                    .text('Occupied');
            }
        }

        showStep(2);
        loadCart();
    };

    $(document).ready(function() {
        const params = new URLSearchParams(window.location.search);
        const shouldOpenHeldQr = params.get('open_held_qr') === '1';
        const storedHeldQr = sessionStorage.getItem('openHeldQrOrderInPos');

        if (shouldOpenHeldQr && storedHeldQr) {
            try {
                const heldOrderData = JSON.parse(storedHeldQr);
                sessionStorage.removeItem('openHeldQrOrderInPos');

                if (typeof window.loadHeldQrOrderToPos === 'function') {
                    setTimeout(function() {
                        window.loadHeldQrOrderToPos(heldOrderData);
                    }, 300);
                }
            } catch (e) {
                console.error('Failed to open held QR order in POS cart:', e);
                sessionStorage.removeItem('openHeldQrOrderInPos');
            }
        }
    });


    window.removeCartItem = function(cartId) {
        // Unsaved/new food in the POS cart can be removed directly.
        // Password protection remains only for deleting food that is already saved on an order.
        let payload = getCartParams();
        payload.cart_id = cartId;

        $.post("{{ route('pos.cart.remove') }}", payload, function(res) {
            if (res.status === 'success') {
                loadCart(true);
            } else {
                Swal.fire('Error', res.message || 'Food could not be deleted.', 'error');
            }
        }).fail(function(xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'Food could not be deleted.', 'error');
        });
    }

    window.updateQty = function(cartId, action) {
        let payload = getCartParams();
        payload.cart_id = cartId;
        payload.action = action;
        $.post("{{ route('pos.cart.update') }}", payload, function(res) {
            if(res.status === 'success') loadCart(true);
        });
    }

    let cartQtyUpdateTimers = {};
    let cartQtyUpdateXhr = {};

    window.scheduleCartQtyUpdate = function(cartId, qty, el) {
        clearTimeout(cartQtyUpdateTimers[cartId]);

        cartQtyUpdateTimers[cartId] = setTimeout(function() {
            window.setCartQty(cartId, qty, el);
        }, 250);
    }

    window.setCartQty = function(cartId, qty, el) {
        qty = parseInt(qty) || 0;

        if(el) {
            $(el).prop('disabled', true);
        }

        let payload = getCartParams();
        payload.cart_id = cartId;
        payload.action = 'set';
        payload.qty = qty;

        if(cartQtyUpdateXhr[cartId] && cartQtyUpdateXhr[cartId].readyState !== 4) {
            cartQtyUpdateXhr[cartId].abort();
        }

        cartQtyUpdateXhr[cartId] = $.post("{{ route('pos.cart.update') }}", payload, function(res) {
            if(res.status === 'success') loadCart(true);
        }).always(function() {
            if(el) {
                $(el).prop('disabled', false);
            }
        });
    }

    window.cartNoteSaveRequests = window.cartNoteSaveRequests || {};

    window.updateItemNote = function(cartId, note) {
        let payload = getCartParams();
        payload.cart_id = cartId;
        payload.note = note;

        // Save the cart note silently. Do not call a page-specific toast helper here:
        // Send to Kitchen waits for this jqXHR, so a missing UI helper must never
        // interrupt the complimentary note save / KOT flow.
        const request = $.post("{{ route('pos.cart.update_note') }}", payload);

        window.cartNoteSaveRequests[cartId] = request;
        return request;
    }

    function validateComplimentaryCartNotes() {
        if (!complimentaryNoteRequired) return true;

        let firstMissing = null;
        $('#posCartBody .progga-pos-item-note-input[data-is-complimentary="1"]').each(function() {
            const $input = $(this);
            const note = String($input.val() || '').trim();
            const missing = note === '';
            $input.toggleClass('is-invalid', missing);
            if (missing && !firstMissing) firstMissing = $input;
        });

        if (firstMissing) {
            firstMissing.focus();
            window.Swal.fire('Note Required', 'Please enter a note for every complimentary food before sending to kitchen.', 'warning');
            return false;
        }

        return true;
    }

    $(document).on('input', '.progga-pos-item-note-input[data-is-complimentary="1"]', function() {
        if (String($(this).val() || '').trim() !== '') {
            $(this).removeClass('is-invalid');
        }
    });

    $(document).on('change', 'input[name="payment_method"]', function() {
        // Keep reference field visible for Card and Mobile Banking payment.
        if (typeof window.syncFinalPaymentFields === 'function') {
            window.syncFinalPaymentFields();
        } else if ($(this).val() === 'Card' || $(this).val() === 'Mobile Banking') {
            $('#transactionDiv').slideDown('fast').find('input[name="transaction_id"]').prop('disabled', false);
        } else {
            $('#transactionDiv').slideUp('fast').find('input[name="transaction_id"]').prop('disabled', true).val('');
        }
    });

    $(document).on('click', '#btnSendToKitchen', function(e) {
        e.preventDefault();

        if (currentOrder.order_type === 'dine_in' && !currentOrder.table_id) {
            window.proggaToast('Please select a table first!', 'danger');
            return;
        }

        // Commit the currently focused cart Note before checking/sending the order.
        // This prevents a quick click on Send to Kitchen from racing the Note AJAX save.
        const activeElement = document.activeElement;
        if (activeElement && $(activeElement).hasClass('progga-pos-item-note-input')) {
            $(activeElement).trigger('change');
        }

        if (!validateComplimentaryCartNotes()) {
            return;
        }

        const pendingNoteSaves = Object.values(window.cartNoteSaveRequests || {})
            .filter(function(xhr) { return xhr && xhr.readyState !== 4; });

        if (pendingNoteSaves.length) {
            const waitBtn = $('#btnSendToKitchen');
            const waitHtml = waitBtn.html();
            waitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving notes...');

            $.when.apply($, pendingNoteSaves)
                .done(function() {
                    waitBtn.prop('disabled', false).html(waitHtml);
                    $('#btnSendToKitchen').trigger('click');
                })
                .fail(function(xhr) {
                    waitBtn.prop('disabled', false).html(waitHtml);
                    const message = xhr && xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Could not save the food note. Please try again.';
                    window.Swal.fire('Error', message, 'error');
                });
            return;
        }

        // Send immediately without a confirmation popup.
        // For front-desk users the successful response redirects straight to the
        // KOT print view, which automatically opens the browser print dialog.
        var discType = $('#cart_discount_type').val() || 'fixed';
        var discVal = parseFloat($('#cart_discount_value').val()) || 0;

        var payload = {
            order_id: currentOrder.order_id || null,
            order_type: currentOrder.order_type,
            table_id: currentOrder.table_id,
            table_booking_id: currentOrder.table_booking_id || null,
            waiter_id: currentOrder.waiter_id,
            is_walk_in: currentOrder.is_walk_in,
            customer_id: currentOrder.customer_id,
            customer_name: currentOrder.customer_name,
            customer_phone: currentOrder.customer_phone,
            order_notes: currentOrder.order_notes,
            delivery_partner: currentOrder.delivery_partner || '',
            is_complimentary_order: currentOrder.is_complimentary_order || 0,
            action_password: currentOrder.is_complimentary_order ? posComplimentaryActionPassword : '',
            discount_type: discType,
            discount_value: discVal,
            preparation_time: $('#cart_prep_time').val() || 20,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        var btn = $('#btnSendToKitchen');
        var originalHtml = btn.html();

        $.ajax({
            url: "{{ route('pos.place_order') }}",
            type: "POST",
            data: payload,
            beforeSend: function() {
                btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);
            },
            success: function(res) {
                if(res.status === 'success') {
                    if (!isWaiter && res.kot_id && res.redirect_url && typeof window.openPosPrintPreview === 'function') {
                        window.openPosPrintPreview(res.redirect_url, 'KOT', { returnToPos: true });
                    } else {
                        window.location.href = "{{ route('pos.index') }}";
                    }
                } else {
                    window.Swal.fire('Error', res.message, 'error');
                    btn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function(xhr) {
                console.error("Error Response:", xhr.responseText);
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Server failed to process order.';
                window.Swal.fire('Error', message, 'error');
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });

    $(document).on('click', '#btnHoldOrder', function(e) {
        e.preventDefault();
        $('#btnSendToKitchen').trigger('click');
    });

    const givenMoneyManualToggleEnabled = @json((bool) ($posSetting->given_money_manual_toggle_enabled ?? true));
    window.givenMoneyWasManuallyEdited = false;

    function posPaymentNumber(value) {
        return parseFloat(String(value || 0).replace(/[^0-9.-]/g, '')) || 0;
    }

    function posMoney(value) {
        return Math.round(posPaymentNumber(value));
    }

    window.getProductDiscountTotal = function() {
        let total = 0;

        $('#payModalItemsArea .progga-product-discount-item[data-detail-id]').each(function() {
            let row = $(this);
            let lineTotal = Math.max(0, posPaymentNumber(row.data('line-total')));
            let type = row.find('.product-discount-type').val() || 'fixed';
            let value = Math.max(0, posPaymentNumber(row.find('.product-discount-value').val()));
            let amount = 0;

            if (type === 'percentage') {
                amount = lineTotal * Math.min(value, 100) / 100;
            } else {
                amount = Math.min(value, lineTotal);
            }

            amount = Math.max(0, Math.round(amount));
            row.find('.progga-product-discount-amount').text('−৳' + posMoney(amount));
            total += amount;
        });

        return Math.max(0, Math.round(total));
    };

    window.syncPaymentRemarkRequirement = function() {
        let orderDiscountValue = Math.max(0, posPaymentNumber($('#modal_discount_value').val()));
        let hasProductDiscount = false;

        $('#payModalItemsArea .product-discount-value').each(function() {
            if (Math.max(0, posPaymentNumber($(this).val())) > 0) {
                hasProductDiscount = true;
                return false;
            }
        });

        let hasDiscount = orderDiscountValue > 0 || hasProductDiscount;
        let remarkInput = $('#paymentRemark');

        remarkInput.prop('required', hasDiscount);
        $('#paymentRemarkRequired').toggle(hasDiscount);

        if (!hasDiscount) {
            remarkInput.removeClass('is-invalid');
        }

        return hasDiscount;
    };

    window.syncFinalPaymentFields = function() {
        let method = $('input[name="payment_method"]:checked').val() || 'Cash';
        let isSplit = method === 'Split';
        let showReferenceField = method === 'Card' || method === 'Mobile Banking';
        let isCard = method === 'Card';
        let isMfs = method === 'Mobile Banking';

        $('#cardTypeDiv').toggle(isCard);
        $('#mfsProviderDiv').toggle(isMfs);
        $('#cardTypeSelect').prop('disabled', !isCard).prop('required', isCard);
        $('#mfsProviderSelect').prop('disabled', !isMfs).prop('required', isMfs);
        if (!isCard) $('#cardTypeSelect').val('').removeClass('is-invalid');
        if (!isMfs) $('#mfsProviderSelect').val('').removeClass('is-invalid');

        $('#normalPaidRow').css('display', isSplit ? 'none' : 'flex');
        $('#splitPaidDisplayRow').css('display', isSplit ? 'flex' : 'none');
        $('#splitPaymentDiv').toggle(isSplit);
        $('#payTotalPaidAmount').prop('disabled', isSplit);
        $('#splitCash, #splitCard, #splitMfc').prop('disabled', !isSplit);
        let splitCardAmount = isSplit ? posPaymentNumber($('#splitCard').val()) : 0;
        let splitMfsAmount = isSplit ? posPaymentNumber($('#splitMfc').val()) : 0;
        let requireSplitCardReference = isSplit && splitCardAmount > 0;
        let requireSplitMfsReference = isSplit && splitMfsAmount > 0;

        $('#splitCardReference')
            .prop('disabled', !isSplit)
            .prop('required', requireSplitCardReference);
        $('#splitMfsReference')
            .prop('disabled', !isSplit)
            .prop('required', requireSplitMfsReference);
        $('#splitCardType')
            .prop('disabled', !isSplit)
            .prop('required', requireSplitCardReference);
        $('#splitMfsProvider')
            .prop('disabled', !isSplit)
            .prop('required', requireSplitMfsReference);
        $('#splitCardReferenceRequired, #splitCardTypeRequired').toggle(requireSplitCardReference);
        $('#splitMfsReferenceRequired, #splitMfsProviderRequired').toggle(requireSplitMfsReference);

        if (!isSplit) {
            $('#splitCardReference, #splitMfsReference').val('').removeClass('is-invalid');
            $('#splitCardType, #splitMfsProvider').val('').removeClass('is-invalid');
        } else {
            if (!requireSplitCardReference) $('#splitCardReference, #splitCardType').val('').removeClass('is-invalid');
            if (!requireSplitMfsReference) $('#splitMfsReference, #splitMfsProvider').val('').removeClass('is-invalid');
        }

        let transactionInput = $('#transactionDiv').find('input[name="transaction_id"]');
        $('#transactionDiv').toggle(showReferenceField);
        transactionInput
            .prop('disabled', !showReferenceField)
            .prop('required', showReferenceField);

        if (method === 'Card') {
            $('#transactionReferenceLabel').html('Reference <span class="text-danger">*</span>');
            transactionInput.attr('placeholder', 'Card Reference');
        } else if (method === 'Mobile Banking') {
            $('#transactionReferenceLabel').html('Reference <span class="text-danger">*</span>');
            transactionInput.attr('placeholder', 'MFS Reference');
        }

        if (!showReferenceField) {
            transactionInput.val('').removeClass('is-invalid');
        }
    };

    window.getFinalPaymentBillPaid = function() {
        let grand = posPaymentNumber($('#payTotalAmount').text());
        let advance = Math.min(grand, posPaymentNumber($('#payAdvanceAmount').val()));
        let method = $('input[name="payment_method"]:checked').val() || 'Cash';

        if (method === 'Split') {
            let cash = posPaymentNumber($('#splitCash').val());
            let card = posPaymentNumber($('#splitCard').val());
            let mfc = posPaymentNumber($('#splitMfc').val());
            let splitTotal = Math.min(Math.max(0, grand - advance), cash + card + mfc);
            let totalPaid = Math.min(grand, advance + splitTotal);
            $('#payTotalPaidAmount').val(totalPaid.toFixed(2));
            $('#payPaidDisplay').text('৳' + posMoney(splitTotal));
            return totalPaid;
        }

        let enteredTotal = posPaymentNumber($('#payTotalPaidAmount').val());
        return Math.min(grand, Math.max(advance, enteredTotal));
    };

    window.getCurrentPaymentAmount = function() {
        let grand = posPaymentNumber($('#payTotalAmount').text());
        let advance = Math.min(grand, posPaymentNumber($('#payAdvanceAmount').val()));
        let totalPaid = window.getFinalPaymentBillPaid();
        return Math.max(0, Math.min(grand - advance, totalPaid - advance));
    };

    window.syncAutoGivenMoney = function(force) {
        if (givenMoneyManualToggleEnabled) return;
        if (!force && window.givenMoneyWasManuallyEdited) return;

        let autoGivenMoney = window.getCurrentPaymentAmount();
        $('#payGivenMoney').val(posMoney(autoGivenMoney)).removeClass('is-invalid');
    };

    window.updateDueAmount = function() {
        let grand = posPaymentNumber($('#payTotalAmount').text());
        let totalPaid = window.getFinalPaymentBillPaid();
        let currentPayment = window.getCurrentPaymentAmount();
        let tips = posPaymentNumber($('#payTipsAmount').val());
        let givenMoneyRaw = $.trim($('#payGivenMoney').val());
        let givenMoney = posPaymentNumber(givenMoneyRaw);

        // Total Paid is the combined bill payment, including reservation advance.
        // Given Money may be manual/toggle mode or legacy auto-fill mode, based on POS Settings.
        let due = Math.max(0, grand - totalPaid);
        let hasGivenMoney = givenMoneyRaw !== '' && givenMoney > 0;
        let changeAmount = hasGivenMoney ? (givenMoney - currentPayment - tips) : 0;
        let isNegativeChange = changeAmount < 0;

        $('#payDueAmount').text('৳' + posMoney(due));
        $('#payChangeAmount')
            .val(posMoney(changeAmount))
            .css({
                'border-color': isNegativeChange ? '#dc3545' : '#198754',
                'color': isNegativeChange ? '#dc3545' : '#198754'
            });
    };

    window.resetFinalPaymentDefaults = function(grand) {
        grand = posMoney(grand);
        $('#payCash').prop('checked', true);
        $('#splitCash, #splitCard, #splitMfc').val(0);
        $('#payTotalPaidAmount').prop('disabled', false).val(grand);
        $('#payTipsAmount').val(0);
        window.givenMoneyWasManuallyEdited = false;
        // Setting ON: manual mode starts at 0 and exposes the Auto/Reset button.
        // Setting OFF: old behavior is restored and the payable amount is auto-filled.
        $('#payGivenMoney').val(0);
        $('#btnToggleGivenMoney').data('auto-active', false);
        $('#payChangeAmount').val(0);
        $('#transactionDiv').find('input[name="transaction_id"]').val('');
        $('#splitCardReference, #splitMfsReference').val('').removeClass('is-invalid');
        $('#cardTypeSelect, #mfsProviderSelect, #splitCardType, #splitMfsProvider').val('').removeClass('is-invalid');
        $('#paymentRemark').val('').removeClass('is-invalid');
        window.syncPaymentRemarkRequirement();
        window.syncFinalPaymentFields();
        if (!givenMoneyManualToggleEnabled) {
            window.syncAutoGivenMoney(true);
        }
        window.updateDueAmount();
    };
    window.currentCheckoutModalMode = 'payment';
    window.preInvoiceSnapshotCache = window.preInvoiceSnapshotCache || {};


    window.openPaymentModal = function(data) {
        window.currentCheckoutModalMode = 'payment';
        $('#paymentMethodSection, #paymentAmountSection').show();
        $('#paymentModalTitleIcon').attr('class', 'bi bi-credit-card me-2');
        $('#paymentModalTitleText').html('Checkout &amp; Payment');
        $('#payFormSubmitBtn').html('<i class="bi bi-check-circle-fill"></i> Confirm Payment');
        let oc = document.getElementById('tableOrderOffcanvas');
        if(oc) bootstrap.Offcanvas.getInstance(oc)?.hide();

        $('#payOrderId').val(data.order_id || '');
        $('#payOrderType').val(data.order_type || 'takeaway');
        $('#payIsComplimentaryOrder').val(data.is_complimentary_order ? 1 : 0);

        let defaultLabel = data.order_type === 'delivery' ? 'Delivery' : 'Takeaway';
        $('#payTableLabel').text(data.table_no || defaultLabel);

        $('#paymentModal').data('subtotal', parseFloat(data.subtotal || 0));
        $('#paySubtotal').text('৳' + Math.round(data.subtotal || 0));
        let hasTableBooking = parseInt(data.table_booking_id || 0, 10) > 0;
        $('#payAdvanceAmount').val(posMoney(hasTableBooking ? (data.booking_advance || 0) : 0));
        $('#bookingAdvanceRow').css('display', hasTableBooking ? 'flex' : 'none');

        let currentOrderId = parseInt(data.order_id || 0, 10);
        let cachedPreInvoiceSnapshot = currentOrderId > 0
            ? window.preInvoiceSnapshotCache[currentOrderId]
            : null;
        let preInvoiceSnapshot = cachedPreInvoiceSnapshot && typeof cachedPreInvoiceSnapshot === 'object'
            ? cachedPreInvoiceSnapshot
            : (data.pre_invoice_snapshot && typeof data.pre_invoice_snapshot === 'object'
                ? data.pre_invoice_snapshot
                : null);
        if (currentOrderId > 0 && preInvoiceSnapshot) {
            window.preInvoiceSnapshotCache[currentOrderId] = preInvoiceSnapshot;
        }
        $('#modal_discount_type').val(preInvoiceSnapshot && preInvoiceSnapshot.discount_type === 'percentage' ? 'percentage' : 'fixed');
        let savedOrderDiscountValue = preInvoiceSnapshot ? posPaymentNumber(preInvoiceSnapshot.discount_value) : 0;
        $('#modal_discount_value').val(savedOrderDiscountValue > 0 ? savedOrderDiscountValue : '');

        let escapeHtml = function(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        };

        let itemsHtml = '';
        if(data.items && data.items.length > 0) {
            data.items.forEach((item, index) => {
                let itemId = parseInt(item.id || 0, 10);
                let lineTotal = Math.max(0, posPaymentNumber(item.total));
                let savedType = item.product_discount_type === 'percentage' ? 'percentage' : 'fixed';
                let savedValue = Math.max(0, posPaymentNumber(item.product_discount_value));
                let safeName = escapeHtml(item.name);
                let controlHtml = '';

                if (itemId > 0) {
                    controlHtml = `
                    <div class="progga-product-discount-controls">
                        <select class="form-select product-discount-type"
                                name="product_discounts[${itemId}][type]"
                                form="payForm"
                                aria-label="Discount type for ${safeName}">
                            <option value="fixed" ${savedType === 'fixed' ? 'selected' : ''}>Fixed (৳)</option>
                            <option value="percentage" ${savedType === 'percentage' ? 'selected' : ''}>Percentage (%)</option>
                        </select>
                        <input type="number"
                               class="form-control product-discount-value"
                               name="product_discounts[${itemId}][value]"
                               form="payForm"
                               min="0"
                               step="0.01"
                               value="${savedValue > 0 ? savedValue : ''}"
                               placeholder="Discount">
                        <span class="progga-product-discount-amount">−৳0</span>
                    </div>`;
                }

                itemsHtml += `
                <div class="progga-product-discount-item" data-detail-id="${itemId || ''}" data-line-total="${lineTotal}">
                    <div class="progga-pay-summary-item" style="display:flex; justify-content:space-between; gap:10px; font-size:13px;">
                        <span class="text-muted">${safeName} ×${parseInt(item.qty || 0, 10)}</span>
                        <span style="font-weight:700; white-space:nowrap;">৳${Math.round(lineTotal)}</span>
                    </div>
                    ${controlHtml}
                </div>`;
            });
        } else {
            itemsHtml = '<div class="text-muted text-center" style="font-size:12px;">No items</div>';
        }
        $('#payModalItemsArea').html(itemsHtml);

        calculateModalTotal();
        window.resetFinalPaymentDefaults(posPaymentNumber($('#payTotalAmount').text()));

        // resetFinalPaymentDefaults clears Remark for a fresh order. Restore the
        // saved Pre-Invoice Remark afterwards so Final Payment continues with it.
        let savedPreInvoiceRemark = preInvoiceSnapshot && preInvoiceSnapshot.remark != null
            ? String(preInvoiceSnapshot.remark)
            : '';
        $('#paymentRemark').val(savedPreInvoiceRemark).removeClass('is-invalid');
        window.syncPaymentRemarkRequirement();

        bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).show();

        // Re-sync after Bootstrap finishes showing the modal, so Card/Mobile reference field cannot be hidden by older handlers.
        setTimeout(function () {
            if (typeof window.syncFinalPaymentFields === 'function') {
                window.syncFinalPaymentFields();
            }
        }, 80);
    }

    window.openPreInvoiceModal = function(data) {
        // Reuse the checkout modal so Order Summary, product-wise discounts,
        // Honored discount and Remark behave exactly like Final Payment.
        window.openPaymentModal(data);
        window.currentCheckoutModalMode = 'preinvoice';
        $('#paymentMethodSection, #paymentAmountSection').hide();
        $('#paymentModalTitleIcon').attr('class', 'bi bi-receipt me-2');
        $('#paymentModalTitleText').text('Bill');
        $('#payFormSubmitBtn').html('<i class="bi bi-printer-fill"></i> Print Bill');
    };

    window.calculateModalTotal = function() {
        // Track whether Total Paid is still on its automatic full-payment default.
        // Given Money follows the selected setting: manual/toggle mode or legacy auto-fill mode.
        let previousGrand = posPaymentNumber($('#payTotalAmount').text());
        let previousAdvance = Math.min(previousGrand, posPaymentNumber($('#payAdvanceAmount').val()));
        let previousTotalPaid = posPaymentNumber($('#payTotalPaidAmount').val());

        let subtotal = parseFloat($('#paymentModal').data('subtotal')) || 0;
        let vat_rate = parseFloat("{{ $taxSettingVatRate ?? 0 }}");

        let orderType = $('#payOrderType').val();
        let service_rate = (orderType === 'dine_in' || orderType === 'Dine-In') ? parseFloat("{{ $taxSettingServiceCharge ?? 0 }}") : 0;

        let disc_type = $('#modal_discount_type').val();
        let disc_val = parseFloat($('#modal_discount_value').val()) || 0;

        let service = Math.round((subtotal * service_rate) / 100);
        let vat = Math.round(((subtotal + service) * vat_rate) / 100);
        // Existing whole-order discount formula stays exactly on the original subtotal.
        let discount_amount = Math.round((disc_type === 'percentage') ? (subtotal * disc_val / 100) : disc_val);
        let product_discount_amount = typeof window.getProductDiscountTotal === 'function'
            ? window.getProductDiscountTotal()
            : 0;

        let grand = Math.max(0, Math.round((subtotal + vat + service) - discount_amount - product_discount_amount));
        let advance = Math.min(grand, posPaymentNumber($('#payAdvanceAmount').val()));

        $('#payProductDiscount').text('−৳' + product_discount_amount);
        $('#payDiscount').text('−৳' + discount_amount);
        $('#payVat').text('৳' + vat);
        $('#payService').text('৳' + service);
        $('#payTotalAmount').text('৳' + grand);
        window.syncPaymentRemarkRequirement();

        // Only show tax rows when the corresponding setting has a positive rate.
        // A database value of 0 or NULL is shared to the view as 0.
        $('#payServiceRow').css('display', service_rate > 0 ? 'flex' : 'none');
        $('#payVatRow').css('display', vat_rate > 0 ? 'flex' : 'none');

        if ($('input[name="payment_method"]:checked').val() !== 'Split') {
            let totalWasAutoFilled = previousTotalPaid === 0 || Math.abs(previousTotalPaid - previousGrand) < 0.01;
            if (totalWasAutoFilled) {
                $('#payTotalPaidAmount').val(grand);
            } else {
                $('#payTotalPaidAmount').val(Math.min(grand, Math.max(advance, previousTotalPaid)));
            }

        }

        if(typeof window.syncFinalPaymentFields === 'function') {
            window.syncFinalPaymentFields();
        }
        if (!givenMoneyManualToggleEnabled && typeof window.syncAutoGivenMoney === 'function') {
            window.syncAutoGivenMoney(false);
        }
        if(typeof window.updateDueAmount === 'function') {
            window.updateDueAmount();
        }
    }

    $(document).on('input change', '.product-discount-type, .product-discount-value', function() {
        window.calculateModalTotal();
    });

    $(document).on('input change', '#payTotalPaidAmount, #payTipsAmount, #payGivenMoney, .split-input', function() {
        let isGivenMoneyInput = $(this).is('#payGivenMoney');

        if (isGivenMoneyInput) {
            if (givenMoneyManualToggleEnabled) {
                // Any manual type/paste exits the button's auto-filled state.
                $('#btnToggleGivenMoney').data('auto-active', false);
            } else {
                // Legacy auto-fill mode remains editable. Once the operator types/pastes,
                // later total/discount changes must not overwrite their manual amount.
                window.givenMoneyWasManuallyEdited = true;
            }
        }

        if ($(this).hasClass('split-input')) window.syncFinalPaymentFields();
        if (!isGivenMoneyInput && !givenMoneyManualToggleEnabled) {
            window.syncAutoGivenMoney(false);
        }
        window.updateDueAmount();
    });

    $(document).on('click', '#btnToggleGivenMoney', function() {
        if (!givenMoneyManualToggleEnabled) return;
        let button = $(this);
        let isAutoActive = button.data('auto-active') === true;

        if (isAutoActive) {
            $('#payGivenMoney').val(0).removeClass('is-invalid');
            button.data('auto-active', false);
        } else {
            // Match the old automatic behavior: fill the amount being collected now,
            // excluding any booking advance already paid.
            let autoGivenMoney = window.getCurrentPaymentAmount();
            $('#payGivenMoney').val(posMoney(autoGivenMoney)).removeClass('is-invalid');
            button.data('auto-active', true);
        }

        window.updateDueAmount();
    });

    $(document).on('change', 'input[name="payment_method"]', function() {
        let grand = posMoney($('#payTotalAmount').text());
        if ($(this).val() !== 'Split') {
            $('#payTotalPaidAmount').val(grand);
        }
        window.syncFinalPaymentFields();
        if (!givenMoneyManualToggleEnabled) {
            window.syncAutoGivenMoney(false);
        }
        window.updateDueAmount();
    });

    $(document).on('submit', '#payForm', function(e) {
        e.preventDefault();

        window.syncFinalPaymentFields();
        window.updateDueAmount();

        let hasDiscount = window.syncPaymentRemarkRequirement();
        let remarkInput = $('#paymentRemark');
        if (hasDiscount && !$.trim(remarkInput.val())) {
            remarkInput.addClass('is-invalid').trigger('focus');
            Swal.fire(
                'Remark Required',
                'Remark is required when a discount is applied.',
                'warning'
            );
            return;
        }
        remarkInput.removeClass('is-invalid');

        if (window.currentCheckoutModalMode === 'preinvoice') {
            let orderId = parseInt($('#payOrderId').val() || 0, 10);
            if (!orderId) {
                Swal.fire('Info', 'No active order found for bill.', 'info');
                return;
            }

            let payload = {
                _token: $('meta[name="csrf-token"]').attr('content'),
                disc_type: $('#modal_discount_type').val() || 'fixed',
                disc_val: Math.max(0, posPaymentNumber($('#modal_discount_value').val())),
                remark: $.trim($('#paymentRemark').val() || ''),
                product_discounts: {}
            };

            $('#payModalItemsArea .progga-product-discount-item[data-detail-id]').each(function() {
                let row = $(this);
                let detailId = parseInt(row.data('detail-id') || 0, 10);
                if (detailId > 0) {
                    payload.product_discounts[detailId] = {
                        type: row.find('.product-discount-type').val() || 'fixed',
                        value: Math.max(0, posPaymentNumber(row.find('.product-discount-value').val()))
                    };
                }
            });

            let btn = $('#payFormSubmitBtn');
            let originalHtml = btn.html();
            btn.html('<i class="spinner-border spinner-border-sm"></i> Preparing...').prop('disabled', true);

            $.ajax({
                url: @json(url('/pos/pre-invoice')) + '/' + orderId + '/snapshot',
                type: 'POST',
                data: payload,
                success: function(res) {
                    if (res && res.status === 'success' && res.preview_url) {
                        if (res.snapshot && typeof res.snapshot === 'object') {
                            window.preInvoiceSnapshotCache[orderId] = res.snapshot;
                        }
                        if (res.table_id) {
                            setPosTableStatus(res.table_id, 'occupied');
                            setPosTableBillPrinted(res.table_id, true);
                        }
                        let modalEl = document.getElementById('paymentModal');
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                        window.setTimeout(function() {
                            if (typeof window.openPosPrintPreview === 'function') {
                                window.openPosPrintPreview(res.preview_url, 'Bill', { returnToPos: true });
                            } else {
                                window.location.href = res.preview_url;
                            }
                        }, 180);
                        return;
                    }
                    Swal.fire('Error', (res && res.message) || 'Could not prepare bill.', 'error');
                },
                error: function(xhr) {
                    let message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Could not prepare bill.';
                    Swal.fire('Error', message, 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
            return;
        }

        let paymentMethod = $('input[name="payment_method"]:checked').val() || 'Cash';
        let referenceInput = $('#transactionDiv').find('input[name="transaction_id"]');
        let requiresReference = paymentMethod === 'Card' || paymentMethod === 'Mobile Banking';

        if (paymentMethod === 'Card' && !$.trim($('#cardTypeSelect').val())) {
            $('#cardTypeSelect').addClass('is-invalid').trigger('focus');
            Swal.fire('Card Type Required', 'Please select the card type before completing payment.', 'warning');
            return;
        }
        $('#cardTypeSelect').removeClass('is-invalid');

        if (paymentMethod === 'Mobile Banking' && !$.trim($('#mfsProviderSelect').val())) {
            $('#mfsProviderSelect').addClass('is-invalid').trigger('focus');
            Swal.fire('MFS Service Required', 'Please select the MFS service before completing payment.', 'warning');
            return;
        }
        $('#mfsProviderSelect').removeClass('is-invalid');

        if (requiresReference && !$.trim(referenceInput.val())) {
            referenceInput.addClass('is-invalid').trigger('focus');
            let referenceName = paymentMethod === 'Card' ? 'Bank / Card Reference Number' : 'MFS Reference Number';
            Swal.fire(
                'Reference Required',
                'Please enter the ' + referenceName + ' before completing payment.',
                'warning'
            );
            return;
        }

        referenceInput.removeClass('is-invalid');

        if (paymentMethod === 'Split') {
            let splitCardAmount = posPaymentNumber($('#splitCard').val());
            let splitMfsAmount = posPaymentNumber($('#splitMfc').val());
            let splitCardReference = $('#splitCardReference');
            let splitMfsReference = $('#splitMfsReference');
            let splitCardType = $('#splitCardType');
            let splitMfsProvider = $('#splitMfsProvider');

            if (splitCardAmount > 0 && !$.trim(splitCardType.val())) {
                splitCardType.addClass('is-invalid').trigger('focus');
                Swal.fire('Card Type Required', 'Please select the card type for the split card amount.', 'warning');
                return;
            }
            splitCardType.removeClass('is-invalid');

            if (splitMfsAmount > 0 && !$.trim(splitMfsProvider.val())) {
                splitMfsProvider.addClass('is-invalid').trigger('focus');
                Swal.fire('MFS Service Required', 'Please select the MFS service for the split MFS amount.', 'warning');
                return;
            }
            splitMfsProvider.removeClass('is-invalid');

            if (splitCardAmount > 0 && !$.trim(splitCardReference.val())) {
                splitCardReference.addClass('is-invalid').trigger('focus');
                Swal.fire('Bank / Card Reference Required', 'Please enter the Bank / Card Reference Number for the Bank / Card amount.', 'warning');
                return;
            }

            splitCardReference.removeClass('is-invalid');

            if (splitMfsAmount > 0 && !$.trim(splitMfsReference.val())) {
                splitMfsReference.addClass('is-invalid').trigger('focus');
                Swal.fire('MFS Reference Required', 'Please enter the MFS Reference Number for the MFS amount.', 'warning');
                return;
            }

            splitMfsReference.removeClass('is-invalid');
        }

        let totalPaid = window.getFinalPaymentBillPaid();
        let currentPayment = window.getCurrentPaymentAmount();
        let tipsAmount = posPaymentNumber($('#payTipsAmount').val());
        let givenMoney = posPaymentNumber($('#payGivenMoney').val());
        let requiredGivenMoney = currentPayment + tipsAmount;
        let saveAsDueOrder = $(this).data('saveAsDueOrder') === true;
        $(this).removeData('saveAsDueOrder');

        // Keep the negative Change visible while the operator is entering a short amount.
        // On submit, offer either correcting the amount or intentionally saving the shortage as Due.
        if (givenMoney + 0.001 < requiredGivenMoney && !saveAsDueOrder) {
            let paymentForm = $(this);
            $('#payGivenMoney').addClass('is-invalid');

            Swal.fire({
                icon: 'warning',
                title: 'Insufficient Given Money',
                text: 'You entered less money than the payment amount. Correct the amount or save the shortage as a Due Order.',
                showDenyButton: true,
                showCancelButton: false,
                confirmButtonText: 'Correct Amount',
                denyButtonText: 'Save as Due Order',
                reverseButtons: true
            }).then((result) => {
                if (result.isDenied) {
                    $('#payGivenMoney').removeClass('is-invalid');
                    paymentForm.data('saveAsDueOrder', true);
                    paymentForm.trigger('submit');
                    return;
                }

                if (result.isConfirmed) {
                    $('#payGivenMoney').trigger('focus').select();
                }
            });

            return;
        }

        $('#payGivenMoney').removeClass('is-invalid');
        $('#payTipsAmount').removeClass('is-invalid');

        let btn = $(this).find('button[type="submit"]');
        let originalHtml = btn.html();
        btn.html('<i class="spinner-border spinner-border-sm"></i> Processing...').prop('disabled', true);

        let formData = $(this).serialize();
        if (saveAsDueOrder) {
            formData += '&save_as_due_order=1';
        }

        $.ajax({
            url: "{{ route('pos.complete_payment') }}",
            type: "POST",
            data: formData + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
            success: function(res) {
                if(res.status === 'success') {
                    // Final invoice printing must start only after the payment modal is fully closed.
                    // This is especially important for the "Save as Due Order" path: that path already
                    // passes through a SweetAlert confirmation and leaving the Bootstrap focus trap active
                    // can prevent the browser print dialog from opening.
                    let printStarted = false;
                    let modalEl = document.getElementById('paymentModal');

                    const printFinalInvoice = function() {
                        if (printStarted) return;
                        printStarted = true;

                        if (res.redirect_url && typeof window.openPosPrintPreview === 'function') {
                            window.openPosPrintPreview(res.redirect_url, 'Invoice', { returnToPos: true });
                        } else if (res.redirect_url) {
                            window.location.href = res.redirect_url;
                        } else {
                            window.location.href = "{{ route('pos.index') }}";
                        }
                    };

                    if (modalEl && modalEl.classList.contains('show')) {
                        $(modalEl).one('hidden.bs.modal', function() {
                            window.setTimeout(printFinalInvoice, 60);
                        });
                        bootstrap.Modal.getInstance(modalEl)?.hide();

                        // Fallback for browsers where hidden.bs.modal is not emitted as expected.
                        window.setTimeout(printFinalInvoice, 500);
                    } else {
                        window.setTimeout(printFinalInvoice, 60);
                    }
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function(xhr) {
                let message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Server error. Please try again.';
                Swal.fire('Error', message, 'error');
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });

    function suspendPosBootstrapFocusTraps() {
        const suspended = [];

        document.querySelectorAll('.modal.show, .offcanvas.show').forEach(function(el) {
            let instance = null;

            if (el.classList.contains('modal')) {
                instance = bootstrap.Modal.getInstance(el);
            } else if (el.classList.contains('offcanvas')) {
                instance = bootstrap.Offcanvas.getInstance(el);
            }

            if (instance && instance._focustrap && typeof instance._focustrap.deactivate === 'function') {
                instance._focustrap.deactivate();
                suspended.push({ instance: instance, element: el });
            }
        });

        return function() {
            setTimeout(function() {
                suspended.forEach(function(item) {
                    if (item.element.classList.contains('show') && item.instance._focustrap && typeof item.instance._focustrap.activate === 'function') {
                        item.instance._focustrap.activate();
                    }
                });
            }, 50);
        };
    }

    function getPosActionPassword(callback, cancelCallback){
        const restoreFocusTraps = suspendPosBootstrapFocusTraps();

        if (document.activeElement) {
            document.activeElement.blur();
        }

        Swal.fire({
            title: 'POS Action Password',
            text: 'Enter the password saved in Settings to continue.',
            input: 'password',
            inputPlaceholder: 'Enter password',
            inputAttributes: {
                autocomplete: 'new-password',
                autocapitalize: 'off',
                spellcheck: 'false'
            },
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: true,
            confirmButtonText: 'Verify & Continue',
            showLoaderOnConfirm: true,
            didOpen: function() {
                setTimeout(function() {
                    const input = Swal.getInput();
                    if (input) {
                        input.removeAttribute('readonly');
                        input.disabled = false;
                        input.focus();
                    }
                }, 50);
            },
            preConfirm: function(password) {
                password = String(password || '');

                if (!password) {
                    Swal.showValidationMessage('Password is required.');
                    return false;
                }

                return $.ajax({
                    url: "{{ route('pos.action.verify') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        password: password,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }
                }).then(function(res) {
                    if (!res || res.status !== 'success') {
                        Swal.showValidationMessage(res?.message || 'Wrong POS Action Password.');
                        return false;
                    }

                    return password;
                }, function(xhr) {
                    const message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Password verification failed. Please try again.';
                    Swal.showValidationMessage(message);
                    return false;
                });
            }
        }).then(function(result) {
            restoreFocusTraps();

            if (result.isConfirmed && result.value) {
                callback(result.value);
            } else if (typeof cancelCallback === 'function') {
                cancelCallback();
            }
        });
    }

    function getComplimentaryPasswordAndNote(callback, cancelCallback) {
        const restoreFocusTraps = suspendPosBootstrapFocusTraps();

        if (document.activeElement) {
            document.activeElement.blur();
        }

        const noteLabel = complimentaryNoteRequired
            ? 'Note <span class="text-danger">*</span>'
            : 'Note <span class="text-muted" style="font-size:12px;">(Optional)</span>';

        Swal.fire({
            title: 'Complimentary Authorization',
            html:
                '<div class="text-start">' +
                    '<label for="swalComplimentaryPassword" class="form-label fw-semibold mb-1">POS Action Password</label>' +
                    '<input id="swalComplimentaryPassword" type="password" class="swal2-input" placeholder="Enter password" autocomplete="new-password" style="width:100%;margin:0 0 14px 0;">' +
                    '<label for="swalComplimentaryNote" class="form-label fw-semibold mb-1">' + noteLabel + '</label>' +
                    '<textarea id="swalComplimentaryNote" class="swal2-textarea" rows="3" maxlength="1000" placeholder="Enter complimentary note" style="width:100%;margin:0;"></textarea>' +
                '</div>',
            focusConfirm: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: true,
            confirmButtonText: 'Verify & Continue',
            showLoaderOnConfirm: true,
            didOpen: function() {
                setTimeout(function() {
                    const input = document.getElementById('swalComplimentaryPassword');
                    if (input) input.focus();
                }, 50);
            },
            preConfirm: function() {
                const password = String($('#swalComplimentaryPassword').val() || '');
                const note = String($('#swalComplimentaryNote').val() || '').trim();

                if (!password) {
                    Swal.showValidationMessage('Password is required.');
                    return false;
                }
                if (complimentaryNoteRequired && !note) {
                    Swal.showValidationMessage('Note is required for complimentary food.');
                    return false;
                }

                return $.ajax({
                    url: "{{ route('pos.action.verify') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        password: password,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }
                }).then(function(res) {
                    if (!res || res.status !== 'success') {
                        Swal.showValidationMessage(res?.message || 'Wrong POS Action Password.');
                        return false;
                    }
                    return { password: password, note: note };
                }, function(xhr) {
                    const message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Password verification failed. Please try again.';
                    Swal.showValidationMessage(message);
                    return false;
                });
            }
        }).then(function(result) {
            restoreFocusTraps();

            if (result.isConfirmed && result.value) {
                callback(result.value.password, result.value.note || '');
            } else if (typeof cancelCallback === 'function') {
                cancelCallback();
            }
        });
    }

    window.openOrderItemDeleteModal = function(orderId, orderDetailIds, itemName, maxQty) {
        const ids = String(orderDetailIds || '')
            .split(',')
            .map(id => parseInt(id, 10))
            .filter(id => id > 0);

        $('#deleteOrderId').val(orderId);
        $('#deleteOrderDetailIds').val(ids.join(','));
        $('#deleteOrderDetailId').val(ids.length ? ids[ids.length - 1] : '');
        $('#deleteOrderItemName').text(itemName);
        $('#deleteOrderItemMaxQty').text(maxQty);
        $('#deleteOrderItemQty').attr('max', maxQty).val(1);
        $('#deleteOrderItemReason').val('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('orderItemDeleteModal')).show();
    }

    $(document).on('click', '#btnDeleteFullQty', function() {
        $('#deleteOrderItemQty').val($('#deleteOrderItemQty').attr('max') || 1);
    });

    $(document).on('submit', '#orderItemDeleteForm', function(e) {
        e.preventDefault();

        let qty = parseInt($('#deleteOrderItemQty').val()) || 0;
        let maxQty = parseInt($('#deleteOrderItemQty').attr('max')) || 0;

        if(qty < 1 || qty > maxQty) {
            Swal.fire('Invalid Quantity', 'Please enter a quantity between 1 and ' + maxQty + '.', 'warning');
            return;
        }

        // Every item visible here has already been sent/saved on the order, so deletion
        // requires the POS action password. Unsent cart items use removeCartItem() instead.
        getPosActionPassword(function(pass){ proceedDeleteWithPassword(pass); });
        return;
    });

    function proceedDeleteWithPassword(pass){
        let btn = $('#btnConfirmOrderItemDelete');
        let originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Deleting...');

        $.ajax({
            url: "{{ route('pos.order_item.remove') }}",
            type: "POST",
            data: $('#orderItemDeleteForm').serialize() + '&action_password=' + encodeURIComponent(pass) + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
            success: function(res) {
                btn.prop('disabled', false).html(originalHtml);

                if(res.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('orderItemDeleteModal'))?.hide();
                    Swal.fire({ icon: 'success', title: 'Deleted!', text: res.message, timer: 1200, showConfirmButton: false });

                    let tableId = currentOrder.table_id || $('#btnContinueOrdering').data('table-id');
                    let orderId = currentOrder.order_id || $('#btnContinueOrdering').data('order-id');
                    if(tableId) {
                        $.get("{{ route('pos.get_table_order', ':id') }}".replace(':id', tableId), function(html) {
                            if(typeof html === 'object' && html.status === 'error') {
                                location.reload();
                            } else {
                                cleanupActiveOrderMetaModal();
                                $('#ocBody').html(html);
                                mountActiveOrderMetaModal();
                            }
                        });
                    } else if(orderId) {
                        $.get("{{ route('pos.get_pos_order', ':id') }}".replace(':id', orderId), function(html) {
                            if(typeof html === 'object' && html.status === 'error') {
                                location.reload();
                            } else {
                                cleanupActiveOrderMetaModal();
                                $('#ocBody').html(html);
                                mountActiveOrderMetaModal();
                            }
                        });
                    } else {
                        location.reload();
                    }
                } else {
                    Swal.fire('Error', res.message || 'Could not delete item.', 'error');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error. Please try again.';
                Swal.fire('Error', msg, 'error');
            }
        });
    }

    $('#tableOrderOffcanvas').on('hidden.bs.offcanvas', function() {
        cleanupActiveOrderMetaModal();
    });

    $(document).on('change', 'input[name="oc_customer_mode"]', function() {
        const $form = $(this).closest('#ocCustomerUpdateForm');
        const isNew = $(this).val() === 'new';

        $form.find('.progga-oc-choice').removeClass('active-choice');
        $(this).closest('.progga-oc-choice').addClass('active-choice');
        $form.find('.oc-existing-customer-wrap').toggle(!isNew);
        $form.find('.oc-new-customer-wrap').toggle(isNew);
    });

    $(document).on('submit', '#ocCustomerUpdateForm', function(e) {
        e.preventDefault();

        const $form = $(this);
        const orderId = $form.attr('data-order-id');
        const tableId = $form.attr('data-table-id') || null;
        const orderType = $form.attr('data-order-type') || 'dine_in';
        const mode = $form.find('input[name="oc_customer_mode"]:checked').val() || 'existing';
        const payload = {
            order_id: orderId,
            update_type: 'customer',
            customer_mode: mode
        };

        if (mode === 'new') {
            payload.customer_name = $.trim($form.find('[name="customer_name"]').val() || '');
            payload.customer_phone = $.trim($form.find('[name="customer_phone"]').val() || '');

            if (!payload.customer_name || !payload.customer_phone) {
                Swal.fire('Customer Info', 'Please enter customer name and phone number.', 'warning');
                return;
            }
        } else {
            payload.customer_id = $form.find('[name="customer_id"]').val();
            if (!payload.customer_id) {
                Swal.fire('Select Customer', 'Please select an existing customer.', 'warning');
                return;
            }
        }

        const $btn = $form.find('.js-oc-customer-save');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

        $.ajax({
            url: "{{ route('pos.active_order.update_meta') }}",
            type: 'POST',
            data: payload,
            success: function(res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Customer update failed.', 'error');
                    return;
                }

                reloadActiveOrderOffcanvas(orderId, tableId, orderType)
                    .done(function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Customer Added',
                            text: res.message || 'Customer added to the order successfully.',
                            timer: 1200,
                            showConfirmButton: false
                        });
                    })
                    .fail(function() {
                        window.location.reload();
                    });
            },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Customer update failed. Please try again.';
                Swal.fire('Error', msg, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    $(document).on('submit', '#ocCustomerWalkInForm', function(e) {
        e.preventDefault();

        const $form = $(this);
        const orderId = $form.attr('data-order-id');
        const tableId = $form.attr('data-table-id') || null;
        const orderType = $form.attr('data-order-type') || 'dine_in';
        const $btn = $form.find('.js-oc-customer-walkin');
        const originalHtml = $btn.html();

        Swal.fire({
            title: 'Make Walk-in Customer?',
            text: 'The customer will be removed from this order. The customer record itself will not be deleted.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Make Walk-in',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Updating...');

            $.ajax({
                url: "{{ route('pos.active_order.update_meta') }}",
                type: 'POST',
                data: {
                    order_id: orderId,
                    update_type: 'customer',
                    customer_mode: 'walk_in'
                },
                success: function(res) {
                    if (res.status !== 'success') {
                        Swal.fire('Error', res.message || 'Could not change customer to Walk-in.', 'error');
                        return;
                    }

                    reloadActiveOrderOffcanvas(orderId, tableId, orderType)
                        .done(function() {
                            Swal.fire({
                                icon: 'success',
                                title: 'Walk-in Customer',
                                text: res.message || 'Order changed back to Walk-in Customer.',
                                timer: 1200,
                                showConfirmButton: false
                            });
                        })
                        .fail(function() {
                            window.location.reload();
                        });
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Could not change customer to Walk-in. Please try again.';
                    Swal.fire('Error', msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    });

    $(document).on('submit', '#ocWaiterUpdateForm', function(e) {
        e.preventDefault();

        const $form = $(this);
        const orderId = $form.attr('data-order-id');
        const tableId = $form.attr('data-table-id') || null;
        const orderType = $form.attr('data-order-type') || 'dine_in';
        const waiterId = $form.find('[name="waiter_id"]').val();

        if (!waiterId) {
            Swal.fire('Select Waiter', 'Please select a waiter.', 'warning');
            return;
        }

        const $btn = $form.find('.js-oc-waiter-save');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Updating...');

        $.ajax({
            url: "{{ route('pos.active_order.update_meta') }}",
            type: 'POST',
            data: {
                order_id: orderId,
                update_type: 'waiter',
                waiter_id: waiterId
            },
            success: function(res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Waiter update failed.', 'error');
                    return;
                }

                reloadActiveOrderOffcanvas(orderId, tableId, orderType)
                    .done(function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Waiter Updated',
                            text: res.message || 'Waiter updated successfully.',
                            timer: 1100,
                            showConfirmButton: false
                        });
                    })
                    .fail(function() {
                        window.location.reload();
                    });
            },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Waiter update failed. Please try again.';
                Swal.fire('Error', msg, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    $(document).on('submit', '#ocDeliveryPartnerUpdateForm', function(e) {
        e.preventDefault();

        const $form = $(this);
        const orderId = $form.attr('data-order-id');
        const tableId = $form.attr('data-table-id') || null;
        const orderType = $form.attr('data-order-type') || 'delivery';
        const deliveryPartner = $form.find('[name="delivery_partner"]').val();

        if (!deliveryPartner) {
            Swal.fire('Delivery Partner', 'Please select a delivery partner.', 'warning');
            return;
        }

        const $btn = $form.find('.js-oc-partner-save');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: "{{ route('pos.active_order.update_meta') }}",
            type: 'POST',
            data: {
                order_id: orderId,
                update_type: 'delivery_partner',
                delivery_partner: deliveryPartner
            },
            success: function(res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Delivery partner update failed.', 'error');
                    return;
                }

                reloadActiveOrderOffcanvas(orderId, tableId, orderType)
                    .done(function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Delivery Partner Updated',
                            text: res.message || 'Delivery partner updated successfully.',
                            timer: 1100,
                            showConfirmButton: false
                        });
                    })
                    .fail(function() {
                        window.location.reload();
                    });
            },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Delivery partner update failed. Please try again.';
                Swal.fire('Error', msg, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    $(document).on('click', '#btnToggleTableSwap', function() {
        $('#tableSwapForm').slideToggle(140);
    });

    $(document).on('submit', '#tableSwapForm', function(e) {
        e.preventDefault();

        const $form = $(this);
        const orderId = $form.find('input[name="order_id"]').val();
        const currentTableId = $form.find('input[name="current_table_id"]').val();
        const newTableId = $('#tableSwapNewTable').val();
        const newTableNumber = $('#tableSwapNewTable option:selected').data('table-number') || $('#tableSwapNewTable option:selected').text();

        if (!newTableId) {
            Swal.fire('Select Table', 'Please select an available table first.', 'warning');
            return;
        }

        Swal.fire({
            icon: 'question',
            title: 'Swap Table?',
            text: 'Move this order to ' + newTableNumber + '?',
            showCancelButton: true,
            confirmButtonText: 'Yes, Swap',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const $btn = $('#btnConfirmTableSwap');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Swapping...');

            $.ajax({
                url: "{{ route('pos.table_swap') }}",
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    order_id: orderId,
                    new_table_id: newTableId
                },
                success: function(res) {
                    $btn.prop('disabled', false).html(originalHtml);

                    if (res.status !== 'success') {
                        Swal.fire('Error', res.message || 'Table swap failed.', 'error');
                        return;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Table Swapped',
                        text: res.message || 'Table swapped successfully.',
                        timer: 900,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(function() {
                        window.location.reload();
                    });

                    setTimeout(function() {
                        window.location.reload();
                    }, 950);
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(originalHtml);
                    let msg = 'Server error. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', msg, 'error');
                }
            });
        });
    });

    $(document).on('click', '#btnAddComplimentary', function() {
        const $sourceButton = $(this);

        // Add Complimentary mode only asks for the POS action password.
        // Each complimentary food gets its own Note from the cart row below the food.
        getPosActionPassword(function(pass) {
            const tId = $sourceButton.data('table-id');
            const orderId = $sourceButton.data('order-id');
            const waiterId = $sourceButton.data('waiter-id');
            const waiterName = $sourceButton.data('waiter-name');
            const customerId = $sourceButton.data('customer-id');
            const customerName = $sourceButton.data('customer-name');
            const orderType = $sourceButton.data('order-type') || 'dine_in';
            const orderLabel = $sourceButton.data('order-label') || $('#ocTableNum').text();
            const deliveryPartner = $sourceButton.data('delivery-partner') || '';
            const deliveryPartnerName = $sourceButton.data('delivery-partner-name') || '';

            currentOrder.table_id = tId || null;
            currentOrder.table_name = orderLabel;
            currentOrder.order_id = orderId;
            currentOrder.order_type = orderType;
            currentOrder.delivery_partner = deliveryPartner || (orderType === 'delivery' ? 'inhouse' : '');
            currentOrder.delivery_partner_name = orderType === 'delivery' ? deliveryPartnerName : '';
            currentOrder.waiter_id = waiterId ? waiterId : null;
            currentOrder.waiter_name = waiterName ? waiterName : '';

            if(customerId) {
                currentOrder.is_walk_in = 0;
                currentOrder.customer_id = customerId;
                currentOrder.customer_name = customerName;
            } else {
                currentOrder.is_walk_in = 1;
                currentOrder.customer_id = null;
                currentOrder.customer_name = '';
            }

            currentOrder.is_complimentary_order = 0;
            isComplimentaryMode = true;
            posComplimentaryActionPassword = pass;
            posComplimentaryNote = '';
            isOffcanvasComplimentaryMode = true;

            var ocElement = document.getElementById('tableOrderOffcanvas');
            if (ocElement) {
                var ocInstance = bootstrap.Offcanvas.getInstance(ocElement);
                if (ocInstance) ocInstance.hide();
            }

            showStep(2);
            loadCart();
            if(window.Swal) {
                Swal.fire({
                    icon: 'info',
                    title: 'Complimentary Mode On',
                    text: 'Authorization verified. Now select food items; they will be added with 0 value.',
                    timer: 1600,
                    showConfirmButton: false
                });
            }
        });
    });

    $(document).on('click', '.js-toggle-order-item-complimentary', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const orderId = $btn.data('order-id');
        const orderDetailId = $btn.data('order-detail-id');
        const orderDetailIds = String($btn.attr('data-order-detail-ids') || orderDetailId || '');
        const tableId = $btn.data('table-id');
        const orderType = $btn.data('order-type') || 'dine_in';
        const productName = $btn.data('product-name') || 'this food';
        const isCurrentlyComplimentary = Number($btn.data('is-complimentary')) === 1;
        const makeComplimentary = !isCurrentlyComplimentary;

        Swal.fire({
            title: makeComplimentary ? 'Make Complimentary?' : 'Return to Normal?',
            text: makeComplimentary
                ? productName + ' will become complimentary and its food/addon value will be changed to 0.'
                : productName + ' will return to normal using its current food/addon price.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: makeComplimentary ? 'Yes, make complimentary' : 'Yes, make normal',
            cancelButtonText: 'Cancel',
            confirmButtonColor: makeComplimentary ? '#198754' : '#6c757d'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            const originalHtml = $btn.html();

            const submitComplimentaryToggle = function(pass, note) {
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

                $.post("{{ route('pos.order_item.complimentary') }}", {
                    order_id: orderId,
                    order_detail_id: orderDetailId,
                    order_detail_ids: orderDetailIds,
                    is_complimentary: makeComplimentary ? 1 : 0,
                    action_password: pass,
                    complimentary_note: makeComplimentary ? (note || '') : ''
                }).done(function(res) {
                    if (res.status !== 'success') {
                        $btn.prop('disabled', false).html(originalHtml);
                        Swal.fire('Error', res.message || 'Food status update failed.', 'error');
                        return;
                    }

                    reloadActiveOrderOffcanvas(orderId, tableId, orderType)
                        .done(function() {
                            Swal.fire({
                                icon: 'success',
                                title: makeComplimentary ? 'Complimentary' : 'Normal Food',
                                text: res.message || (makeComplimentary
                                    ? 'Food converted to complimentary successfully.'
                                    : 'Food returned to normal successfully.'),
                                timer: 1100,
                                showConfirmButton: false
                            });
                        })
                        .fail(function() {
                            window.location.reload();
                        });
                }).fail(function(xhr) {
                    $btn.prop('disabled', false).html(originalHtml);
                    Swal.fire('Error', xhr.responseJSON?.message || 'Food status update failed.', 'error');
                });
            };

            if (makeComplimentary) {
                getComplimentaryPasswordAndNote(function(pass, note) {
                    submitComplimentaryToggle(pass, note);
                });
            } else {
                getPosActionPassword(function(pass) {
                    submitComplimentaryToggle(pass, '');
                });
            }
        });
    });


    $(document).on('click', '#btnContinueOrdering', function() {
        const tId = $(this).data('table-id');
        const orderId = $(this).data('order-id');
        const waiterId = $(this).data('waiter-id');
        const waiterName = $(this).data('waiter-name');
        const customerId = $(this).data('customer-id');
        const customerName = $(this).data('customer-name');

        const orderType = $(this).data('order-type') || 'dine_in';
        const orderLabel = $(this).data('order-label') || $('#ocTableNum').text();
        const deliveryPartner = $(this).data('delivery-partner') || '';
        const deliveryPartnerName = $(this).data('delivery-partner-name') || '';

        currentOrder.table_id = tId || null;
        currentOrder.table_name = orderLabel;
        currentOrder.order_id = orderId;
        currentOrder.order_type = orderType;
        currentOrder.delivery_partner = deliveryPartner || (orderType === 'delivery' ? 'inhouse' : '');
        currentOrder.delivery_partner_name = orderType === 'delivery' ? deliveryPartnerName : '';
        currentOrder.is_complimentary_order = 0;
        isComplimentaryMode = false;
        posComplimentaryActionPassword = '';
        posComplimentaryNote = '';
        isOffcanvasComplimentaryMode = false;

        currentOrder.waiter_id = waiterId ? waiterId : null;
        currentOrder.waiter_name = waiterName ? waiterName : '';

        if(customerId) {
            currentOrder.is_walk_in = 0;
            currentOrder.customer_id = customerId;
            currentOrder.customer_name = customerName;
        } else {
            currentOrder.is_walk_in = 1;
            currentOrder.customer_id = null;
            currentOrder.customer_name = '';
        }

        var ocElement = document.getElementById('tableOrderOffcanvas');
        if (ocElement) {
            var ocInstance = bootstrap.Offcanvas.getInstance(ocElement);
            if (ocInstance) ocInstance.hide();
        }

        showStep(2);
        loadCart();
    });

    $(document).on('click', '.progga-pos-filter-btn', function() {
        $('.progga-pos-filter-btn').removeClass('active');
        $(this).addClass('active');

        let filterValue = $(this).data('table-filter');

        if(filterValue === 'all') {
            $('.progga-pos-table-card').fadeIn('fast');
        } else {
            $('.progga-pos-table-card').hide();
            $('.progga-pos-table-card[data-status="' + filterValue + '"]').fadeIn('fast');
        }
    });

    $(document).on('click', '#posCartFab', function() {
        $('.progga-pos-cart').addClass('show');
        $('#posMobileBackdrop').fadeIn('fast');
        $('body').addClass('progga-pos-overflow-lock');
    });

    $(document).on('click', '#posMobileCartClose, #posMobileBackdrop', function() {
        $('.progga-pos-cart').removeClass('show');
        $('#posMobileBackdrop').fadeOut('fast');
        $('body').removeClass('progga-pos-overflow-lock');
    });

    // POS workflow note.
    $(document).on('click', '.btnEditSession', function(e) {
        e.preventDefault();

        let sessionId = $(this).attr('data-id');
        let sessionStatus = $(this).attr('data-status').trim(); // POS workflow note.

        $('#sessionModalTitle').html('<i class="bi bi-pencil-square me-2"></i>Edit Session #' + sessionId);
        $('#inlineEditSessionId').val(sessionId);
        $('#inlineEditStartTime').val($(this).attr('data-start'));
        $('#inlineEditEndTime').val($(this).attr('data-end'));

        // POS workflow note.
        $('#inlineEditStatus').val(sessionStatus).trigger('change');

        $('#sessionListView').hide();
        $('#sessionEditView').fadeIn('fast');
    });

    // POS workflow note.
    $(document).on('click', '#btnCancelEdit', function(e) {
        e.preventDefault();
        $('#sessionModalTitle').html('<i class="bi bi-table me-2"></i>Work Period Sessions');
        $('#sessionEditView').hide();
        $('#sessionListView').fadeIn('fast');
    });

    // POS workflow note.
    $('#inlineEditSessionForm').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize();

        let btn = $(this).find('button[type="submit"]');
        let originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> Saving...');

        $.post("{{ route('pos.session.update') }}", formData, function(res) {
            if(res.status === 'success') {
                Swal.fire('Updated!', res.message, 'success').then(() => {
                    location.reload();
                });
            }
        }).fail(function(xhr) {
            const response = xhr.responseJSON || {};
            const title = response.code === 'session_close_blocked' ? 'Cannot End Session' : 'Error';
            const message = response.message || 'Something went wrong!';
            Swal.fire(title, message, 'error');
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // POS workflow note.
    $('#sessionHistoryModal').on('hidden.bs.modal', function () {
        $('#sessionModalTitle').html('<i class="bi bi-table me-2"></i>Work Period Sessions');
        $('#sessionEditView').hide();
        $('#sessionListView').show();
    });


})();
</script>
@else
<script>
(function showPosClosedPopup() {
    const popupMessage = @json($posClosedMessage ?? 'The restaurant is currently closed. Orders will be accepted from 12:01 PM.');
    const orderWindow = @json(
        'Order hours: ' .
        \Carbon\Carbon::createFromFormat('H:i', $posOpeningTime ?? '12:01')->format('h:i A') .
        ' – ' .
        \Carbon\Carbon::createFromFormat('H:i', $posClosingTime ?? '06:00')->format('h:i A')
    );
    const dashboardUrl = @json(route('home'));
    const openSessionId = @json($activeSession->id ?? null);
    const openSessionStart = @json(optional($activeSession)->start_time ? $activeSession->start_time->format('Y-m-d H:i:s') : null);
    const forceUnfinishedPrompt = @json((bool) ($forceUnfinishedSessionPrompt ?? false));
    const ackKey = 'pos_acknowledged_session_id';

    function acknowledgedSessionId() {
        try { return window.sessionStorage.getItem(ackKey); } catch (ignore) { return null; }
    }

    function acknowledge(sessionId) {
        try { window.sessionStorage.setItem(ackKey, String(sessionId)); } catch (ignore) {}
    }

    function formatStart(startText) {
        if (!startText) return '';
        const parsed = new Date(String(startText).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return startText;
        return parsed.toLocaleString([], {
            year: 'numeric', month: 'short', day: '2-digit',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function resolveUnfinished(action) {
        $.post(@json(route('pos.session.start')), { action: action })
            .done(function(res) {
                if (res && res.status === 'success') {
                    acknowledge(res.session_id);
                    window.Swal.fire({
                        icon: 'success',
                        title: action === 'continue' ? 'Session Continued' : 'New Session Started',
                        text: res.message || 'POS session is ready.',
                        timer: 800,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.reload();
                    });
                    return;
                }

                window.Swal.fire('Error', (res && res.message) || 'Could not update the POS session.', 'error');
            })
            .fail(function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Could not update the POS session.';
                window.Swal.fire('Error', message, 'error');
            });
    }

    function openUnfinishedPrompt() {
        const readableStart = formatStart(openSessionStart);
        window.Swal.fire({
            icon: 'warning',
            title: 'Unfinished POS Session',
            html: 'A previous POS session is still open.'
                + (readableStart ? '<br><strong>Started:</strong> ' + readableStart : '')
                + '<br><br>Do you want to continue it or start a new session?',
            showCancelButton: false,
            showDenyButton: true,
            confirmButtonText: '<i class="bi bi-arrow-repeat"></i> Continue Previous Session',
            denyButtonText: '<i class="bi bi-play-circle-fill"></i> Start New Session',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(function(result) {
            if (result.isConfirmed) {
                resolveUnfinished('continue');
            } else if (result.isDenied) {
                resolveUnfinished('new');
            }
        });
    }

    function openClosedNotification() {
        if (!window.Swal) {
            setTimeout(openClosedNotification, 50);
            return;
        }

        // Shared Manager sessions are accepted automatically in every browser.
        const needsUnfinishedChoice = !!openSessionId && forceUnfinishedPrompt;

        if (needsUnfinishedChoice) {
            openUnfinishedPrompt();
            return;
        }

        window.Swal.fire({
            icon: 'warning',
            title: 'Restaurant Is Currently Closed',
            text: popupMessage,
            footer: orderWindow,
            confirmButtonText: 'Go to Dashboard',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCloseButton: false
        }).then(function () {
            window.location.href = dashboardUrl;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openClosedNotification);
    } else {
        openClosedNotification();
    }
})();
</script>
@endif
@endsection
