<?php $current = basename($_SERVER['PHP_SELF']); ?>
<aside class="progga-sidebar" id="proggaSidebar">
  <div class="progga-sidebar-brand">
   <div class="progga-brand-logo" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
        @if(!empty($restaurantSettingIconName))
            <img src="{{ asset('public/'.$restaurantSettingIconName) }}" alt="Icon" style="width: 100%; height: 100%; object-fit: contain;">
        @else
            {{ strtoupper(substr($restaurantSettingName ?? 'P', 0, 1)) }}
        @endif
    </div>
    <div>
      <div class="progga-brand-name">{{ $restaurantSettingName }}</div>
    </div>
  </div>
  <nav class="progga-sidebar-nav">
    <div class="progga-nav-section"><div class="progga-nav-section-label">Overview</div></div>
     @can('dashboard-view')
    <div class="progga-nav-item" >
        <a class="progga-nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
            <i class="bi bi-grid-1x2-fill progga-nav-icon"></i><span>Dashboard</span>
        </a>
    </div>
    @endcan
    @can('pos-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('pos.index') ? 'active' : '' }}" href="{{ route('pos.index') }}">
            <i class="bi bi-display progga-nav-icon"></i><span>Sales</span>
        </a>
    </div>
    @endcan

    @can('kitchen-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('kitchen.*') ? 'active' : '' }}" href="{{ route('kitchen.index') }}">
            <i class="bi bi-fire progga-nav-icon"></i><span>Kitchen Board</span>
        </a>
    </div>
    @endcan

    @php
        $operationMenuOpen = request()->routeIs('pos.sessions.*') || request()->routeIs('pos.kots.*') || request()->routeIs('table-booking.*') || request()->routeIs('food-category.*') || request()->routeIs('cuisine-type.*') || request()->routeIs('allergen.*') || request()->routeIs('course-type.*') || request()->routeIs('food-item.*') || request()->routeIs('order.*') || request()->routeIs('table.*') || request()->routeIs('floor-zone.*') || request()->routeIs('qrcode.*') || request()->routeIs('customer.*') || request()->routeIs('delivery-partner.*') || request()->routeIs('reviews.*') || request()->routeIs('waiter.*');
    @endphp
    @canany(['pos-view','table-booking-view','food-category-view','cuisine-type-view','allergen-view','food-item-view','order-view','table-view','qrcode-view','customer-view','waiter-view'])
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ $operationMenuOpen ? 'active' : '' }}" data-bs-toggle="collapse" href="#operationDropdown" role="button" aria-expanded="{{ $operationMenuOpen ? 'true' : 'false' }}">
            <i class="bi bi-grid-fill progga-nav-icon"></i><span>Operations</span>
            <i class="bi bi-chevron-down ms-auto" style="font-size:11px;"></i>
        </a>
        <div class="collapse {{ $operationMenuOpen ? 'show' : '' }}" id="operationDropdown">
            @can('pos-view')

            <a class="progga-nav-link {{ request()->routeIs('pos.kots.*') ? 'active' : '' }}" href="{{ route('pos.kots.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-receipt-cutoff progga-nav-icon"></i><span>Kot List</span></a>
            @endcan
             @can('order-view')
            <a class="progga-nav-link {{ request()->routeIs('order.index') ? 'active' : '' }}" href="{{ route('order.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-cart-check-fill progga-nav-icon"></i><span>Order List</span></a>
            <a class="progga-nav-link {{ request()->routeIs('order.due_list') ? 'active' : '' }}" href="{{ route('order.due_list') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-wallet-fill progga-nav-icon"></i><span>Due List</span></a>
            @endcan
            @can('table-booking-view')
            <a class="progga-nav-link {{ request()->routeIs('table-booking.*') ? 'active' : '' }}" href="{{ route('table-booking.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-calendar-check progga-nav-icon"></i><span>Table Booking</span></a>
            @endcan

            @can('food-category-view')<a class="progga-nav-link {{ request()->routeIs('food-category.*') ? 'active' : '' }}" href="{{ route('food-category.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-tags-fill progga-nav-icon"></i><span>Food Categories</span></a>@endcan
            @can('cuisine-type-view')<a class="progga-nav-link {{ request()->routeIs('cuisine-type.*') ? 'active' : '' }}" href="{{ route('cuisine-type.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-egg-fried progga-nav-icon"></i><span>Cuisine Types</span></a>@endcan
            @can('allergen-view')<a class="progga-nav-link {{ request()->routeIs('allergen.*') || request()->routeIs('course-type.*') ? 'active' : '' }}" href="{{ route('allergen.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-sliders progga-nav-icon"></i><span>Food Attributes</span></a>@endcan
            @can('food-item-view')<a class="progga-nav-link {{ request()->routeIs('food-item.*') ? 'active' : '' }}" href="{{ route('food-item.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-menu-button-wide-fill progga-nav-icon"></i><span>Food Menu</span></a>@endcan



            @can('table-view')
            <a class="progga-nav-link {{ request()->routeIs('table.index') ? 'active' : '' }}" href="{{ route('table.index') }}#table-section" style="padding-left:42px;font-size:13px;"><i class="bi bi-table progga-nav-icon"></i><span>Tables</span></a>
            <a class="progga-nav-link {{ request()->routeIs("floor-zone.*") ? "active" : "" }}" href="{{ route("floor-zone.index") }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-building progga-nav-icon"></i><span>Floor / Zone</span></a>
            @endcan
            @can('qrcode-view')<a class="progga-nav-link {{ request()->routeIs('qrcode.*') ? 'active' : '' }}" href="{{ route('qrcode.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-qr-code progga-nav-icon"></i><span>Table QR Codes</span></a>@endcan
            @can('customer-view')
            <a class="progga-nav-link {{ request()->routeIs('customer.*') ? 'active' : '' }}" href="{{ route('customer.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-people-fill progga-nav-icon"></i><span>Customers</span></a>
            <a class="progga-nav-link {{ request()->routeIs('delivery-partner.*') ? 'active' : '' }}" href="{{ route('delivery-partner.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-truck progga-nav-icon"></i><span>Delivery Partner</span></a>
            <a class="progga-nav-link {{ request()->routeIs('reviews.*') ? 'active' : '' }}" href="{{ route('reviews.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-chat-square-text-fill progga-nav-icon"></i><span>Feedback List</span></a>
            @endcan
             @can('pos-view')
            <a class="progga-nav-link {{ request()->routeIs('pos.sessions.*') ? 'active' : '' }}" href="{{ route('pos.sessions.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-clock-history progga-nav-icon"></i><span>Session List</span></a>
            @endcan
            @can('waiter-view')<a class="progga-nav-link {{ request()->routeIs('waiter.*') ? 'active' : '' }}" href="{{ route('waiter.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-person-badge-fill progga-nav-icon"></i><span>Waiters</span></a>@endcan
        </div>
    </div>
    @endcanany

 @canany(['inventory-dashboard-view', 'inventory-kitchen-dashboard-view', 'inventory-kitchen-stock-view', 'inventory-view', 'inventory-units-manage', 'inventory-ingredients-manage', 'inventory-recipes-manage', 'inventory-vendors-manage', 'inventory-purchase-voucher-manage', 'inventory-purchase-approve', 'inventory-purchase-create', 'inventory-purchase-receive', 'inventory-kitchen-request-create', 'inventory-kitchen-request-review', 'inventory-kitchen-request-assign', 'inventory-return-post', 'inventory-wastage-view', 'inventory-wastage-create', 'inventory-wastage-edit', 'inventory-wastage-delete', 'inventory-reports-view', 'inventory-transaction-history-view'])
    <div class="progga-nav-section"><div class="progga-nav-section-label">Inventory</div></div>
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ (request()->routeIs('inventory.*') || request()->routeIs('reports.inventory.*')) ? 'active' : '' }}" data-bs-toggle="collapse" href="#inventoryModuleDropdown" role="button" aria-expanded="{{ (request()->routeIs('inventory.*') || request()->routeIs('reports.inventory.*')) ? 'true' : 'false' }}" aria-controls="inventoryModuleDropdown">
            <i class="bi bi-box-seam-fill progga-nav-icon"></i><span>Inventory</span><i class="bi bi-chevron-down ms-auto" style="font-size:11px;"></i>
        </a>
        <div class="collapse {{ (request()->routeIs('inventory.*') || request()->routeIs('reports.inventory.*')) ? 'show' : '' }}" id="inventoryModuleDropdown">
            @if(auth()->user()?->isKitchenManager())
                @can('inventory-kitchen-dashboard-view')
                <a class="progga-nav-link {{ request()->routeIs('inventory.kitchen-dashboard') ? 'active' : '' }}" href="{{ route('inventory.kitchen-dashboard') }}" style="padding-left:42px;font-size:13px;">
                    <i class="bi bi-speedometer2 progga-nav-icon"></i><span>Kitchen Inventory Dashboard</span>
                </a>
                @endcan
            @else
                @can('inventory-dashboard-view')
                <a class="progga-nav-link {{ request()->routeIs('inventory.dashboard') ? 'active' : '' }}" href="{{ route('inventory.dashboard') }}" style="padding-left:42px;font-size:13px;">
                    <i class="bi bi-speedometer2 progga-nav-icon"></i><span>Inventory Dashboard</span>
                </a>
                @endcan
            @endif

            @canany(['inventory-view', 'inventory-kitchen-stock-view'])
            <a class="progga-nav-link {{ request()->routeIs('inventory.stock.*') ? 'active' : '' }}" href="{{ route('inventory.stock.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-boxes progga-nav-icon"></i><span>{{ auth()->user()?->isKitchenManager() ? 'Kitchen Stock' : 'Current Stock' }}</span>
            </a>
            @endcanany

            @canany(['inventory-kitchen-request-create', 'inventory-kitchen-request-review', 'inventory-kitchen-request-assign'])
            <a class="progga-nav-link {{ request()->routeIs('inventory.kitchen-requests.*') ? 'active' : '' }}" href="{{ route('inventory.kitchen-requests.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-clipboard2-check-fill progga-nav-icon"></i><span>Kitchen Requests</span>
            </a>
            @endcanany

            @if(auth()->user()?->isKitchenManager())
                @can('inventory-return-post')
                <a class="progga-nav-link {{ request()->routeIs('inventory.transfers.return.*') ? 'active' : '' }}" href="{{ route('inventory.transfers.return.create') }}" style="padding-left:42px;font-size:13px;">
                    <i class="bi bi-arrow-return-left progga-nav-icon"></i><span>Return Extra Ingredient</span>
                </a>
                @endcan
            @endif

            @canany(['inventory-view', 'inventory-wastage-view', 'inventory-wastage-create', 'inventory-wastage-edit', 'inventory-wastage-delete'])
            <a class="progga-nav-link {{ request()->routeIs('inventory.wastages.*') ? 'active' : '' }}" href="{{ route('inventory.wastages.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-trash3-fill progga-nav-icon"></i><span>Wastage</span>
            </a>
            @endcanany

            @can('inventory-transaction-history-view')
            <a class="progga-nav-link {{ request()->routeIs('inventory.ledger.*') ? 'active' : '' }}" href="{{ route('inventory.ledger.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-clock-history progga-nav-icon"></i><span>Transaction History</span>
            </a>
            @endcan

            @can('inventory-purchase-voucher-manage')
            <a class="progga-nav-link {{ request()->routeIs('inventory.purchase-vouchers.*') ? 'active' : '' }}" href="{{ route('inventory.purchase-vouchers.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-file-earmark-check-fill progga-nav-icon"></i><span>Purchase Vouchers</span>
            </a>
            @endcan

            @can('inventory-purchase-approve')
            <a class="progga-nav-link {{ request()->routeIs('inventory.purchase-approvals.*') ? 'active' : '' }}" href="{{ route('inventory.purchase-approvals.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-person-check-fill progga-nav-icon"></i><span>My Purchase Approvals</span>
            </a>
            @endcan

            @canany(['inventory-purchase-create', 'inventory-purchase-receive'])
            <a class="progga-nav-link {{ request()->routeIs('inventory.purchases.*') ? 'active' : '' }}" href="{{ route('inventory.purchases.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-receipt-cutoff progga-nav-icon"></i><span>Purchases / Supply Receive</span>
            </a>
            @endcanany

            @can('inventory-ingredients-manage')
            <a class="progga-nav-link {{ request()->routeIs('inventory.ingredients.*') ? 'active' : '' }}" href="{{ route('inventory.ingredients.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-basket2-fill progga-nav-icon"></i><span>Ingredients</span>
            </a>
            @endcan

            @can('inventory-recipes-manage')
            <a class="progga-nav-link {{ request()->routeIs('inventory.recipes.*') ? 'active' : '' }}" href="{{ route('inventory.recipes.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-card-checklist progga-nav-icon"></i><span>Food Recipes</span>
            </a>
            @endcan

            @can('inventory-units-manage')
            <a class="progga-nav-link {{ request()->routeIs('inventory.units.*') ? 'active' : '' }}" href="{{ route('inventory.units.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-rulers progga-nav-icon"></i><span>Units &amp; Conversion</span>
            </a>
            @endcan

            @can('inventory-vendors-manage')
            <a class="progga-nav-link {{ request()->routeIs('inventory.vendors.*') ? 'active' : '' }}" href="{{ route('inventory.vendors.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-truck progga-nav-icon"></i><span>Vendors</span>
            </a>
            @endcan

            @can('inventory-reports-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.inventory.*') ? 'active' : '' }}" href="{{ route('reports.inventory.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-bar-chart-line-fill progga-nav-icon"></i><span>Inventory Reports</span>
            </a>
            @endcan
        </div>
    </div>
    @endcanany
    @canany(['hr-dashboard-view', 'employee-view', 'attendance-view', 'leave-management-view', 'salary-advance-view', 'loan-view', 'payroll-view', 'shift-view', 'hr-setting-view'])
    <div class="progga-nav-section"><div class="progga-nav-section-label">Human Resources</div></div>
    @php $hrMenuOpen = request()->routeIs('hr.*'); @endphp
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ $hrMenuOpen ? 'active' : '' }}" data-bs-toggle="collapse" href="#hrModuleDropdown" role="button" aria-expanded="{{ $hrMenuOpen ? 'true' : 'false' }}">
            <i class="bi bi-people-fill progga-nav-icon"></i><span>HR</span>
            <i class="bi bi-chevron-down ms-auto" style="font-size:11px;"></i>
        </a>
        <div class="collapse {{ $hrMenuOpen ? 'show' : '' }}" id="hrModuleDropdown">
            @can('hr-dashboard-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.dashboard') ? 'active' : '' }}" href="{{ route('hr.dashboard') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-speedometer2 progga-nav-icon"></i><span>HR Dashboard</span></a>
            @endcan
            @can('employee-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}" href="{{ route('hr.employees.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-person-vcard-fill progga-nav-icon"></i><span>Employees</span></a>
            @endcan
            @can('attendance-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.attendance.*') ? 'active' : '' }}" href="{{ route('hr.attendance.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-fingerprint progga-nav-icon"></i><span>Attendance</span></a>
            @endcan
            @can('leave-management-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.leaves.*') ? 'active' : '' }}" href="{{ route('hr.leaves.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-calendar2-check-fill progga-nav-icon"></i><span>Leave Management</span></a>
            @endcan
            @can('salary-advance-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.salary-advances.*') ? 'active' : '' }}" href="{{ route('hr.salary-advances.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-cash-coin progga-nav-icon"></i><span>Salary Advance</span></a>
            @endcan
            @can('loan-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.loans.*') ? 'active' : '' }}" href="{{ route('hr.loans.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-bank progga-nav-icon"></i><span>Loan</span></a>
            @endcan
            @can('payroll-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}" href="{{ route('hr.payroll.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-wallet2 progga-nav-icon"></i><span>Payroll</span></a>
            @endcan
            @can('shift-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.shifts.*') ? 'active' : '' }}" href="{{ route('hr.shifts.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-clock-history progga-nav-icon"></i><span>Shifts &amp; Duty Roster</span></a>
            @endcan
            @can('hr-setting-view')
            <a class="progga-nav-link {{ request()->routeIs('hr.settings.*') ? 'active' : '' }}" href="{{ route('hr.settings.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-sliders2-vertical progga-nav-icon"></i><span>HR Settings</span></a>
            @endcan
        </div>
    </div>
    @endcanany
@canany(['report-sales-order-view', 'report-delivery-view', 'report-due-view', 'report-complimentary-orders-view', 'report-payment-type-sales-view', 'report-food-sales-view', 'report-waiter-daily-orders-view', 'report-kot-view', 'report-pos-session-view'])
    <div class="progga-nav-section"><div class="progga-nav-section-label">Analytics</div></div>

    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" data-bs-toggle="collapse" href="#reportsDropdown" role="button" aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}" aria-controls="reportsDropdown">
            <i class="bi bi-bar-chart-fill progga-nav-icon"></i><span>Reports</span>
            <i class="bi bi-chevron-down ms-auto" style="font-size: 11px;"></i>
        </a>
        <div class="collapse {{ request()->routeIs('reports.*') ? 'show' : '' }}" id="reportsDropdown">
            @can('report-sales-order-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.index') || request()->routeIs('reports.sales_order') ? 'active' : '' }}" href="{{ route('reports.sales_order') }}" style="padding-left: 42px; font-size: 13px;">
                <i class="bi bi-receipt-cutoff progga-nav-icon"></i><span>Sales & Order Report</span>
            </a>
            @endcan
            @can('report-delivery-view')
            @php
                $sidebarDeliveryPartners = \App\Models\DeliveryPartner::query()->orderBy('name')->orderBy('id')->get();
                $deliveryReportOpen = request()->routeIs('reports.delivery*');
                $sidebarSelectedPartner = (string) request()->query('delivery_partner', 'all');
            @endphp
            <a class="progga-nav-link {{ $deliveryReportOpen ? 'active' : '' }}" data-bs-toggle="collapse" href="#deliveryReportDropdown" role="button" aria-expanded="{{ $deliveryReportOpen ? 'true' : 'false' }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-truck progga-nav-icon"></i><span>Delivery Report</span><i class="bi bi-chevron-down ms-auto" style="font-size:10px;"></i>
            </a>
            <div class="collapse {{ $deliveryReportOpen ? 'show' : '' }}" id="deliveryReportDropdown">
                <a class="progga-nav-link {{ $deliveryReportOpen && ($sidebarSelectedPartner === 'all' || $sidebarSelectedPartner === '') ? 'active' : '' }}" href="{{ route('reports.delivery', ['delivery_partner' => 'all']) }}" style="padding-left:58px;font-size:12px;">
                    <span>ALL</span>
                </a>
                @foreach($sidebarDeliveryPartners as $partner)
                    <a class="progga-nav-link {{ $deliveryReportOpen && $sidebarSelectedPartner === (string)$partner->id ? 'active' : '' }}" href="{{ route('reports.delivery', ['delivery_partner' => $partner->id]) }}" style="padding-left:58px;font-size:12px;">
                        <span>{{ $partner->name }}</span>
                    </a>
                @endforeach
            </div>
            @endcan
            @can('report-due-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.due') ? 'active' : '' }}" href="{{ route('reports.due') }}" style="padding-left: 42px; font-size: 13px;">
                <i class="bi bi-hourglass-split progga-nav-icon"></i><span>Due Report</span>
            </a>
            @endcan
            @can('report-complimentary-orders-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.complimentary_orders') ? 'active' : '' }}" href="{{ route('reports.complimentary_orders') }}" style="padding-left: 42px; font-size: 13px;">
                <i class="bi bi-gift-fill progga-nav-icon"></i><span>Complimentary Order Report</span>
            </a>
            @endcan
            @can('report-payment-type-sales-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.payment_type_sales') ? 'active' : '' }}" href="{{ route('reports.payment_type_sales') }}" style="padding-left: 42px; font-size: 13px;">
                <i class="bi bi-credit-card-2-front progga-nav-icon"></i><span>Payment Type Sales</span>
            </a>
            @endcan
            @can('report-food-sales-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.food_sales') ? 'active' : '' }}" href="{{ route('reports.food_sales') }}" style="padding-left: 42px; font-size: 13px;">
                <i class="bi bi-basket-fill progga-nav-icon"></i><span>Food Wise Sales</span>
            </a>
            @endcan
            @can('report-waiter-daily-orders-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.waiter_daily_orders') ? 'active' : '' }}" href="{{ route('reports.waiter_daily_orders') }}" style="padding-left: 42px; font-size: 13px;">
                <i class="bi bi-person-check-fill progga-nav-icon"></i><span>Waiter Daily Orders</span>
            </a>
            @endcan
            @can('report-kot-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.kots') ? 'active' : '' }}" href="{{ route('reports.kots') }}" style="padding-left: 42px; font-size: 13px;">
                <i class="bi bi-receipt progga-nav-icon"></i><span>KOT Report</span>
            </a>
            @endcan
            @can('report-pos-session-view')
            <a class="progga-nav-link {{ request()->routeIs('reports.pos_sessions') ? 'active' : '' }}" href="{{ route('reports.pos_sessions') }}" style="padding-left: 42px; font-size: 13px;">
                <i class="bi bi-clock-history progga-nav-icon"></i><span>POS Session Report</span>
            </a>

            @endcan
        </div>
    </div>
@endcanany
@canany(['systemsetting-view','profile-view','user-view','permission-view','role-view','offline-pos-device-view'])
<div class="progga-nav-item">
    @php
        $settingsMenuOpen = request()->routeIs('settings.*') || request()->routeIs('profile.*') || request()->routeIs('user.*') || request()->routeIs('permission.*') || request()->routeIs('role.*') || request()->routeIs('offline-pos-devices.*') || request()->routeIs('offline-sync-logs.*');
    @endphp
    <a class="progga-nav-link {{ $settingsMenuOpen ? 'active' : '' }}" data-bs-toggle="collapse" href="#settingsDropdown" role="button" aria-expanded="{{ $settingsMenuOpen ? 'true' : 'false' }}">
        <i class="bi bi-gear-fill progga-nav-icon"></i><span>Settings</span>
        <i class="bi bi-chevron-down ms-auto"></i>
    </a>
    <div class="collapse {{ $settingsMenuOpen ? 'show' : '' }}" id="settingsDropdown">
        @can('systemsetting-view')<a class="progga-nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-sliders progga-nav-icon"></i><span>System Settings</span></a>@endcan
        @can('profile-view')<a class="progga-nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-person-circle progga-nav-icon"></i><span>My Profile</span></a>@endcan
        @can('user-view')<a class="progga-nav-link {{ request()->routeIs('user.*') ? 'active' : '' }}" href="{{ route('user.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-people progga-nav-icon"></i><span>User Management</span></a>@endcan
        @can('permission-view')<a class="progga-nav-link {{ request()->routeIs('permission.*') ? 'active' : '' }}" href="{{ route('permission.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-shield-lock progga-nav-icon"></i><span>Permissions</span></a>@endcan
        @can('role-view')<a class="progga-nav-link {{ request()->routeIs('role.*') ? 'active' : '' }}" href="{{ route('role.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-person-badge progga-nav-icon"></i><span>Role Management</span></a>@endcan
        @can('offline-pos-device-view')<a class="progga-nav-link {{ request()->routeIs('offline-pos-devices.*') ? 'active' : '' }}" href="{{ route('offline-pos-devices.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-pc-display progga-nav-icon"></i><span>Offline POS Devices</span></a>@endcan
        @can('offline-pos-device-view')<a class="progga-nav-link {{ request()->routeIs('offline-sync-logs.*') ? 'active' : '' }}" href="{{ route('offline-sync-logs.index') }}" style="padding-left:42px;font-size:13px;"><i class="bi bi-arrow-left-right progga-nav-icon"></i><span>Offline Sync Logs</span></a>@endcan
    </div>
</div>
@endcanany
</nav>
  <div class="progga-sidebar-footer">
    <div class="progga-sidebar-user" onclick="window.location='{{ route('profile.edit') }}'">
      <img src="{{ auth()->user()->image ? asset('public/' . auth()->user()->image) : 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=21352a&color=d5aa65&size=68' }}" class="progga-user-avatar" alt="User">
      <div>
        <div class="progga-user-name">{{ auth()->user()->name }}</div>
        <div class="progga-user-role">{{ auth()->user()->getRoleNames()->first() ?? 'No Role' }}</div>
      </div>
    </div>
  </div>
</aside>
