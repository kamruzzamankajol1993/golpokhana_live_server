/**
 * Progga RMS — POS System
 * progga-pos.js
 * UI / navigation only — backend handles all data.
 */

(function () {
  'use strict';

  /* ─── Step navigation ─── */
  function goToStep(n) {
    document.querySelectorAll('.progga-pos-screen').forEach(function (s) { s.classList.remove('active'); });
    var screen = document.getElementById('posStep' + n);
    if (screen) screen.classList.add('active');
    document.querySelectorAll('.progga-pos-step').forEach(function (el) {
      var sn = parseInt(el.dataset.step);
      el.classList.remove('active', 'done');
      if (sn === n) el.classList.add('active');
      if (sn < n)  el.classList.add('done');
    });
  }

  /* ─── Table filter buttons ─── */
  function initTableFilters() {
    document.querySelectorAll('.progga-pos-filter-btn[data-table-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.progga-pos-filter-btn[data-table-filter]').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var filter = btn.dataset.tableFilter;
        document.querySelectorAll('.progga-pos-table-card').forEach(function (card) {
          card.style.display = (filter === 'all' || card.dataset.status === filter) ? '' : 'none';
        });
      });
    });

    /* Available / reserved → open new order modal */
    document.querySelectorAll('.progga-pos-table-card.available, .progga-pos-table-card.reserved').forEach(function (card) {
      card.addEventListener('click', function () {
        openNewOrderModal();
      });
    });

    /* Occupied → offcanvas */
    document.querySelectorAll('.progga-pos-table-card.occupied').forEach(function (card) {
      card.addEventListener('click', function () {
        var order = JSON.parse(card.dataset.order);
        openOccupiedOffcanvas(card.dataset.tableNum, card.dataset.zone, card.dataset.capacity, order);
      });
    });
  }

  /* ─── Takeaway shortcut button ─── */
  function initOrderModeBtns() {
    var takeawayBtn = document.getElementById('modeTakeaway');
    if (!takeawayBtn) return;
    takeawayBtn.addEventListener('click', function () {
      openNewOrderModal();
    });
  }

  /* ─── Open new order modal ─── */
  function openNewOrderModal() {
    var modal = document.getElementById('newOrderModal');
    if (!modal) return;

    /* Reset form to clean state */
    var walkIn = document.getElementById('posWalkIn');
    if (walkIn) walkIn.checked = true;
    var nameEl  = modal.querySelector('[name="customer_name"]');
    var phoneEl = modal.querySelector('[name="customer_phone"]');
    if (nameEl)  nameEl.value = '';
    if (phoneEl) phoneEl.value = '';

    var dineRadio = document.getElementById('posTypeDineIn');
    if (dineRadio) dineRadio.checked = true;

    var gc = document.getElementById('posGuestCount');
    var gh = document.getElementById('posGuestHidden');
    if (gc) gc.textContent = '2';
    if (gh) gh.value = '2';

    new bootstrap.Modal(modal).show();
  }

  /* ─── Start Order — just close modal and proceed ─── */
  function initStartOrderBtn() {
    var btn = document.getElementById('posStartOrderBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var m = bootstrap.Modal.getInstance(document.getElementById('newOrderModal'));
      if (m) m.hide();
      goToStep(2);
    });
  }

  /* ─── Guest counter ─── */
  function initGuestCounter() {
    var minus  = document.getElementById('posGuestMinus');
    var plus   = document.getElementById('posGuestPlus');
    var count  = document.getElementById('posGuestCount');
    var hidden = document.getElementById('posGuestHidden');
    if (!minus || !plus || !count) return;

    function setGuests(n) {
      n = Math.max(1, Math.min(20, n));
      count.textContent = n;
      if (hidden) hidden.value = n;
    }

    minus.addEventListener('click', function () { setGuests(parseInt(count.textContent) - 1); });
    plus.addEventListener('click',  function () { setGuests(parseInt(count.textContent) + 1); });
  }

  /* ─── Category filter ─── */
  function initCategoryFilter() {
    document.querySelectorAll('.progga-pos-cat-item').forEach(function (item) {
      item.addEventListener('click', function () {
        document.querySelectorAll('.progga-pos-cat-item').forEach(function (i) { i.classList.remove('active'); });
        item.classList.add('active');
        var search = document.getElementById('posFoodSearch');
        if (search) search.value = '';
        showFoodsByCategory(item.dataset.catId);
      });
    });
  }

  function showFoodsByCategory(catId) {
    document.querySelectorAll('#posFoodGrid .progga-pos-food-card').forEach(function (card) {
      card.style.display = (card.dataset.catId === catId) ? '' : 'none';
    });
  }

  /* ─── Food search ─── */
  function initFoodSearch() {
    var input = document.getElementById('posFoodSearch');
    if (!input) return;
    input.addEventListener('input', function () {
      var q = input.value.toLowerCase().trim();
      if (!q) {
        var active = document.querySelector('.progga-pos-cat-item.active');
        showFoodsByCategory(active ? active.dataset.catId : '1');
        return;
      }
      document.querySelectorAll('#posFoodGrid .progga-pos-food-card').forEach(function (card) {
        var name = card.querySelector('.progga-pos-food-name');
        card.style.display = (name && name.textContent.toLowerCase().includes(q)) ? '' : 'none';
      });
    });
  }

  /* ─── Back to tables ─── */
  function initBackButtons() {
    var back = document.getElementById('posBackToTables');
    if (back) back.addEventListener('click', function () { goToStep(1); });
  }

  /* ─── Mobile cart slide-up ─── */
  function initMobileCart() {
    var fab      = document.getElementById('posCartFab');
    var cart     = document.querySelector('.progga-pos-cart');
    var backdrop = document.getElementById('posMobileBackdrop');
    var closeBtn = document.getElementById('posMobileCartClose');
    if (!fab || !cart) return;

    function openCart() {
      cart.classList.add('pos-cart-open');
      if (backdrop) backdrop.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    function closeCart() {
      cart.classList.remove('pos-cart-open');
      if (backdrop) backdrop.classList.remove('active');
      document.body.style.overflow = '';
    }

    fab.addEventListener('click', openCart);
    if (closeBtn) closeBtn.addEventListener('click', closeCart);
    if (backdrop) backdrop.addEventListener('click', closeCart);
  }

  /* ─── Pay button ─── */
  function initPayBtn() {
    var btn = document.getElementById('posGoPayment');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var modal = document.getElementById('paymentModal');
      if (modal) new bootstrap.Modal(modal).show();
    });
  }

  /* ─── Occupied table offcanvas (reads inline data-order JSON) ─── */
  function fmt(n) { return '৳' + parseFloat(n).toFixed(2); }

  function openOccupiedOffcanvas(tableNum, zone, capacity, order) {
    document.getElementById('ocTableNum').textContent  = tableNum;
    document.getElementById('ocTableMeta').textContent = zone + ' · ' + capacity + ' seats';
    document.getElementById('ocChips').innerHTML =
      '<span class="progga-oc-chip"><i class="bi bi-receipt"></i> ' + order.orderId + '</span>' +
      '<span class="progga-oc-chip"><i class="bi bi-person"></i> ' + order.waiter + '</span>' +
      '<span class="progga-oc-chip"><i class="bi bi-clock"></i> ' + order.elapsed + ' min</span>';

    var bodyHtml = '<div class="progga-oc-section-label">Current Order</div>';
    var subtotal = 0;
    order.kots.forEach(function (kot) {
      bodyHtml += '<div class="progga-oc-kot"><div class="progga-oc-kot-head">' +
        '<span class="progga-oc-kot-label">' + kot.id + '</span>' +
        '<span class="progga-oc-kot-time"><i class="bi bi-clock me-1"></i>' + kot.time + '</span></div>';
      kot.items.forEach(function (item) {
        var line = item.qty * item.price;
        subtotal += line;
        bodyHtml += '<div class="progga-oc-item">' +
          '<span class="progga-oc-item-name">' + item.name + '</span>' +
          '<span class="progga-oc-item-qty">×' + item.qty + '</span>' +
          '<span class="progga-oc-item-price">' + fmt(line) + '</span></div>';
      });
      bodyHtml += '</div>';
    });
    document.getElementById('ocBody').innerHTML = bodyHtml;

    var tax   = subtotal * 0.05;
    var total = subtotal + tax;
    document.getElementById('ocTotals').innerHTML =
      '<div class="progga-oc-total-row"><span>Subtotal</span><span>' + fmt(subtotal) + '</span></div>' +
      '<div class="progga-oc-total-row"><span>Tax (5%)</span><span>' + fmt(tax) + '</span></div>' +
      '<div class="progga-oc-total-row grand"><span>Total</span><span>' + fmt(total) + '</span></div>';

    var oc = bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('tableOrderOffcanvas'));

    document.getElementById('ocAddMoreBtn').onclick = function () {
      oc.hide();
      goToStep(2);
    };

    document.getElementById('ocPayBtn').onclick = function () {
      oc.hide();
      var modal = document.getElementById('paymentModal');
      if (modal) new bootstrap.Modal(modal).show();
    };

    oc.show();
  }

  /* ─── Init ─── */
  document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('posStep1')) return;
    showFoodsByCategory('1');
    goToStep(1);
    initTableFilters();
    initOrderModeBtns();
    initStartOrderBtn();
    initGuestCounter();
    initCategoryFilter();
    initFoodSearch();
    initBackButtons();
    initPayBtn();
    initMobileCart();
  });

})();
