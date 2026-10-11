<style>
/* RMS-native Accounting styling. Uses the main progga design system. */
.acc-shell{color:var(--progga-text,#273b32);--acc-ink:var(--progga-text,#273b32);--acc-muted:var(--progga-text-muted,#6d7a72);--acc-accent:var(--progga-primary,#21352a);--acc-line:var(--progga-border-light,#e1e8e3)}
.acc-shell .acc-panel{background:var(--progga-bg-white,#fff);border:1px solid var(--acc-line);border-radius:var(--radius-lg,16px);padding:20px;margin-bottom:20px;box-shadow:var(--shadow-sm,0 3px 12px rgba(25,45,32,.035))}
.acc-shell .acc-panel h3{font-size:17px;font-weight:750;color:var(--progga-primary,#21352a);margin-bottom:17px}
.acc-shell .acc-page-title{font-size:24px;font-weight:800;color:var(--progga-primary,#21352a)}
.acc-shell .table{font-size:13px;vertical-align:middle;color:var(--progga-text,#334237)}
.acc-shell .table>thead>tr>th{background:var(--progga-bg,#f5f7f3);color:var(--progga-text,#334237);font-size:12px;font-weight:750;border-color:var(--acc-line);white-space:nowrap}
.acc-shell .table>tbody>tr>td{border-color:var(--acc-line);padding:11px 12px}
.acc-shell .table tbody tr:hover{background:var(--progga-primary-50,rgba(33,53,42,.05))}
.acc-shell label{font-size:13px;font-weight:650;margin-bottom:5px;color:var(--progga-text,#334237)}
.acc-shell .form-control,.acc-shell .form-select{font-size:13px;min-height:38px;border-color:var(--acc-line)}
.acc-shell .form-control:focus,.acc-shell .form-select:focus{border-color:var(--progga-primary,#21352a);box-shadow:0 0 0 3px var(--progga-primary-100,rgba(33,53,42,.1))}
.acc-shell .btn-primary{background:var(--progga-primary,#21352a);border-color:var(--progga-primary,#21352a)}
.acc-shell .btn-primary:hover{background:var(--progga-primary-light,#2e4a3c);border-color:var(--progga-primary-light,#2e4a3c)}
.acc-shell .btn-outline-primary{color:var(--progga-primary,#21352a);border-color:var(--progga-primary,#21352a)}
.acc-shell .btn-outline-primary:hover{background:var(--progga-primary,#21352a);color:#fff}
.acc-shell .acc-hint{font-size:12px;color:var(--acc-muted)}
.acc-shell .acc-stat{font-size:25px;font-weight:800;color:var(--progga-primary,#21352a)}
.acc-shell .badge{font-size:11px}.acc-shell button{font-size:13px}
.acc-shell .acc-action-btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;white-space:nowrap}
.acc-shell .acc-action-btn .bi{font-size:13px;line-height:1}
.acc-shell .acc-action-note{font-size:11px;color:var(--acc-muted);margin-top:4px}
/* Icon-only actions inside Accounting tables: consistent touch size and accessible focus states. */
.acc-shell .table .acc-icon-action{display:inline-flex;align-items:center;justify-content:center;min-width:34px;width:34px;height:34px;min-height:34px;padding:0!important;line-height:1;vertical-align:middle;flex:0 0 auto}
.acc-shell .table .acc-icon-action .bi{font-size:15px;line-height:1}
.acc-shell .table .acc-icon-action:focus-visible{outline:2px solid var(--progga-primary,#21352a);outline-offset:2px}
.acc-shell .table .acc-icon-action:disabled{cursor:not-allowed;opacity:.5}
/* Consistent branded Bootstrap tooltips for icon-only Accounting table actions. */
.acc-action-tooltip{--bs-tooltip-bg:var(--progga-primary,#21352a);--bs-tooltip-color:#fff;--bs-tooltip-opacity:1;--bs-tooltip-max-width:270px}
.acc-action-tooltip .tooltip-inner{font-size:12px;font-weight:600;padding:7px 11px;border-radius:7px;box-shadow:0 4px 16px rgba(0,0,0,.12)}
/* Order List pagination appearance and the same controls for all Accounting lists. */
.acc-shell .acc-ajax-list{position:relative;min-width:0}
.acc-shell .acc-ajax-list.is-loading{opacity:.55;pointer-events:none}
.acc-shell .acc-page-footer{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 12px;border-top:1px solid var(--acc-line)}
.acc-shell .acc-page-info{font-size:12px;font-weight:650;color:var(--acc-muted)}
.acc-shell .acc-pagination{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.acc-shell .acc-pagination a,.acc-shell .acc-pagination span.acc-page-num{display:inline-flex;justify-content:center;align-items:center;gap:5px;min-height:34px;min-width:34px;padding:7px 10px;border:1px solid var(--acc-line);border-radius:8px;background:#fff;color:var(--progga-text,#334237);font-size:12px;font-weight:750;text-decoration:none;transition:background .15s,border-color .15s}
.acc-shell .acc-pagination a:hover{color:var(--progga-primary,#21352a);background:var(--progga-primary-50,rgba(33,53,42,.05));border-color:var(--progga-primary,#21352a)}
.acc-shell .acc-pagination .acc-page-num.active{background:var(--progga-primary,#21352a);border-color:var(--progga-primary,#21352a);color:#fff}
.acc-shell .acc-pagination .disabled{opacity:.4;pointer-events:none}
.acc-shell .acc-pagination .acc-ellipsis{padding:0 4px;color:var(--acc-muted)}
@media(max-width:768px){.acc-shell .acc-panel{padding:14px}.acc-shell .acc-page-footer{justify-content:center;text-align:center}.acc-shell .acc-pagination{justify-content:center}}
@media print{.acc-shell .acc-page-footer,.acc-shell .acc-hide-print{display:none!important}}
</style>


@include('admin.accounting.shared._ajax_pagination_script')

{{-- All Accounting pages share one scoped datepicker, notification and confirmation system. --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const accounting = document.querySelector('.acc-shell');
    if (!accounting) return;
    function initAccountingDates(scope) {
      if (typeof window.flatpickr !== 'function') return;
      scope.querySelectorAll('input[type="date"], input.acc-date').forEach(function (input) {
            if (input._flatpickr) return;
            window.flatpickr(input, { dateFormat: 'Y-m-d', allowInput: true, disableMobile: true, monthSelectorType: 'dropdown' });
      });
    }
    initAccountingDates(accounting);

    // Bootstrap tooltips, not only native `title` popups. Accessible on hover and focus.
    // Initialize newly fetched AJAX rows again after pagination replaces the old table.
    function initAccountingActionTooltips(scope) {
      if (!window.bootstrap || !window.bootstrap.Tooltip) return;
      scope.querySelectorAll('.acc-icon-action').forEach(function (button) {
        const label = (button.getAttribute('data-bs-title') || button.getAttribute('title') || button.getAttribute('aria-label') || '').trim();
        if (!label) return;
        if (!button.hasAttribute('title') && !button.hasAttribute('data-bs-original-title')) {
          button.setAttribute('title', label);
        }
        if (!button.hasAttribute('aria-label')) button.setAttribute('aria-label', label);
        button.setAttribute('data-bs-toggle', 'tooltip');
        button.setAttribute('data-bs-placement', 'top');
        button.setAttribute('data-bs-title', label);
        window.bootstrap.Tooltip.getOrCreateInstance(button, {
          trigger: 'hover focus',
          placement: 'top',
          container: 'body',
          boundary: 'viewport',
          customClass: 'acc-action-tooltip',
          delay: {show: 180, hide: 80}
        });
      });
    }
    initAccountingActionTooltips(accounting);
    document.addEventListener('accounting:pageLoaded', function (event) {
      if (event.detail && event.detail.target) {
        initAccountingDates(event.detail.target);
        initAccountingActionTooltips(event.detail.target);
      }
    });
    // Dispose tooltips before a paginated table is removed to avoid orphan floating labels.
    document.addEventListener('accounting:beforePageReplace', function (event) {
      const target = event.detail && event.detail.target;
      if (!target || !window.bootstrap || !window.bootstrap.Tooltip) return;
      target.querySelectorAll('.acc-icon-action').forEach(function (button) {
        const tooltip = window.bootstrap.Tooltip.getInstance(button);
        if (tooltip) tooltip.dispose();
      });
    });
    if (!window.Swal) return;
    const flash = @json(session('success'));
    const error = @json(session('error'));
    const warning = @json(session('warning'));
    const validation = @json($errors->all());
    const deletionError = @json($errors->first('delete'));
    if (deletionError) {
        // A delete blocked by financial references is an expected safety rule, not a form-input problem.
        Swal.fire({icon:'info', title:'Deletion not allowed', text:deletionError, input:undefined,
            confirmButtonText:'Understood', confirmButtonColor:'#21352a'});
    } else if (validation.length) {
        Swal.fire({icon:'error', title:'Please review the form', text:validation.join('\n'), input:undefined,
            confirmButtonColor:'#21352a'});
    } else if (error) {
        Swal.fire({icon:'error', title:'Action failed', text:error, input:undefined, confirmButtonColor:'#21352a'});
    } else if (warning) {
        Swal.fire({icon:'warning', title:'Attention', text:warning, confirmButtonColor:'#21352a'});
    } else if (flash) {
        Swal.fire({toast:true, position:'top-end', icon:'success', title:flash,
            timer:3000, timerProgressBar:true, showConfirmButton:false});
    }
    accounting.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.accConfirm) return;
        if (form.dataset.accConfirmed === 'yes') { form.dataset.accConfirmed = ''; return; }
        if (!form.checkValidity()) return; // Preserve native HTML5 validation.
        e.preventDefault();
        e.stopImmediatePropagation();
        const isDelete = form.querySelector('input[name="_method"][value="DELETE"], input[name="_method"][value="delete"]')
            || form.dataset.accDanger === 'yes';
        const isDeactivate = form.dataset.accAction === 'deactivate';
        Swal.fire({
            title:isDelete?'Delete this record?':isDeactivate?'Deactivate journal type?':'Confirm accounting action',
            text:form.dataset.accConfirm,
            input:undefined,
            icon:(isDelete || isDeactivate)?'warning':'question',
            showCancelButton:true,
            confirmButtonText:isDelete?'Yes, delete':isDeactivate?'Yes, deactivate':'Confirm',
            confirmButtonColor:isDelete?'#c43e3e':'#21352a',
            cancelButtonColor:'#777', reverseButtons:true, focusCancel:true
        }).then(function(result) {
            if (!result.isConfirmed) return;
            form.dataset.accConfirmed = 'yes';
            // One confirmed submit only; no double popups or duplicate action.
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else HTMLFormElement.prototype.submit.call(form);
        });
    }, true);
    // Future Accounting delete buttons using the existing RMS CSS class are also supported.
    accounting.addEventListener('click', function (e) {
        const button = e.target.closest('.progga-delete-btn');
        if (!button) return;
        const form = button.closest('form');
        if (!form) return;
        // Prevent the RMS global deletion handler from showing a second dialog.
        e.preventDefault();
        e.stopImmediatePropagation();
        if (!form.dataset.accConfirm) form.dataset.accConfirm = 'This action cannot be undone.';
        form.dataset.accDanger = 'yes';
        if (form.checkValidity()) {
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.dispatchEvent(new Event('submit', {bubbles:true,cancelable:true}));
        } else form.reportValidity();
    }, true);
});
</script>
