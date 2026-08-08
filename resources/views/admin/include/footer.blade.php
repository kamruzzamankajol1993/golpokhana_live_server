<footer class="progga-footer" id="proggaFooter">
  <div class="progga-footer-inner">
    <div class="progga-footer-brand">
      <div class="progga-footer-logo">T</div>
      <span class="progga-footer-name">TableTrack</span>
      <span class="progga-footer-version">v1.0.0</span>
    </div>
    <div class="progga-footer-copy">
      &copy; {{ date('Y') }} TableTrack Restaurant Management System &mdash; All rights reserved.
    </div>
    <div class="progga-footer-links">
        @can('dashboard-view')
      <a href="{{ route('home') }}" class="progga-footer-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
      @endcan
      @canany(['report-sales-order-view', 'report-complimentary-orders-view', 'report-payment-type-sales-view', 'report-food-sales-view', 'report-waiter-daily-orders-view'])
      @php
          $reportLandingRoute = auth()->user()->can('report-sales-order-view') ? 'reports.sales_order'
              : (auth()->user()->can('report-complimentary-orders-view') ? 'reports.complimentary_orders'
              : (auth()->user()->can('report-payment-type-sales-view') ? 'reports.payment_type_sales'
              : (auth()->user()->can('report-food-sales-view') ? 'reports.food_sales' : 'reports.waiter_daily_orders')));
      @endphp
      <a href="{{ route($reportLandingRoute) }}" class="progga-footer-link"><i class="bi bi-bar-chart-fill"></i> Reports</a>
      @endcanany
      @can('systemsetting-view')
      <a href="{{ route('settings.index') }}" class="progga-footer-link"><i class="bi bi-gear-fill"></i> Settings</a>
      @endcan
      @can('profile-view')
      <a href="{{ route('profile.edit') }}" class="progga-footer-link"><i class="bi bi-person-circle"></i> Profile</a>
      @endcan
    </div>
  </div>
</footer>
