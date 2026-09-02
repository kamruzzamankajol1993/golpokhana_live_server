<style>
  .progga-pos-cats {
    height: 100%;
    min-height: 0;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: rgba(213, 170, 101, .75) rgba(255, 255, 255, .08);
  }

  .progga-pos-cats::-webkit-scrollbar {
    width: 7px;
  }

  .progga-pos-cats::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, .08);
  }

  .progga-pos-cats::-webkit-scrollbar-thumb {
    background: rgba(213, 170, 101, .75);
    border-radius: 10px;
  }

  .progga-pos-cat-name {
    font-size: 14px !important;
    font-weight: 900 !important;
    line-height: 1.18 !important;
  }

  /* POS category custom tooltip */
  .progga-pos-category-tooltip {
    --tooltip-bg-start: #253d31;
    --tooltip-bg-end: #15251d;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 999999;
    display: block;
    min-width: 110px;
    max-width: min(280px, calc(100vw - 30px));
    padding: 10px 14px;
    border: 1px solid rgba(255, 255, 255, .14);
    border-radius: 10px;
    background: linear-gradient(135deg, var(--tooltip-bg-start), var(--tooltip-bg-end));
    box-shadow: 0 12px 30px rgba(5, 15, 10, .30),
                0 2px 8px rgba(5, 15, 10, .18);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.35;
    letter-spacing: .1px;
    text-align: left;
    white-space: normal;
    overflow-wrap: anywhere;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translate(-7px, -50%) scale(.96);
    transform-origin: left center;
    transition: opacity .16s ease,
                transform .16s ease,
                visibility .16s ease;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
  }

  .progga-pos-category-tooltip::before {
    content: '';
    position: absolute;
    top: 50%;
    left: -7px;
    width: 13px;
    height: 13px;
    border-left: 1px solid rgba(255, 255, 255, .10);
    border-bottom: 1px solid rgba(255, 255, 255, .10);
    background: var(--tooltip-bg-start);
    transform: translateY(-50%) rotate(45deg);
  }

  .progga-pos-category-tooltip.is-visible {
    opacity: 1;
    visibility: visible;
    transform: translate(0, -50%) scale(1);
  }

  .progga-pos-category-tooltip.is-left {
    transform: translate(7px, -50%) scale(.96);
    transform-origin: right center;
  }

  .progga-pos-category-tooltip.is-left::before {
    right: -7px;
    left: auto;
    border: 0;
    border-top: 1px solid rgba(255, 255, 255, .10);
    border-right: 1px solid rgba(255, 255, 255, .10);
    background: var(--tooltip-bg-end);
  }

  .progga-pos-category-tooltip.is-left.is-visible {
    transform: translate(0, -50%) scale(1);
  }

  @media (max-width: 991.98px), (hover: none) {
    .progga-pos-category-tooltip {
      display: none !important;
    }
  }

  @media (max-width: 768px) {
    .progga-pos-cats {
      height: auto !important;
      min-height: auto;
      overflow-x: auto !important;
      overflow-y: hidden !important;
    }

    .progga-pos-cats::-webkit-scrollbar {
      display: none;
    }
  }
</style>

<div class="progga-pos-screen progga-pos-order-screen" id="posStep2">
    <div class="progga-pos-cats" id="posCatList">
      <div class="progga-pos-cat-item active" data-cat-id="" data-category-name="All Items"><div class="progga-pos-cat-emoji">🍽️</div><div class="progga-pos-cat-name">All Items</div></div>
      @foreach($categories as $cat)
      <div class="progga-pos-cat-item" data-cat-id="{{ $cat->id }}" data-category-name="{{ $cat->name }}"><div class="progga-pos-cat-emoji">🥘</div><div class="progga-pos-cat-name">{{ $cat->name }}</div></div>
      @endforeach
    </div>

    <div class="progga-pos-food-panel">
      <div class="progga-pos-food-toolbar">
        <div class="progga-pos-table-indicator"><i class="bi bi-layout-wtf"></i> Table: <strong id="posSelectedTableMeta">—</strong></div>
        <div class="progga-search progga-pos-food-search-wrap">
          <i class="bi bi-search progga-search-icon"></i>
          <input type="text" class="progga-form-control" id="posFoodSearch" placeholder="Search food items...">
        </div>
        <button class="progga-btn progga-btn-outline progga-btn-sm" id="posBackToTables" type="button"><i class="bi bi-arrow-left"></i> Change Table</button>
      </div>

      <div class="progga-pos-food-grid" id="posFoodGrid">
          </div>

      <div class="pos-cart-fab" id="posCartFab" style="display: none;">
        <span class="pos-cart-fab-count" id="fabCartCount">0</span>
        <span class="pos-cart-fab-label">View Cart</span>
        <span class="pos-cart-fab-total" id="fabCartTotal">৳0</span>
        <i class="bi bi-chevron-up"></i>
      </div>
    </div>

    <div class="progga-pos-cart">
      <div class="pos-cart-handle-bar d-md-none"></div>

      <div class="progga-pos-cart-header">
        <div class="progga-pos-cart-title">
            <i class="bi bi-receipt"></i> Current Order <span class="badge bg-light text-dark ms-2" id="headerCartCount" style="display:none; font-size: 12px;">0</span>
        </div>
        <button class="pos-cart-mobile-close d-md-none" id="posMobileCartClose" type="button" style="background: none; border: none; color: white;"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="pos-cart-info" id="posCartMeta">
        <div class="pos-ci-row1">
          <span class="pos-ci-type" id="metaType">Dine-In</span>
          <span id="metaDeliveryPartner" style="display:none; margin-left:10px; font-size:12px; font-weight:800;">
            <i class="bi bi-truck"></i> Delivery Partner: <span id="metaDeliveryPartnerName">—</span>
          </span>
        </div>
        <div class="pos-ci-row2">
          <div class="pos-ci-customer">
            <div class="pos-ci-avatar"><i class="bi bi-person-fill"></i></div>
            <span class="pos-ci-name" id="metaCustomer">Walk-in Customer</span>
          </div>
          <span class="pos-ci-waiter" id="metaWaiter"><i class="bi bi-person-badge"></i> Unassigned</span>
        </div>
      </div>

      <div id="posCartBody" style="display:flex; flex-direction:column; flex:1; overflow-y:auto;">
          </div>
    </div>
</div>

<div class="pos-mobile-backdrop" id="posMobileBackdrop" style="display: none;"></div>

<div class="progga-pos-category-tooltip" id="proggaPosCategoryTooltip" role="tooltip" aria-hidden="true"></div>

<script>
(function () {
  function initPosCategoryTooltip() {
    const tooltip = document.getElementById('proggaPosCategoryTooltip');
    const categoryList = document.getElementById('posCatList');

    if (!tooltip || !categoryList || categoryList.dataset.tooltipReady === '1') {
      return;
    }

    categoryList.dataset.tooltipReady = '1';
    let activeItem = null;

    function positionTooltip(item) {
      const itemRect = item.getBoundingClientRect();
      const gap = 13;
      const viewportPadding = 12;

      tooltip.classList.remove('is-left');
      tooltip.style.left = (itemRect.right + gap) + 'px';
      tooltip.style.top = (itemRect.top + (itemRect.height / 2)) + 'px';

      const tooltipRect = tooltip.getBoundingClientRect();
      let left = itemRect.right + gap;

      if (left + tooltipRect.width + viewportPadding > window.innerWidth) {
        left = itemRect.left - tooltipRect.width - gap;
        tooltip.classList.add('is-left');
      }

      left = Math.max(viewportPadding, Math.min(left, window.innerWidth - tooltipRect.width - viewportPadding));

      let top = itemRect.top + (itemRect.height / 2);
      const halfHeight = tooltipRect.height / 2;
      top = Math.max(viewportPadding + halfHeight, Math.min(top, window.innerHeight - viewportPadding - halfHeight));

      tooltip.style.left = left + 'px';
      tooltip.style.top = top + 'px';
    }

    function showTooltip(item) {
      if (window.matchMedia('(max-width: 991.98px), (hover: none)').matches) {
        return;
      }

      const categoryName = (item.dataset.categoryName || '').trim();
      if (!categoryName) {
        return;
      }

      activeItem = item;
      tooltip.textContent = categoryName;
      tooltip.setAttribute('aria-hidden', 'false');
      tooltip.classList.add('is-visible');
      positionTooltip(item);
    }

    function hideTooltip() {
      activeItem = null;
      tooltip.classList.remove('is-visible', 'is-left');
      tooltip.setAttribute('aria-hidden', 'true');
    }

    categoryList.querySelectorAll('.progga-pos-cat-item').forEach(function (item) {
      item.addEventListener('mouseenter', function () {
        showTooltip(item);
      });

      item.addEventListener('mouseleave', hideTooltip);

      item.addEventListener('focusin', function () {
        showTooltip(item);
      });

      item.addEventListener('focusout', hideTooltip);
    });

    categoryList.addEventListener('scroll', function () {
      if (activeItem) {
        positionTooltip(activeItem);
      }
    }, { passive: true });

    window.addEventListener('resize', hideTooltip, { passive: true });
    window.addEventListener('scroll', hideTooltip, { passive: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPosCategoryTooltip);
  } else {
    initPosCategoryTooltip();
  }
})();
</script>
