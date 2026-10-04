<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryPurchaseApprover;
use App\Models\InventoryPurchaseApprovalSetting;
use App\Models\PurchaseVoucherApproval;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PurchaseApprovalSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-purchase-approval-settings');
    }

    public function index()
    {
        // Kept for backward-compatible bookmarks/links. The UI now lives under System Settings.
        return redirect()->route('settings.index', ['tab' => 'purchase-approval']);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'minimum_approvers' => ['required', 'integer', 'min:1', 'max:10'],
            'approvers' => ['required_if:is_enabled,1', 'array', 'max:10'],
            'approvers.*.user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'approvers.*.approval_order' => ['nullable', 'integer', 'min:1', 'max:10', 'required_with:approvers.*.user_id'],
        ]);

        $enabled = $request->boolean('is_enabled');
        $rows = collect($data['approvers'] ?? [])
            ->filter(fn ($row) => !empty($row['user_id']))
            ->sortBy('approval_order')
            ->values();

        if ($enabled && $rows->count() < (int) $data['minimum_approvers']) {
            return back()->withErrors([
                'approvers' => 'Please select at least ' . (int) $data['minimum_approvers'] . ' approval officer(s).',
            ])->withInput();
        }

        if ($rows->pluck('user_id')->filter()->duplicates()->isNotEmpty()) {
            return back()->withErrors(['approvers' => 'The same user cannot be added more than once.'])->withInput();
        }
        if ($rows->pluck('approval_order')->filter()->duplicates()->isNotEmpty()) {
            return back()->withErrors(['approvers' => 'Each approval level/order must be unique.'])->withInput();
        }

        DB::transaction(function () use ($enabled, $data, $rows) {
            $setting = InventoryPurchaseApprovalSetting::query()->firstOrNew();
            $setting->fill([
                'is_enabled' => $enabled,
                'sequential_approval' => false,
                'minimum_approvers' => (int) $data['minimum_approvers'],
            ])->save();

            $oldUserIds = InventoryPurchaseApprover::query()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $newUserIds = $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all();

            InventoryPurchaseApprover::query()->delete();
            foreach ($rows as $row) {
                InventoryPurchaseApprover::query()->create([
                    'user_id' => (int) $row['user_id'],
                    'approval_order' => (int) $row['approval_order'],
                    'is_active' => true,
                ]);
            }

            $permission = Permission::findOrCreate('inventory-purchase-approve', 'web');
            foreach (array_diff($oldUserIds, $newUserIds) as $userId) {
                $user = User::query()->find($userId);
                $hasPendingSnapshotApproval = PurchaseVoucherApproval::query()
                    ->where('approver_user_id', $userId)
                    ->where('status', PurchaseVoucherApproval::STATUS_PENDING)
                    ->exists();
                if ($user && !$hasPendingSnapshotApproval && $user->hasDirectPermission($permission)) {
                    $user->revokePermissionTo($permission);
                }
            }
            foreach ($newUserIds as $userId) {
                $user = User::query()->find($userId);
                if ($user && !$user->hasPermissionTo($permission)) {
                    $user->givePermissionTo($permission);
                }
            }
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('settings.index', ['tab' => 'purchase-approval'])
            ->with('success', 'Purchase approval setup updated successfully.');
    }
}
