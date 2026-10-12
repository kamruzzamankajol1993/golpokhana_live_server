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
        // Tax rate histories record configuration changes, even while Accounting Capture is OFF.
        // These are setting audit records, never automatic journal/event rows.
        \App\Models\TaxSetting::saved(static function (\App\Models\TaxSetting $setting): void {
            if (!\Illuminate\Support\Facades\Schema::hasTable('vat_rate_histories')) return;
            $oldRate=$setting->wasRecentlyCreated ? null : (float)$setting->getOriginal('vat_rate');
            $newRate=(float)$setting->vat_rate;
            $oldIncl=$setting->wasRecentlyCreated ? null : (bool)$setting->getOriginal('is_tax_included');
            $newIncl=(bool)$setting->is_tax_included;
            if ($oldRate!==null && abs($oldRate-$newRate)<0.00001 && $oldIncl===$newIncl) return;
            \Illuminate\Support\Facades\DB::table('vat_rate_histories')->insert([
                'tax_setting_id'=>$setting->id,'old_rate'=>$oldRate,'new_rate'=>$newRate,
                'old_included'=>$oldIncl,'new_included'=>$newIncl,'effective_at'=>now(),
                'changed_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
        $recordVendorRate = static function (\App\Models\Vendor $vendor, bool $isCreate): void {
            if (!\Illuminate\Support\Facades\Schema::hasTable('vendor_tax_rate_histories')) return;
            $tax=app(\App\Services\Inventory\VendorWithholdingService::class);
            foreach (['tds','vds'] as $type) {
                $legacy=$isCreate ? null : (string)$vendor->getOriginal($type);
                try { $old=$legacy===null ? null : $tax->rate($legacy); }
                catch (\Illuminate\Validation\ValidationException $e) { $old=null; } // legacy free-text rate
                $new=$tax->rate($vendor->{$type});
                if ($old!==null && $old===$new) continue;
                \Illuminate\Support\Facades\DB::table('vendor_tax_rate_histories')->insert([
                    'vendor_id'=>$vendor->id,'tax_type'=>strtoupper($type),
                    'old_rate'=>$old,'new_rate'=>$new,'effective_at'=>now(),
                    'changed_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        };
        \App\Models\Vendor::created(static fn (\App\Models\Vendor $v) => $recordVendorRate($v, true));
        \App\Models\Vendor::updated(static fn (\App\Models\Vendor $v) => $recordVendorRate($v, false));
        // Snapshot actual VAT rate on first saved POS order / VAT change only. Never backfill
        // historical orders from current VAT settings, or replace old invoice VAT amounts.
        \App\Models\Order::saving(static function (\App\Models\Order $order): void {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('orders','vat_rate_snapshot')) return;
            if (!$order->exists || $order->isDirty('vat_tax')) {
                $rate=\Illuminate\Support\Facades\DB::table('tax_settings')->value('vat_rate');
                if ($rate!==null) $order->vat_rate_snapshot=$rate;
            }
        });
        // Accounting V2.1 Phases 2-3: source listeners only register callbacks.
        // Actual capture occurs AFTER the operational DB commit and respects Capture OFF.
        \App\Models\Order::saved(static function (\App\Models\Order $order): void {
            if (strcasecmp((string)$order->status,'Completed')===0 &&
                ($order->wasRecentlyCreated || $order->wasChanged('status'))) {
                \App\Services\Accounting\AccountingSourceBridge::schedule('order',(int)$order->id);
            } elseif (strcasecmp((string)$order->status,'Completed')===0 &&
                $order->wasChanged(['subtotal','grand_total','discount_amount','product_discount_amount','vat_tax',
                    'service_charge','delivery_charge','booking_advance','tips_amount'])) {
                \App\Services\Accounting\AccountingSourceBridge::schedule('order_edit',(int)$order->id);
            }
        });
        \App\Models\OrderDuePayment::created(static function (\App\Models\OrderDuePayment $p): void {
            \App\Services\Accounting\AccountingSourceBridge::schedule('due',(int)$p->id);
        });
        \App\Models\TableBooking::saved(static function (\App\Models\TableBooking $b): void {
            if ($b->wasRecentlyCreated || $b->wasChanged('advance_amount')) {
                \App\Services\Accounting\AccountingSourceBridge::schedule('booking',(int)$b->id, [
                    'previous_advance'=>$b->wasRecentlyCreated ? '0' : (string)($b->getOriginal('advance_amount') ?? '0'),
                ]);
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
