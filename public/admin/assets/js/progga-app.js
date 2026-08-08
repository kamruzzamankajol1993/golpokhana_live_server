/**
 * Progga RMS — Main Application JS
 * progga-app.js
 */

(function () {
  'use strict';

  /* ─── Select2 Global Init ─── */
  function initSelect2(context) {
    if (typeof $.fn.select2 === 'undefined') return;
    /* Target all <select> elements not already initialized and not inside a modal */
    var $selects = $('select:not(.select2-hidden-accessible):not([data-no-select2])', context || document);
    $selects.not('.modal select, .modal-body select').each(function () {
      $(this).select2({
        theme: 'progga-theme',
        width: '100%',
        placeholder: $(this).data('placeholder') || $(this).find('option[value=""]').text() || 'Select an option',
        allowClear: $(this).data('allow-clear') !== false
      });
    });
  }
  window.proggaInitSelect2 = initSelect2;

  /* ─── Sidebar Toggle ─── */
  function initSidebar() {
    const toggleBtn  = document.querySelector('.progga-navbar-toggle');
    const sidebar    = document.querySelector('.progga-sidebar');
    const overlay    = document.querySelector('.progga-sidebar-overlay');
    if (!toggleBtn || !sidebar) return;

    toggleBtn.addEventListener('click', function () {
      const isMobile = window.innerWidth <= 768;
      if (isMobile) {
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
      } else {
        sidebar.classList.toggle('progga-sidebar-collapsed');
        document.querySelector('.progga-navbar').classList.toggle('progga-navbar-expanded');
        document.querySelector('.progga-main').classList.toggle('progga-main-expanded');
      }
    });

    if (overlay) {
      overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
      });
    }
  }

  /* ─── Active Nav Link ─── */
  function setActiveNav() {
    const path = window.location.pathname.split('/').pop();
    document.querySelectorAll('.progga-nav-link').forEach(function (link) {
      const href = link.getAttribute('href');
      if (href && href === path) {
        link.classList.add('active');
        const sub = link.closest('.progga-nav-sub');
        if (sub) sub.classList.add('open');
      }
    });
  }
  window.proggaSetActiveNav = setActiveNav;

  /* ─── Sub-menu Toggle ─── */
  function initSubMenus() {
    document.querySelectorAll('.progga-nav-link[data-submenu]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = link.dataset.submenu;
        const sub = document.getElementById(targetId);
        if (!sub) return;
        sub.classList.toggle('open');
        const icon = link.querySelector('.progga-submenu-arrow');
        if (icon) icon.style.transform = sub.classList.contains('open') ? 'rotate(90deg)' : '';
      });
    });
  }

  /* ─── Toggle Switch ─── */
  function initToggles() {
    document.querySelectorAll('.progga-toggle input').forEach(function (input) {
      function update() {
        const label = input.closest('.progga-toggle').querySelector('.progga-toggle-label');
        if (label) {
          label.textContent = input.checked
            ? (input.dataset.on  || 'Active')
            : (input.dataset.off || 'Inactive');
        }
      }
      update();
      input.addEventListener('change', update);
    });
  }

  /* ─── Image Preview ─── */
  function initImagePreviews() {
    document.querySelectorAll('.progga-upload-zone').forEach(function (zone) {
      const input   = zone.querySelector('input[type="file"]') || zone.nextElementSibling;
      const preview = zone.closest('.progga-form-group')?.querySelector('.progga-img-preview');

      zone.addEventListener('click', function () { if (input) input.click(); });

      if (input) {
        input.addEventListener('change', function () {
          const file = input.files[0];
          if (!file || !preview) return;
          const reader = new FileReader();
          reader.onload = function (e) { preview.src = e.target.result; preview.style.display = 'block'; };
          reader.readAsDataURL(file);
        });
      }

      zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('dragover'); });
      zone.addEventListener('dragleave', function () { zone.classList.remove('dragover'); });
      zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (!file || !preview) return;
        const reader = new FileReader();
        reader.onload = function (ev) { preview.src = ev.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(file);
      });
    });
  }

  /* ─── Search Filter ─── */
  function initTableSearch() {
    document.querySelectorAll('.progga-table-search').forEach(function (input) {
      const tableId = input.dataset.table;
      const table   = document.getElementById(tableId);
      if (!table) return;

      input.addEventListener('input', function () {
        const q = input.value.toLowerCase().trim();
        table.querySelectorAll('tbody tr').forEach(function (row) {
          row.style.display = q === '' || row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
      });
    });
  }

  /* ─── Toast Notifications ─── */
  window.proggaToast = function (message, type) {
    type = type || 'success';
    let container = document.querySelector('.progga-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'progga-toast-container';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' };
    toast.className = 'progga-toast ' + type;
    toast.innerHTML = '<i class="bi ' + (icons[type] || icons.success) + '"></i> ' + message;
    container.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(20px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
  };

  /* ─── Confirm Delete ─── */
  function initDeleteConfirm() {
    document.querySelectorAll('[data-delete-confirm]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const modal = document.getElementById('deleteConfirmModal');
        if (!modal) return;
        const label = btn.dataset.deleteLabel || 'this item';
        const modalBody = modal.querySelector('.progga-delete-label');
        if (modalBody) modalBody.textContent = label;
        modal.querySelector('.progga-confirm-delete-btn')?.setAttribute('data-id', btn.dataset.id || '');
        new bootstrap.Modal(modal).show();
      });
    });
  }

  /* ─── Edit Modal Pre-fill ─── */
  window.proggaOpenEdit = function (data, modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    Object.keys(data).forEach(function (key) {
      const el = modal.querySelector('[name="' + key + '"]');
      if (!el) return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = el.value == data[key];
      } else if (el.tagName === 'SELECT') {
        const $el = $(el);
        if ($el.data('select2')) {
          $el.val(data[key]).trigger('change');
        } else {
          el.value = data[key];
        }
      } else {
        el.value = data[key];
      }
    });
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    modal.addEventListener('shown.bs.modal', function () { initSelect2(); }, { once: true });
  };

  /* ─── Modal Select2 re-init on show ─── */
  function initModalSelect2() {
    document.querySelectorAll('.modal').forEach(function (modal) {
      modal.addEventListener('shown.bs.modal', function () {
        $(modal).find('select:not(.select2-hidden-accessible):not([data-no-select2])').each(function () {
          $(this).select2({
            theme: 'progga-theme',
            width: '100%',
            placeholder: $(this).data('placeholder') || $(this).find('option[value=""]').text() || 'Select an option',
            allowClear: $(this).data('allow-clear') !== false,
            dropdownParent: $(modal)
          });
        });
      });
    });
  }

  /* ─── Password Toggle ─── */
  function initPasswordToggle() {
    document.querySelectorAll('.progga-pwd-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const input = btn.closest('.progga-pwd-field').querySelector('input');
        if (!input) return;
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        const icon = btn.querySelector('i');
        if (icon) {
          icon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
        }
      });
    });
  }

  /* ─── Password Strength ─── */
  function initPasswordStrength() {
    document.querySelectorAll('input[data-strength]').forEach(function (input) {
      const barId  = input.dataset.strength;
      const bar    = document.getElementById(barId);
      if (!bar) return;
      input.addEventListener('input', function () {
        const val = input.value;
        bar.className = 'progga-pwd-bar';
        if (val.length === 0) { bar.style.width = '0'; return; }
        let score = 0;
        if (val.length >= 8)    score++;
        if (/[A-Z]/.test(val))  score++;
        if (/[0-9]/.test(val))  score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        if (score <= 1)      bar.classList.add('weak');
        else if (score <= 2) bar.classList.add('medium');
        else                 bar.classList.add('strong');
      });
    });
  }

  /* ─── OTP Input ─── */
  function initOtpInput() {
    const inputs = document.querySelectorAll('.progga-otp-input');
    if (!inputs.length) return;
    inputs.forEach(function (input, i) {
      input.addEventListener('input', function () {
        input.value = input.value.slice(-1);
        if (input.value) {
          input.classList.add('filled');
          if (i < inputs.length - 1) inputs[i + 1].focus();
        }
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !input.value && i > 0) {
          inputs[i - 1].focus();
          inputs[i - 1].classList.remove('filled');
        }
      });
      input.addEventListener('paste', function (e) {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        paste.split('').forEach(function (char, j) {
          if (inputs[j]) {
            inputs[j].value = char;
            inputs[j].classList.add('filled');
          }
        });
        if (inputs[paste.length]) inputs[paste.length].focus();
      });
    });
  }

  /* ─── Resend Timer ─── */
  function initResendTimer() {
    const timerEl = document.getElementById('proggaResendTimer');
    const resendBtn = document.getElementById('proggaResendBtn');
    if (!timerEl) return;
    let seconds = parseInt(timerEl.dataset.seconds || '60');
    function tick() {
      timerEl.textContent = seconds + 's';
      if (seconds <= 0) {
        if (resendBtn) { resendBtn.disabled = false; resendBtn.style.opacity = '1'; }
        timerEl.textContent = '';
        return;
      }
      seconds--;
      setTimeout(tick, 1000);
    }
    if (resendBtn) { resendBtn.disabled = true; resendBtn.style.opacity = '0.5'; }
    tick();
  }

  /* ─── Role-based Sidebar ─── */
  function initRoleNav() {
    const role = document.body.dataset.role || 'admin';
    document.querySelectorAll('[data-roles]').forEach(function (el) {
      const roles = el.dataset.roles.split(',').map(r => r.trim());
      if (!roles.includes(role) && !roles.includes('all')) {
        el.style.display = 'none';
      }
    });
  }
  window.proggaInitRoleNav = initRoleNav;

  /* ─── Bootstrap Init ─── */
  function initBootstrap() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(function (el) { new bootstrap.Tooltip(el); });
  }

  /* ─── Init All ─── */
  document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    setActiveNav();
    initRoleNav();
    initSubMenus();
    initToggles();
    initImagePreviews();
    initTableSearch();
    initDeleteConfirm();
    initModalSelect2();
    initPasswordToggle();
    initPasswordStrength();
    initOtpInput();
    initResendTimer();
    initBootstrap();
    if (typeof $ !== 'undefined') initSelect2();
  });

})();
