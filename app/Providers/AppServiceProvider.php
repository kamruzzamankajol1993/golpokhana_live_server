<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\RestaurantSetting;
use App\Models\TaxSetting;
use App\Models\InvoiceSetting;
use App\Models\PosSetting;
use App\Models\Waiter;     // নতুন যুক্ত করা হলো
use App\Models\Customer;   // নতুন যুক্ত করা হলো
use Exception;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Accounting V2.1 Phases 2-3: source listeners only register callbacks.
        // Actual capture occurs AFTER the operational DB commit and respects Capture OFF.
        \App\Models\Order::saved(static function (\App\Models\Order $order): void {
            if (strcasecmp((string)$order->status,'Completed')===0 &&
                ($order->wasRecentlyCreated || $order->wasChanged('status'))) {
                \App\Services\Accounting\AccountingSourceBridge::schedule('order',(int)$order->id);
            }
        });
        \App\Models\OrderDuePayment::created(static function (\App\Models\OrderDuePayment $p): void {
            \App\Services\Accounting\AccountingSourceBridge::schedule('due',(int)$p->id);
        });
        \App\Models\TableBooking::saved(static function (\App\Models\TableBooking $b): void {
            if ($b->wasRecentlyCreated || $b->wasChanged('advance_amount')) {
                \App\Services\Accounting\AccountingSourceBridge::schedule('booking',(int)$b->id);
            }
        });
        \App\Models\Purchase::saved(static function (\App\Models\Purchase $p): void {
            if ($p->status===\App\Models\Purchase::STATUS_RECEIVED &&
                ($p->wasRecentlyCreated || $p->wasChanged('status'))) {
                \App\Services\Accounting\AccountingSourceBridge::schedule('purchase',(int)$p->id);
            }
        });
        \App\Models\VendorPayment::created(static function (\App\Models\VendorPayment $p): void {
            \App\Services\Accounting\AccountingSourceBridge::schedule('vendor_payment',(int)$p->id);
        });
        \App\Models\StockMovement::saved(static function (\App\Models\StockMovement $m): void {
            if ($m->status===\App\Models\StockMovement::STATUS_POSTED && $m->wasChanged('status')) {
                \App\Services\Accounting\AccountingSourceBridge::schedule('stock_movement',(int)$m->id);
            }
        });
        // Phase 4: per-employee approved payroll is the accrual boundary.
        \App\Models\PayrollItem::saved(static function (\App\Models\PayrollItem $item): void {
            if (in_array($item->status,['approved','paid'],true) &&
                ($item->wasChanged('status') || $item->wasChanged('approved_at'))) {
                \App\Services\Accounting\AccountingSourceBridge::schedule('payroll_item',(int)$item->id);
            }
        });
        \App\Models\PayrollPayment::created(static function (\App\Models\PayrollPayment $p): void {
            \App\Services\Accounting\AccountingSourceBridge::schedule('payroll_payment',(int)$p->id);
        });
        try {
            // ১. ডাটাবেস থেকে সেটিংসের প্রথম রেকর্ডগুলো ফেচ করা হচ্ছে
            $restaurantSetting = RestaurantSetting::first();
            $taxSetting        = TaxSetting::first();
            $invoiceSetting    = InvoiceSetting::first();
            $posSetting        = PosSetting::first();

            // POS এবং Master ফাইলের মোডালের জন্য Waiter ও Customer ফেচ করা হচ্ছে
            $waiters   = Waiter::where('status', 1)->get();
            $customers = Customer::orderBy('name', 'asc')->get();

            // ==========================================
            // ২. Restaurant Settings এর ভ্যারিয়েবলগুলো
            // ==========================================
            $restaurantSettingName        = $restaurantSetting ? $restaurantSetting->name : null;
            $restaurantSettingPhone       = $restaurantSetting ? $restaurantSetting->phone : null;
            $restaurantSettingEmail       = $restaurantSetting ? $restaurantSetting->email : null;
            $restaurantSettingWebsite     = $restaurantSetting ? $restaurantSetting->website : null;
            $restaurantSettingAddress     = $restaurantSetting ? $restaurantSetting->address : null;
            $restaurantSettingOpeningTime = $restaurantSetting ? $restaurantSetting->opening_time : null;
            $restaurantSettingClosingTime = $restaurantSetting ? $restaurantSetting->closing_time : null;
            $restaurantSettingCurrency    = $restaurantSetting ? $restaurantSetting->currency : 'BDT';
            $restaurantSettingIconName    = $restaurantSetting ? $restaurantSetting->icon_name : null;
            $restaurantSettingLogo        = $restaurantSetting ? $restaurantSetting->logo : null;

            // ==========================================
// ৩. Tax Settings এর ভ্যারিয়েবলগুলো
// ==========================================
$taxSettingVatRate            = $taxSetting ? (float) $taxSetting->vat_rate : 0;
$taxSettingTaxLabel           = $taxSetting ? $taxSetting->tax_label : 'VAT';
$taxSettingTaxRegistrationNo  = $taxSetting ? $taxSetting->tax_registration_no : null;
$taxSettingIsTaxIncluded      = $taxSetting ? $taxSetting->is_tax_included : false;
$taxSettingServiceCharge      = $taxSetting ? (float) $taxSetting->service_charge : 0;

            // ==========================================
            // ৪. Invoice Settings এর ভ্যারিয়েবলগুলো
            // ==========================================
            $invoiceSettingPrefix         = $invoiceSetting ? $invoiceSetting->prefix : 'INV-';
            $invoiceSettingStartingNumber = $invoiceSetting ? $invoiceSetting->starting_number : 1001;
            $invoiceSettingFooterNote     = $invoiceSetting ? $invoiceSetting->footer_note : null;
            $invoiceSettingPaperSize      = $invoiceSetting ? $invoiceSetting->paper_size : '80mm';
            $invoiceSettingShowLogo       = $invoiceSetting ? $invoiceSetting->show_logo : true;

            // ==========================================
            // ৫. POS Settings এর ভ্যারিয়েবলগুলো
            // ==========================================
            $posSettingDefaultView           = $posSetting ? $posSetting->default_view : 'Grid';
            $posSettingItemsPerPage          = $posSetting ? $posSetting->items_per_page : 12;
            $posSettingAutoPrintKitchen      = $posSetting ? $posSetting->auto_print_kitchen : true;
            $posSettingAutoPrintInvoice      = $posSetting ? $posSetting->auto_print_invoice : true;
            $posSettingRequireTableSelection = $posSetting ? $posSetting->require_table_selection : true;
            $posSettingShowOutOfStock        = $posSetting ? $posSetting->show_out_of_stock : true;


            // ==========================================
            // ৬. ভিউ এর সাথে গ্লোবাল ভেরিয়েবল শেয়ার করা (View::share)
            // ==========================================

            // Restaurant
            View::share('restaurantSettingName', $restaurantSettingName);
            View::share('restaurantSettingPhone', $restaurantSettingPhone);
            View::share('restaurantSettingEmail', $restaurantSettingEmail);
            View::share('restaurantSettingWebsite', $restaurantSettingWebsite);
            View::share('restaurantSettingAddress', $restaurantSettingAddress);
            View::share('restaurantSettingOpeningTime', $restaurantSettingOpeningTime);
            View::share('restaurantSettingClosingTime', $restaurantSettingClosingTime);
            View::share('restaurantSettingCurrency', $restaurantSettingCurrency);
            View::share('restaurantSettingIconName', $restaurantSettingIconName);
            View::share('restaurantSettingLogo', $restaurantSettingLogo);

            // Tax
            View::share('taxSettingVatRate', $taxSettingVatRate);
            View::share('taxSettingTaxLabel', $taxSettingTaxLabel);
            View::share('taxSettingTaxRegistrationNo', $taxSettingTaxRegistrationNo);
            View::share('taxSettingIsTaxIncluded', $taxSettingIsTaxIncluded);
            View::share('taxSettingServiceCharge', $taxSettingServiceCharge);

            // Invoice
            View::share('invoiceSettingPrefix', $invoiceSettingPrefix);
            View::share('invoiceSettingStartingNumber', $invoiceSettingStartingNumber);
            View::share('invoiceSettingFooterNote', $invoiceSettingFooterNote);
            View::share('invoiceSettingPaperSize', $invoiceSettingPaperSize);
            View::share('invoiceSettingShowLogo', $invoiceSettingShowLogo);

            // POS
            View::share('posSettingDefaultView', $posSettingDefaultView);
            View::share('posSettingItemsPerPage', $posSettingItemsPerPage);
            View::share('posSettingAutoPrintKitchen', $posSettingAutoPrintKitchen);
            View::share('posSettingAutoPrintInvoice', $posSettingAutoPrintInvoice);
            View::share('posSettingRequireTableSelection', $posSettingRequireTableSelection);
            View::share('posSettingShowOutOfStock', $posSettingShowOutOfStock);

            // পুরো অবজেক্টগুলোও শেয়ার করা থাকলো যদি কোনো কারণে প্রয়োজন হয়
            View::share('restaurantSetting', $restaurantSetting);
            View::share('taxSetting', $taxSetting);
            View::share('invoiceSetting', $invoiceSetting);
            View::share('posSetting', $posSetting);

            // রিয়েল-টাইম মোডালের জন্য গ্লোবাল ভেরিয়েবল
            View::share('waiters', $waiters);
            View::share('customers', $customers);

        } catch (Exception $e) {
            // ডাটাবেস মাইগ্রেট করার সময় বা টেবিল না থাকলে যাতে অ্যাপ্লিকেশন ক্র্যাশ না করে
        }
    }
}
