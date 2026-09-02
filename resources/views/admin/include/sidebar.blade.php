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
        <a class="progga-nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" data-bs-toggle="collapse" href="#posSystemDropdown" role="button" aria-expanded="{{ request()->routeIs('pos.*') ? 'true' : 'false' }}" aria-controls="posSystemDropdown">
            <i class="bi bi-display progga-nav-icon"></i><span>POS System</span>
            <span class="progga-nav-badge">LIVE</span>
            <i class="bi bi-chevron-down ms-auto" style="font-size:11px;"></i>
        </a>
        <div class="collapse {{ request()->routeIs('pos.*') ? 'show' : '' }}" id="posSystemDropdown">
            <a class="progga-nav-link {{ request()->routeIs('pos.index') ? 'active' : '' }}" href="{{ route('pos.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-display progga-nav-icon"></i><span>Sales</span>
            </a>
            <a class="progga-nav-link {{ request()->routeIs('pos.sessions.*') ? 'active' : '' }}" href="{{ route('pos.sessions.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-clock-history progga-nav-icon"></i><span>Session List</span>
            </a>
            <a class="progga-nav-link {{ request()->routeIs('pos.kots.*') ? 'active' : '' }}" href="{{ route('pos.kots.index') }}" style="padding-left:42px;font-size:13px;">
                <i class="bi bi-receipt progga-nav-icon"></i><span>Kot List</span>
            </a>
        </div>
    </div>
    @endcan

    @can('kitchen-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('kitchen.*') ? 'active' : '' }}" href="{{ route('kitchen.index') }}">
            <i class="bi bi-fire progga-nav-icon"></i><span>Kitchen Board</span>
        </a>
    </div>
    @endcan
    @can('food-category-view')
    <div class="progga-nav-section" ><div class="progga-nav-section-label">Menu</div></div>

    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('food-category.*') ? 'active' : '' }}" href="{{ route('food-category.index') }}">
            <i class="bi bi-tags-fill progga-nav-icon"></i><span>Food Categories</span>
        </a>
    </div>
    @endcan
    @can('cuisine-type-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('cuisine-type.*') ? 'active' : '' }}" href="{{ route('cuisine-type.index') }}">
            <i class="bi bi-globe progga-nav-icon"></i><span>Cuisine Types</span>
        </a>
    </div>
    @endcan
    @can('allergen-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('allergen.*') || request()->routeIs('course-type.*') ? 'active' : '' }}" href="{{ route('allergen.index') }}">
            <i class="bi bi-sliders progga-nav-icon"></i><span>Food Attributes</span>
        </a>
    </div>
    @endcan
    @can('food-item-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('food-item.*') ? 'active' : '' }}" href="{{ route('food-item.index') }}">
            <i class="bi bi-journal-richtext progga-nav-icon"></i><span>Food Menu</span>
        </a>
    </div>
    @endcan
    @can('order-view')
    <div class="progga-nav-section" ><div class="progga-nav-section-label">Operations</div></div>


    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('order.*') ? 'active' : '' }}" href="{{ route('order.index') }}">
            <i class="bi bi-receipt-cutoff progga-nav-icon"></i><span>Order List</span>
        </a>
    </div>
    @endcan

   @can('table-view')
<div class="progga-nav-item">
    <a class="progga-nav-link {{ (request()->routeIs('table.*') || request()->routeIs('floor-zone.*')) ? 'active' : '' }}" data-bs-toggle="collapse" href="#tableManagementMenu" role="button" aria-expanded="false">
        <i class="bi bi-table progga-nav-icon"></i><span>Table Management</span>
        <i class="bi bi-chevron-down ms-auto"></i>
    </a>
    <div class="collapse {{ (request()->routeIs('table.*') || request()->routeIs('floor-zone.*')) ? 'show' : '' }}" id="tableManagementMenu">
        <a class="progga-nav-link ps-5 {{ request()->routeIs('floor-zone.*') ? 'active' : '' }}" href="{{ route('floor-zone.index') }}">
            <span>Floor / Zone</span>
        </a>
        <a class="progga-nav-link ps-5 {{ request()->routeIs('table.*') ? 'active' : '' }}" href="{{ route('table.index') }}#table-section">
            <span>Tables</span>
        </a>
    </div>
</div>
@endcan


@can('qrcode-view')
<div class="progga-nav-item">
    <a class="progga-nav-link {{ request()->routeIs('qrcode.*') ? 'active' : '' }}" href="{{ route('qrcode.index') }}">
        <i class="bi bi-qr-code-scan progga-nav-icon"></i><span>Table QR Codes</span>
    </a>
</div>
@endcan
   @can('table-booking-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('table-booking.*') ? 'active' : '' }}" href="{{ route('table-booking.index') }}">
            <i class="bi bi-calendar-check-fill progga-nav-icon"></i>
            <span>Table Booking</span>

            @php
                $upcomingCount = \App\Models\TableBooking::where('status', 'upcoming')->count();
            @endphp
            @if($upcomingCount > 0)
                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 10px;">{{ $upcomingCount }}</span>
            @endif
        </a>
    </div>
    @endcan
   @can('customer-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('customer.*') || request()->routeIs('reward-points.*') ? 'active' : '' }}" href="{{ route('customer.index') }}">
            <i class="bi bi-people-fill progga-nav-icon"></i><span>Customers</span>
        </a>
    </div>

    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('delivery-partner.*') ? 'active' : '' }}" href="{{ route('delivery-partner.index') }}">
            <i class="bi bi-truck progga-nav-icon"></i><span>Delivery Partner</span>
        </a>
    </div>
    @endcan
    @can('customer-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('reviews.*') ? 'active' : '' }}" href="{{ route('reviews.index') }}">
            <i class="bi bi-chat-square-heart-fill progga-nav-icon"></i><span>Feedback List</span>
        </a>
    </div>
    @endcan
   @can('waiter-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('waiter.*') ? 'active' : '' }}" href="{{ route('waiter.index') }}">
            <i class="bi bi-person-badge-fill progga-nav-icon"></i><span>Waiters</span>
        </a>
    </div>
    @endcan

    @canany(['hr-dashboard-view', 'employee-view', 'attendance-view', 'leave-management-view', 'payroll-view', 'shift-view', 'hr-setting-view'])
    <div class="progga-nav-section"><div class="progga-nav-section-label">Human Resources</div></div>

    @can('hr-dashboard-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('hr.dashboard') ? 'active' : '' }}" href="{{ route('hr.dashboard') }}">
            <i class="bi bi-speedometer2 progga-nav-icon"></i><span>HR Dashboard</span>
        </a>
    </div>
    @endcan

    @can('employee-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}" href="{{ route('hr.employees.index') }}">
            <i class="bi bi-person-vcard-fill progga-nav-icon"></i><span>Employees</span>
        </a>
    </div>
    @endcan

    @can('attendance-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('hr.attendance.*') ? 'active' : '' }}" href="{{ route('hr.attendance.index') }}">
            <i class="bi bi-fingerprint progga-nav-icon"></i><span>Attendance</span>
        </a>
    </div>
    @endcan

    @can('leave-management-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('hr.leaves.*') ? 'active' : '' }}" href="{{ route('hr.leaves.index') }}">
            <i class="bi bi-calendar2-check-fill progga-nav-icon"></i><span>Leave Management</span>
        </a>
    </div>
    @endcan

    @can('payroll-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }} {{ Route::has('hr.payroll.index') ? '' : 'opacity-50' }}" href="{{ Route::has('hr.payroll.index') ? route('hr.payroll.index') : 'javascript:void(0)' }}" title="{{ Route::has('hr.payroll.index') ? 'Payroll' : 'Available in the next HR phase' }}">
            <i class="bi bi-wallet2 progga-nav-icon"></i><span>Payroll</span>
        </a>
    </div>
    @endcan

    @can('shift-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('hr.shifts.*') ? 'active' : '' }}" href="{{ route('hr.shifts.index') }}">
            <i class="bi bi-clock-history progga-nav-icon"></i><span>Shifts</span>
        </a>
    </div>
    @endcan

    @can('hr-setting-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('hr.settings.*') ? 'active' : '' }}" href="{{ route('hr.settings.index') }}">
            <i class="bi bi-sliders2-vertical progga-nav-icon"></i><span>HR Settings</span>
        </a>
    </div>
    @endcan
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
@can('systemsetting-view')
    <div class="progga-nav-section" ><div class="progga-nav-section-label">System</div></div>


    <div class="progga-nav-item">
    <a class="progga-nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
        <i class="bi bi-gear-fill progga-nav-icon"></i><span>Settings</span>
    </a>
</div>
@endcan
@can('profile-view')
<div class="progga-nav-item">
    <a class="progga-nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
        <i class="bi bi-person-circle progga-nav-icon"></i><span>My Profile</span>
    </a>
</div>
@endcan

@can('user-view')
<div class="progga-nav-item">
    <a class="progga-nav-link {{ request()->routeIs('user.*') ? 'active' : '' }}" href="{{ route('user.index') }}">
        <i class="bi bi-people-fill progga-nav-icon"></i><span>User Management</span>
    </a>
</div>
@endcan
    @can('permission-view')
<div class="progga-nav-item">
    <a class="progga-nav-link {{ request()->routeIs('permission.*') ? 'active' : '' }}" href="{{ route('permission.index') }}">
        <i class="bi bi-shield-lock-fill progga-nav-icon"></i><span>Permissions</span>
    </a>
</div>
@endcan

@can('role-view')
    <div class="progga-nav-item">
        <a class="progga-nav-link {{ request()->routeIs('role.*') ? 'active' : '' }}" href="{{ route('role.index') }}">
            <i class="bi bi-person-lines-fill progga-nav-icon"></i><span>Role Management</span>
        </a>
    </div>
    @endcan
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
