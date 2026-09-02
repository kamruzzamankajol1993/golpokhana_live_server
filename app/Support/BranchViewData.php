<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryPartner;
use App\Models\InvoiceSetting;
use App\Models\PosSetting;
use App\Models\RestaurantSetting;
use App\Models\TaxSetting;
use App\Models\Waiter;
use App\Services\BranchModeManager;
use App\Services\BranchSettingResolver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class BranchViewData
{
    public function __construct(
        private BranchContext $context,
        private BranchModeManager $mode,
        private BranchSettingResolver $settings
    ) {
    }

    public function share(): void
    {
        $restaurantSetting = $this->settings->get(RestaurantSetting::class);
        $taxSetting = $this->settings->get(TaxSetting::class);
        $invoiceSetting = $this->settings->get(InvoiceSetting::class);
        $posSetting = $this->settings->get(PosSetting::class);

        $waiters = Waiter::query()->where('status', 1)->get();
        $customers = Customer::query()->orderBy('name')->get();
        $sidebarDeliveryPartners = Schema::hasTable('delivery_partners')
            ? DeliveryPartner::with('branch')->orderBy('name')->orderBy('id')->get()
            : collect();
        $availableBranches = Branch::query()->active()->orderByDesc('is_main')->orderBy('name')->get();
        $activeBranch = $this->context->branchId() ? $availableBranches->firstWhere('id', $this->context->branchId()) : null;

        View::share([
            'restaurantSettingName' => $restaurantSetting?->name,
            'restaurantSettingPhone' => $restaurantSetting?->phone,
            'restaurantSettingEmail' => $restaurantSetting?->email,
            'restaurantSettingWebsite' => $restaurantSetting?->website,
            'restaurantSettingAddress' => $restaurantSetting?->address,
            'restaurantSettingOpeningTime' => $restaurantSetting?->opening_time,
            'restaurantSettingClosingTime' => $restaurantSetting?->closing_time,
            'restaurantSettingCurrency' => $restaurantSetting?->currency ?? 'BDT',
            'restaurantSettingIconName' => $restaurantSetting?->icon_name,
            'restaurantSettingLogo' => $restaurantSetting?->logo,
            'taxSettingVatRate' => (float) ($taxSetting?->vat_rate ?? 0),
            'taxSettingTaxLabel' => $taxSetting?->tax_label ?? 'VAT',
            'taxSettingTaxRegistrationNo' => $taxSetting?->tax_registration_no,
            'taxSettingIsTaxIncluded' => (bool) ($taxSetting?->is_tax_included ?? false),
            'taxSettingServiceCharge' => (float) ($taxSetting?->service_charge ?? 0),
            'invoiceSettingPrefix' => $invoiceSetting?->prefix ?? 'INV-',
            'invoiceSettingStartingNumber' => $invoiceSetting?->starting_number ?? 1001,
            'invoiceSettingFooterNote' => $invoiceSetting?->footer_note,
            'invoiceSettingPaperSize' => $invoiceSetting?->paper_size ?? '80mm',
            'invoiceSettingShowLogo' => (bool) ($invoiceSetting?->show_logo ?? true),
            'posSettingDefaultView' => $posSetting?->default_view ?? 'Grid',
            'posSettingItemsPerPage' => $posSetting?->items_per_page ?? 12,
            'posSettingAutoPrintKitchen' => (bool) ($posSetting?->auto_print_kitchen ?? true),
            'posSettingAutoPrintInvoice' => (bool) ($posSetting?->auto_print_invoice ?? true),
            'posSettingRequireTableSelection' => (bool) ($posSetting?->require_table_selection ?? true),
            'posSettingShowOutOfStock' => (bool) ($posSetting?->show_out_of_stock ?? true),
            'restaurantSetting' => $restaurantSetting,
            'taxSetting' => $taxSetting,
            'invoiceSetting' => $invoiceSetting,
            'posSetting' => $posSetting,
            'waiters' => $waiters,
            'customers' => $customers,
            'sidebarDeliveryPartners' => $sidebarDeliveryPartners,
            'activeBranchId' => $this->context->branchId(),
            'allBranchesSelected' => $this->context->isAllBranches(),
            'branchMode' => $this->mode->setting()->mode,
            'availableBranches' => $availableBranches,
            'activeBranch' => $activeBranch,
        ]);
    }

}
