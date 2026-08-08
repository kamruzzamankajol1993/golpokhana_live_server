/**
 * Progga RMS — Kitchen Dashboard
 * progga-kitchen.js
 */

(function () {
  'use strict';

  let refreshInterval = null;
  const REFRESH_SECONDS = 30;
  let countdown = REFRESH_SECONDS;

  /* ─── Timer display ─── */
  function updateCountdown() {
    const el = document.getElementById('kitchenCountdown');
    if (el) el.textContent = countdown + 's';
    if (countdown <= 0) {
      countdown = REFRESH_SECONDS;
      refreshKitchenData();
    } else {
      countdown--;
    }
  }

  /* ─── Order elapsed timers ─── */
  function updateElapsedTimers() {
    document.querySelectorAll('[data-order-started]').forEach(function (el) {
      const startedAt = parseInt(el.dataset.orderStarted);
      if (!startedAt) return;
      const elapsed = Math.floor((Date.now() - startedAt) / 60000);
      el.textContent = elapsed + ' min';
      const card = el.closest('.progga-kitchen-card');
      if (card) {
        if (elapsed >= 15) {
          card.classList.add('urgent');
          el.classList.add('urgent');
        } else {
          card.classList.remove('urgent');
          el.classList.remove('urgent');
        }
      }
    });
  }

  /* ─── Status action buttons ─── */
  function initStatusActions() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-kitchen-action]');
      if (!btn) return;
      const action  = btn.dataset.kitchenAction;
      const orderId = btn.dataset.orderId;
      const card    = btn.closest('.progga-kitchen-card');

      btn.disabled = true;
      btn.innerHTML = '<i class="bi bi-hourglass-split progga-animate-spin"></i>';

      setTimeout(function () {
        if (action === 'start') {
          moveCard(card, 'cooking-col');
          btn.disabled = false;
          proggaToast('Order #' + orderId + ' — Cooking started', 'info');
        } else if (action === 'ready') {
          moveCard(card, 'ready-col');
          btn.disabled = false;
          proggaToast('Order #' + orderId + ' — Marked as Ready!', 'success');
        } else if (action === 'deliver') {
          card.style.transition = 'opacity 0.3s, transform 0.3s';
          card.style.opacity    = '0';
          card.style.transform  = 'scale(0.95)';
          setTimeout(function () {
            card.remove();
            updateColCounts();
          }, 300);
          proggaToast('Order #' + orderId + ' — Delivered!', 'success');
        }
        updateColCounts();
      }, 600);
    });
  }

  function moveCard(card, colId) {
    if (!card) return;
    const target = document.getElementById(colId);
    if (!target) return;
    const body = target.querySelector('.progga-kitchen-col-body');
    if (!body) return;
    card.style.transition = 'opacity 0.3s, transform 0.3s';
    card.style.opacity    = '0';
    card.style.transform  = 'translateX(20px)';
    setTimeout(function () {
      body.prepend(card);
      card.style.opacity   = '1';
      card.style.transform = 'translateX(0)';
    }, 300);
  }

  function updateColCounts() {
    var statMap = { 'pending-col': 'statPending', 'cooking-col': 'statCooking', 'ready-col': 'statReady' };
    ['pending-col', 'cooking-col', 'ready-col'].forEach(function (colId) {
      var col = document.getElementById(colId);
      if (!col) return;
      var cards   = col.querySelectorAll('.progga-kitchen-card').length;
      var countEl = col.querySelector('.progga-kitchen-col-count');
      if (countEl) countEl.textContent = cards;
      var statEl  = document.getElementById(statMap[colId]);
      if (statEl) statEl.textContent = cards;
    });
    buildFoodSummary();
  }

  /* ─── Food summary ─── */
  function buildFoodSummary() {
    const body = document.getElementById('kitchenSummaryBody');
    if (!body) return;

    const totals = {};
    document.querySelectorAll('.progga-kitchen-card').forEach(function (card) {
      card.querySelectorAll('.progga-kitchen-item').forEach(function (item) {
        const nameEl = item.querySelector('.progga-kitchen-item-name');
        const qtyEl  = item.querySelector('.progga-kitchen-item-qty');
        if (!nameEl || !qtyEl) return;
        const name = nameEl.textContent.trim();
        const qty  = parseInt(qtyEl.textContent.replace('×', '').trim()) || 0;
        totals[name] = (totals[name] || 0) + qty;
      });
    });

    const entries = Object.entries(totals).sort(function (a, b) { return b[1] - a[1]; });

    const countEl = document.getElementById('kitchenSummaryCount');
    if (countEl) countEl.textContent = entries.length;

    if (entries.length === 0) {
      body.innerHTML = '<div class="progga-kitchen-summary-empty">No active orders</div>';
      return;
    }

    body.innerHTML = entries.map(function (e) {
      return '<div class="progga-kitchen-summary-item">' +
        '<div class="progga-kitchen-summary-name">' + e[0] + '</div>' +
        '<div class="progga-kitchen-summary-qty">' + e[1] + '</div>' +
      '</div>';
    }).join('');
  }

  /* ─── Simulated refresh ─── */
  function refreshKitchenData() {
    const refreshIcon = document.getElementById('kitchenRefreshIcon');
    if (refreshIcon) refreshIcon.classList.add('progga-animate-spin');
    setTimeout(function () {
      if (refreshIcon) refreshIcon.classList.remove('progga-animate-spin');
      proggaToast('Kitchen queue refreshed', 'success');
    }, 800);
  }

  /* ─── Manual refresh button ─── */
  function initManualRefresh() {
    const btn = document.getElementById('kitchenRefreshBtn');
    if (btn) {
      btn.addEventListener('click', function () {
        countdown = REFRESH_SECONDS;
        refreshKitchenData();
      });
    }
  }

  /* ─── Init ─── */
  document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('kitchenBoard')) return;
    initStatusActions();
    initManualRefresh();
    updateColCounts();
    buildFoodSummary();
    setInterval(updateElapsedTimers, 30000);
    updateElapsedTimers();
    refreshInterval = setInterval(updateCountdown, 1000);
  });

})();
