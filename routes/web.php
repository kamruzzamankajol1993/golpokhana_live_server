<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\CustomResetPasswordController; // নতুন কন্ট্রোলার
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\HrSettingController;
use App\Http\Controllers\Admin\HrDashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeSalaryController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\LeaveManagementController;
use App\Http\Controllers\Admin\HrShiftController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\WaiterController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\RewardPointSettingController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Admin\TableBookingController;
use App\Http\Controllers\Admin\OccasionController;
use App\Http\Controllers\Admin\FoodCategoryController;
Route::get('/', function () {
    return view('admin.auth.login');
});

Route::get('/clear', function() {
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('config:cache');
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    return redirect()->back();
});

// ডিফল্ট অথ রাউট (কিন্তু রিসেট রাউটগুলো ফলস করে দিলাম)
Auth::routes(['reset' => false]);

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/dashboard/chart-data', [App\Http\Controllers\HomeController::class, 'chartData'])->name('dashboard.chart_data');
Route::get('/download-pdf', [PDFController::class, 'generatePDF']);
Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('admin.login');

// ==========================================
// Custom Password Reset Routes (GET & POST)
// ==========================================

// 1. Forgot Password Form & Send OTP
Route::get('/forgot-password', [CustomResetPasswordController::class, 'showForgotForm'])->name('password.request');
Route::post('/forgot-password', [CustomResetPasswordController::class, 'sendOtp'])->name('password.email');

// 2. OTP Verification Form & Verify Process
Route::get('/verify-otp', [CustomResetPasswordController::class, 'showOtpForm'])->name('password.otp');
Route::post('/verify-otp', [CustomResetPasswordController::class, 'verifyOtp'])->name('password.otp.verify');

// 3. Reset Password Form & Update Password
Route::get('/reset-password', [CustomResetPasswordController::class, 'showResetForm'])->name('password.resetForm');
Route::post('/reset-password', [CustomResetPasswordController::class, 'resetPassword'])->name('password.update');

Route::middleware(['auth'])->group(function () {

// Refresh CSRF token for long-open AJAX pages like Kitchen Board
Route::get('/refresh-csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
})->name('csrf.refresh');



// ==========================================
    // Real-Time Notifications (QR Orders & Waiter Calls)
    // ==========================================
    Route::get('/notifications-check', [App\Http\Controllers\Admin\OrderController::class, 'checkNotifications'])->name('notifications.check');
    Route::post('/notifications-accept-order', [App\Http\Controllers\Admin\OrderController::class, 'acceptQrOrder'])->name('notifications.accept_order');
    Route::post('/notifications-resolve-waiter', [App\Http\Controllers\Admin\OrderController::class, 'resolveWaiterCall'])->name('notifications.resolve_waiter');

// ==========================================
    // Table QR Code Builder Routes
    // ==========================================
    Route::get('/qr-code-builder', [App\Http\Controllers\Admin\QrCodeController::class, 'index'])->name('qrcode.index');
    Route::get('/qr-code-builder/get-tables/{zone_id}', [App\Http\Controllers\Admin\QrCodeController::class, 'getTables'])->name('qrcode.get_tables');
    Route::post('/qr-code-builder/generate-pdf', [App\Http\Controllers\Admin\QrCodeController::class, 'generatePdf'])->name('qrcode.generate_pdf');
// ==========================================
    // Reports & Analytics Routes
    // ==========================================
    Route::get('reports', [App\Http\Controllers\Admin\ReportController::class, 'salesOrder'])->name('reports.index');
    Route::get('reports/sales-order', [App\Http\Controllers\Admin\ReportController::class, 'salesOrder'])->name('reports.sales_order');
    Route::get('reports/complimentary-orders', [App\Http\Controllers\Admin\ReportController::class, 'complimentaryOrders'])->name('reports.complimentary_orders');
    Route::get('reports/payment-type-wise-sales', [App\Http\Controllers\Admin\ReportController::class, 'paymentTypeSales'])->name('reports.payment_type_sales');
    Route::get('reports/food-wise-sales', [App\Http\Controllers\Admin\ReportController::class, 'foodSales'])->name('reports.food_sales');
    Route::get('reports/waiter-daily-orders', [App\Http\Controllers\Admin\ReportController::class, 'waiterDailyOrders'])->name('reports.waiter_daily_orders');
    Route::get('reports/export/pdf', [App\Http\Controllers\Admin\ReportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('reports/export/excel', [App\Http\Controllers\Admin\ReportController::class, 'exportExcel'])->name('reports.export.excel');
    Route::get('reports/export/csv', [App\Http\Controllers\Admin\ReportController::class, 'exportCsv'])->name('reports.export.csv');

// ==========================================
 // ==========================================
    // Food Category AJAX
    // ==========================================
    Route::get('get-subcategories/{id}', [App\Http\Controllers\Admin\FoodCategoryController::class, 'getSubcategories']);
// POS Session Routes
Route::post('pos-session-start', [App\Http\Controllers\Admin\PosController::class, 'startSession'])->name('pos.session.start');
Route::post('pos-session-end', [App\Http\Controllers\Admin\PosController::class, 'endSession'])->name('pos.session.end');

// POS Session History & Report Routes
Route::get('/pos/session/report/{id}', [App\Http\Controllers\Admin\PosController::class, 'printSessionReport'])->name('pos.session.report');
Route::post('/pos/session/update', [App\Http\Controllers\Admin\PosController::class, 'updateSession'])->name('pos.session.update');

Route::post('/kitchen/mark-unavailable', [App\Http\Controllers\Admin\KitchenController::class, 'markItemUnavailable'])->name('kitchen.mark_unavailable');
    // ==========================================
    // POS (Point of Sale) Routes
    // ==========================================
    Route::get('/pos', [App\Http\Controllers\Admin\PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/random-half-order/activate', [App\Http\Controllers\Admin\PosController::class, 'activateRandomHalfOrderList'])->name('pos.random_half_order.activate');

    // POS: Food & Category
    Route::get('/pos/foods', [App\Http\Controllers\Admin\PosController::class, 'getFoods'])->name('pos.get_foods');
    Route::get('/pos/get-addons/{id}', [App\Http\Controllers\Admin\PosController::class, 'getAddons'])->name('pos.get_addons');

    // POS: Customer
    Route::get('/pos/search-customer', [App\Http\Controllers\Admin\PosController::class, 'searchCustomer'])->name('pos.search_customer');
    Route::post('/pos/store-customer', [App\Http\Controllers\Admin\PosController::class, 'storeCustomer'])->name('pos.store_customer');

    // POS: Cart Management (AJAX)
    Route::get('/pos/cart', [App\Http\Controllers\Admin\PosController::class, 'getCart'])->name('pos.cart.get');
    Route::post('/pos/cart/add', [App\Http\Controllers\Admin\PosController::class, 'addToCart'])->name('pos.cart.add');
    Route::post('/pos/cart/update', [App\Http\Controllers\Admin\PosController::class, 'updateCart'])->name('pos.cart.update');
    Route::post('/pos/cart/remove', [App\Http\Controllers\Admin\PosController::class, 'removeFromCart'])->name('pos.cart.remove');
    Route::post('/pos/order-item/remove', [App\Http\Controllers\Admin\PosController::class, 'removeOrderedItem'])->name('pos.order_item.remove');
    Route::post('/pos/order-item/complimentary', [App\Http\Controllers\Admin\PosController::class, 'makeOrderedItemComplimentary'])->name('pos.order_item.complimentary');
    Route::post('/pos/cart/clear', [App\Http\Controllers\Admin\PosController::class, 'clearCart'])->name('pos.cart.clear');
Route::get('reviews', [App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
    // POS: Order & Payment
    Route::post('/pos-cart-update-note', [App\Http\Controllers\Admin\PosController::class, 'updateNote'])->name('pos.cart.update_note');
    Route::post('/pos-place-order', [App\Http\Controllers\Admin\PosController::class, 'placeOrder'])->name('pos.place_order'); // Send to Kitchen
    Route::post('/pos/hold-web-order', [App\Http\Controllers\Admin\PosController::class, 'holdWebOrder'])->name('pos.hold_web_order'); // Hold website QR order in POS cart
    Route::get('/pos/table-order/{table_id}', [App\Http\Controllers\Admin\PosController::class, 'getTableOrder'])->name('pos.get_table_order'); // Occupied Table Data
    Route::post('/pos/table-swap', [App\Http\Controllers\Admin\PosController::class, 'swapTable'])->name('pos.table_swap'); // Dine-In active order table swap
    Route::get('/pos/active-order/{order_id}', [App\Http\Controllers\Admin\PosController::class, 'getPosOrder'])->name('pos.get_pos_order'); // Takeaway/Delivery active order data
    Route::post('/pos/takeaway-delivery/complete-pending', [App\Http\Controllers\Admin\PosController::class, 'completePendingTakeawayDeliveryPayments'])->name('pos.takeaway_delivery.complete_pending');
    Route::post('/pos/payment', [App\Http\Controllers\Admin\PosController::class, 'completePayment'])->name('pos.complete_payment');
Route::get('orders/{id}/details', [App\Http\Controllers\Admin\OrderController::class, 'details'])->name('order.details');
Route::get('/pos/invoice/{id}', [App\Http\Controllers\Admin\PosController::class, 'printInvoice'])->name('pos.invoice');
// নতুন রাউট: প্রি-পেমেন্ট ইনভয়েস (Guest Bill)
    Route::get('/pos/pre-invoice/{id}', [App\Http\Controllers\Admin\PosController::class, 'printPreInvoice'])->name('pos.pre_invoice');
   // ==========================================
    // Kitchen (KOT) Routes
    // ==========================================
    Route::get('/kitchen', [App\Http\Controllers\Admin\KitchenController::class, 'index'])->name('kitchen.index');
    Route::get('/kitchen/get-live-orders', [App\Http\Controllers\Admin\KitchenController::class, 'getLiveOrders'])->name('kitchen.get_live_orders');
    Route::post('/kitchen/update-status', [App\Http\Controllers\Admin\KitchenController::class, 'updateStatus'])->name('kitchen.update_status');

    // KOT প্রিন্ট করার জন্য নতুন রাউট
    Route::get('/kitchen/print-kot/{id}', [App\Http\Controllers\Admin\KitchenController::class, 'printKot'])->name('kitchen.print_kot');

Route::get('get-subcategories/{id}', [App\Http\Controllers\Admin\FoodCategoryController::class, 'getSubcategories']);
// Food Item Routes
    Route::resource('food-item', App\Http\Controllers\Admin\FoodItemController::class);
    Route::post('food-item-status/{id}', [App\Http\Controllers\Admin\FoodItemController::class, 'updateStatus'])->name('food-item.status');
// Allergen Routes
    Route::resource('allergen', App\Http\Controllers\Admin\AllergenController::class);
    Route::post('allergen-status/{id}', [App\Http\Controllers\Admin\AllergenController::class, 'updateStatus'])->name('allergen.status');

    // Course Type Routes
    Route::resource('course-type', App\Http\Controllers\Admin\CourseTypeController::class);
    Route::post('course-type-status/{id}', [App\Http\Controllers\Admin\CourseTypeController::class, 'updateStatus'])->name('course-type.status');
Route::get('orders-export-pdf', [App\Http\Controllers\Admin\OrderController::class, 'exportPDF'])->name('order.export_pdf');
Route::get('orders-export-excel', [App\Http\Controllers\Admin\OrderController::class, 'exportExcel'])->name('order.export_excel');

// Food Category Routes
    Route::resource('food-category', FoodCategoryController::class);
Route::post('food-category-status/{id}', [FoodCategoryController::class, 'updateStatus'])->name('food-category.status');

// ==========================================
    // Order Management Routes
    // ==========================================
    Route::get('orders', [App\Http\Controllers\Admin\OrderController::class, 'index'])->name('order.index');
    // Order edit page: existing quantity/payment summary and complimentary conversion can be changed.
    Route::get('orders/{id}/edit', [App\Http\Controllers\Admin\OrderController::class, 'edit'])->name('order.edit');
    Route::put('orders/{id}', [App\Http\Controllers\Admin\OrderController::class, 'update'])->name('order.update');
    Route::post('orders/{id}/pay-due', [App\Http\Controllers\Admin\OrderController::class, 'payDue'])->name('order.pay_due');
    Route::get('orders/{id}', [App\Http\Controllers\Admin\OrderController::class, 'show'])->name('order.show');
    Route::get('orders/{id}/delete-history', [App\Http\Controllers\Admin\OrderController::class, 'deletedHistory'])->name('order.delete_history');
    Route::delete('orders/{id}', [App\Http\Controllers\Admin\OrderController::class, 'destroy'])
    ->name('order.destroy');
// Cuisine Type Routes
    Route::resource('cuisine-type', App\Http\Controllers\Admin\CuisineTypeController::class);
    Route::post('cuisine-type-status/{id}', [App\Http\Controllers\Admin\CuisineTypeController::class, 'updateStatus'])->name('cuisine-type.status');
// ==========================================
    // Table Booking Routes
    // ==========================================
    Route::resource('table-booking', TableBookingController::class);

    // ==========================================
    // Occasion Management Routes (AJAX Based)
    // ==========================================
    Route::get('occasion', [OccasionController::class, 'index'])->name('occasion.index');
    Route::post('occasion', [OccasionController::class, 'store'])->name('occasion.store');
    Route::put('occasion/{id}', [OccasionController::class, 'update'])->name('occasion.update');
    Route::delete('occasion/{id}', [OccasionController::class, 'destroy'])->name('occasion.destroy');

// ==========================================
    // Table Management Routes
    // ==========================================
    Route::resource('table', TableController::class);


// ==========================================
    // Customer Management Routes
    // ==========================================

    // Customer Export Routes
Route::get('customer-export-pdf', [CustomerController::class, 'exportPDF'])->name('customer.export.pdf');
Route::get('customer-export-excel', [CustomerController::class, 'exportExcel'])->name('customer.export.excel');


    Route::resource('customer', CustomerController::class);
// Customer History Route (Ajax)
    Route::get('customer-history/{id}', [CustomerController::class, 'history'])->name('customer.history');
    // ==========================================
    // Reward Point Settings Routes
    // ==========================================
    Route::get('reward-points', [RewardPointSettingController::class, 'index'])->name('reward-points.index');
    Route::post('reward-points/update', [RewardPointSettingController::class, 'update'])->name('reward-points.update');


// Zone Management Routes
    Route::resource('zone', ZoneController::class);

    // Shift Management Routes
    Route::resource('shift', ShiftController::class);
Route::post('waiter-update-status', [WaiterController::class, 'updateStatus'])->name('waiter.status');
    // Waiter Management Routes
    Route::resource('waiter', WaiterController::class);


    Route::resource('permission', PermissionController::class);
    Route::resource('role', RoleController::class);
    Route::resource('user', UserController::class);

    // Profile Routes
    Route::get('/my-profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/my-profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/my-profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');


    // ==========================================
    // Human Resources - HR Settings Routes
    // ==========================================
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('/dashboard', [HrDashboardController::class, 'index'])->name('dashboard');

        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('/employees/{employee}/status', [EmployeeController::class, 'updateStatus'])->name('employees.status');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::get('/employees/{employee}/salary', [EmployeeSalaryController::class, 'show'])->name('employees.salary.show');
        Route::post('/employees/{employee}/salary', [EmployeeSalaryController::class, 'store'])->name('employees.salary.store');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/template/download', [AttendanceController::class, 'downloadTemplate'])->name('attendance.template.download');
        Route::post('/attendance/import', [AttendanceController::class, 'importExcel'])->name('attendance.import');
        Route::get('/attendance/reports', [AttendanceController::class, 'reports'])->name('attendance.reports.index');
        Route::get('/attendance/reports/table', [AttendanceController::class, 'reportTable'])->name('attendance.reports.table');
        Route::get('/attendance/reports/pdf', [AttendanceController::class, 'reportPdf'])->name('attendance.reports.pdf');
        Route::post('/attendance/bulk', [AttendanceController::class, 'storeBulk'])->name('attendance.bulk.store');
        Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
        Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

        Route::get('/leave-management', [LeaveManagementController::class, 'index'])->name('leaves.index');
        Route::get('/leave-management/employee/{employee}/balances', [LeaveManagementController::class, 'balances'])->name('leaves.balances');
        Route::post('/leave-management', [LeaveManagementController::class, 'store'])->name('leaves.store');
        Route::post('/leave-management/{leave}', [LeaveManagementController::class, 'update'])->name('leaves.update');
        Route::patch('/leave-management/{leave}/decision', [LeaveManagementController::class, 'decision'])->name('leaves.decision');
        Route::patch('/leave-management/{leave}/cancel', [LeaveManagementController::class, 'cancel'])->name('leaves.cancel');
        Route::delete('/leave-management/{leave}', [LeaveManagementController::class, 'destroy'])->name('leaves.destroy');


        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('/payroll/create', [PayrollController::class, 'create'])->name('payroll.create');
        Route::get('/payroll/precheck', [PayrollController::class, 'precheck'])->name('payroll.precheck');
        Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('/payroll/single/create', [PayrollController::class, 'createSingle'])->name('payroll.single.create');
        Route::get('/payroll/single/precheck', [PayrollController::class, 'singlePrecheck'])->name('payroll.single.precheck');
        Route::post('/payroll/single', [PayrollController::class, 'storeSingle'])->name('payroll.single.store');
        Route::get('/payroll/{payrollRun}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('/payroll/{payrollRun}/items/table', [PayrollController::class, 'itemsTable'])->name('payroll.items.table');
        Route::get('/payroll/{payrollRun}/items/{payrollItem}', [PayrollController::class, 'itemShow'])->name('payroll.items.show');
        Route::put('/payroll/{payrollRun}/items/{payrollItem}', [PayrollController::class, 'updateItem'])->name('payroll.items.update');
        Route::post('/payroll/{payrollRun}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
        Route::post('/payroll/{payrollRun}/items/{payrollItem}/approve', [PayrollController::class, 'approveItem'])->name('payroll.items.approve');
        Route::post('/payroll/{payrollRun}/items/{payrollItem}/pay', [PayrollController::class, 'payItem'])->name('payroll.items.pay');
        Route::post('/payroll/{payrollRun}/pay-all', [PayrollController::class, 'payAll'])->name('payroll.pay-all');
        Route::post('/payroll/{payrollRun}/regenerate', [PayrollController::class, 'regenerate'])->name('payroll.regenerate');
        Route::delete('/payroll/{payrollRun}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
        Route::get('/payroll/{payrollRun}/summary-pdf', [PayrollController::class, 'summaryPdf'])->name('payroll.summary-pdf');
        Route::get('/payroll/{payrollRun}/items/{payrollItem}/payslip', [PayrollController::class, 'payslip'])->name('payroll.items.payslip');

        Route::get('/shifts', [HrShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/table/list', [HrShiftController::class, 'shiftTable'])->name('shifts.table');
        Route::post('/shifts', [HrShiftController::class, 'store'])->name('shifts.store');
        Route::put('/shifts/{shift}', [HrShiftController::class, 'update'])->name('shifts.update');
        Route::patch('/shifts/{shift}/status', [HrShiftController::class, 'updateStatus'])->name('shifts.status');
        Route::delete('/shifts/{shift}', [HrShiftController::class, 'destroy'])->name('shifts.destroy');
        Route::get('/shift-roster/table', [HrShiftController::class, 'rosterTable'])->name('shifts.roster.table');
        Route::post('/shift-roster/save', [HrShiftController::class, 'saveRoster'])->name('shifts.roster.save');
        Route::post('/shift-roster/copy-previous', [HrShiftController::class, 'copyPreviousWeek'])->name('shifts.roster.copy');

        Route::get('/settings', [HrSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings/general', [HrSettingController::class, 'updateGeneral'])->name('settings.general.update');
        Route::post('/settings/attendance', [HrSettingController::class, 'updateAttendance'])->name('settings.attendance.update');
        Route::post('/settings/payroll', [HrSettingController::class, 'updatePayroll'])->name('settings.payroll.update');

        Route::post('/settings/departments', [HrSettingController::class, 'storeDepartment'])->name('settings.departments.store');
        Route::put('/settings/departments/{department}', [HrSettingController::class, 'updateDepartment'])->name('settings.departments.update');
        Route::patch('/settings/departments/{department}/status', [HrSettingController::class, 'updateDepartmentStatus'])->name('settings.departments.status');
        Route::delete('/settings/departments/{department}', [HrSettingController::class, 'destroyDepartment'])->name('settings.departments.destroy');

        Route::post('/settings/designations', [HrSettingController::class, 'storeDesignation'])->name('settings.designations.store');
        Route::put('/settings/designations/{designation}', [HrSettingController::class, 'updateDesignation'])->name('settings.designations.update');
        Route::patch('/settings/designations/{designation}/status', [HrSettingController::class, 'updateDesignationStatus'])->name('settings.designations.status');
        Route::delete('/settings/designations/{designation}', [HrSettingController::class, 'destroyDesignation'])->name('settings.designations.destroy');

        Route::post('/settings/employment-types', [HrSettingController::class, 'storeEmploymentType'])->name('settings.employment-types.store');
        Route::put('/settings/employment-types/{employmentType}', [HrSettingController::class, 'updateEmploymentType'])->name('settings.employment-types.update');
        Route::patch('/settings/employment-types/{employmentType}/status', [HrSettingController::class, 'updateEmploymentTypeStatus'])->name('settings.employment-types.status');
        Route::delete('/settings/employment-types/{employmentType}', [HrSettingController::class, 'destroyEmploymentType'])->name('settings.employment-types.destroy');

        Route::post('/settings/leave-types', [HrSettingController::class, 'storeLeaveType'])->name('settings.leave-types.store');
        Route::put('/settings/leave-types/{leaveType}', [HrSettingController::class, 'updateLeaveType'])->name('settings.leave-types.update');
        Route::patch('/settings/leave-types/{leaveType}/status', [HrSettingController::class, 'updateLeaveTypeStatus'])->name('settings.leave-types.status');
        Route::delete('/settings/leave-types/{leaveType}', [HrSettingController::class, 'destroyLeaveType'])->name('settings.leave-types.destroy');

        Route::post('/settings/salary-components', [HrSettingController::class, 'storeSalaryComponent'])->name('settings.salary-components.store');
        Route::put('/settings/salary-components/{salaryComponent}', [HrSettingController::class, 'updateSalaryComponent'])->name('settings.salary-components.update');
        Route::patch('/settings/salary-components/{salaryComponent}/status', [HrSettingController::class, 'updateSalaryComponentStatus'])->name('settings.salary-components.status');
        Route::delete('/settings/salary-components/{salaryComponent}', [HrSettingController::class, 'destroySalaryComponent'])->name('settings.salary-components.destroy');

        Route::post('/settings/holidays', [HrSettingController::class, 'storeHoliday'])->name('settings.holidays.store');
        Route::put('/settings/holidays/{holiday}', [HrSettingController::class, 'updateHoliday'])->name('settings.holidays.update');
        Route::patch('/settings/holidays/{holiday}/status', [HrSettingController::class, 'updateHolidayStatus'])->name('settings.holidays.status');
        Route::delete('/settings/holidays/{holiday}', [HrSettingController::class, 'destroyHoliday'])->name('settings.holidays.destroy');
    });

    // Settings Routes
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/restaurant', [SettingController::class, 'updateRestaurant'])->name('settings.restaurant');
    Route::post('/settings/tax', [SettingController::class, 'updateTax'])->name('settings.tax');
    Route::post('/settings/invoice', [SettingController::class, 'updateInvoice'])->name('settings.invoice');
    Route::post('/settings/pos', [SettingController::class, 'updatePos'])->name('settings.pos');
});
