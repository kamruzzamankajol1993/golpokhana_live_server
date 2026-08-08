<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RestaurantSetting;
use App\Models\TaxSetting;
use App\Models\InvoiceSetting;
use App\Models\PosSetting;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\Facades\Image;

class SettingController extends Controller
{
    public function index()
    {
        $restaurant = RestaurantSetting::first();
        $tax        = TaxSetting::first();
        $invoice    = InvoiceSetting::first();
        $pos        = PosSetting::first();
        $roles      = Role::with('permissions')->get(); // রোলের জন্য

        return view('admin.setting.index', compact('restaurant', 'tax', 'invoice', 'pos', 'roles'));
    }

   public function updateRestaurant(Request $request)
{
    // validation (ঐচ্ছিক কিন্তু দিলে ভালো)
    $request->validate([
        'icon_name' => 'nullable|image|max:100', // শুধুমাত্র PNG, ম্যাক্স ১০০ KB
        'logo'      => 'nullable|image|max:500',
    ]);

    $data = $request->except(['_token', 'logo', 'icon_name']);
    $restaurant = RestaurantSetting::first() ?? new RestaurantSetting();

    // Logo Upload Logic
    if ($request->hasFile('logo')) {
        if ($restaurant->logo && File::exists(public_path($restaurant->logo))) {
            File::delete(public_path($restaurant->logo));
        }
        $imageName = 'logo_' . time() . '.' . $request->logo->extension();
        $request->logo->move(public_path('uploads/settings'), $imageName);
        $data['logo'] = 'uploads/settings/' . $imageName;
    }

    // Icon Upload Logic (PNG)
    if ($request->hasFile('icon_name')) {
        if ($restaurant->icon_name && File::exists(public_path($restaurant->icon_name))) {
            File::delete(public_path($restaurant->icon_name));
        }
        $iconName = 'icon_' . time() . '.png'; // যেহেতু স্পেসিফিকভাবে PNG বলা হয়েছে
        $request->icon_name->move(public_path('uploads/settings'), $iconName);
        $data['icon_name'] = 'uploads/settings/' . $iconName;
    }

    $restaurant->fill($data)->save();
    return back()->with('success', 'Restaurant settings updated!');
}

    public function updateTax(Request $request)
    {
        $data = $request->except(['_token']);
        $data['is_tax_included'] = $request->has('is_tax_included');

        $tax = TaxSetting::first() ?? new TaxSetting();
        $tax->fill($data)->save();
        return back()->with('success', 'Tax configuration updated!');
    }

    public function updateInvoice(Request $request)
    {
        $data = $request->except(['_token']);
        $data['show_logo'] = $request->has('show_logo');

        $invoice = InvoiceSetting::first() ?? new InvoiceSetting();
        $invoice->fill($data)->save();
        return back()->with('success', 'Invoice settings updated!');
    }

    public function updatePos(Request $request)
    {
        // Only a Super Admin may change the global Order List visibility mode.
        $data = $request->except([
            '_token',
            'order_list_random_half_enabled',
            'random_half_order_button_visible',
            'random_order_hide_percentage',
        ]);
        $data['auto_print_kitchen'] = $request->has('auto_print_kitchen');
        $data['auto_print_invoice'] = $request->has('auto_print_invoice');
        $data['require_table_selection'] = $request->has('require_table_selection');
        $data['show_out_of_stock'] = $request->has('show_out_of_stock');
        $data['final_payment_depends_on_kitchen_status'] = $request->has('final_payment_depends_on_kitchen_status');

        if ($this->userHasRoleCaseInsensitive($request->user(), 'Super Admin')) {
            $request->validate([
                'random_order_hide_percentage' => 'nullable|integer|min:1|max:100',
            ]);

            $data['order_list_random_half_enabled'] = $request->boolean('order_list_random_half_enabled');
            $data['random_half_order_button_visible'] = $request->boolean('random_half_order_button_visible');
            $data['random_order_hide_percentage'] = (int) $request->input('random_order_hide_percentage', 50);
        }

        $pos = PosSetting::first() ?? new PosSetting();
        $pos->fill($data);
        $pos->final_payment_depends_on_kitchen_status = $request->has('final_payment_depends_on_kitchen_status');
        $pos->save();

        return back()->with('success', 'POS preferences updated!');
    }

    private function userHasRoleCaseInsensitive($user, string $roleName): bool
    {
        if (!$user) {
            return false;
        }

        return $user->getRoleNames()->contains(function ($assignedRole) use ($roleName) {
            return strcasecmp($assignedRole, $roleName) === 0;
        });
    }
}
