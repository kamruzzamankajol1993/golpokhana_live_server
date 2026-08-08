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
