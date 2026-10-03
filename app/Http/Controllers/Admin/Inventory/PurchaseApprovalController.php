<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\PurchaseVoucher;
use App\Models\PurchaseVoucherApproval;
use App\Services\Inventory\PurchaseVoucherService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-purchase-approve');
    }

    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;
        $approvals = PurchaseVoucherApproval::query()
            ->with(['voucher.vendor', 'approver'])
            ->where('approver_user_id', $userId)
            ->whereHas('voucher', fn ($q) => $q->whereColumn('purchase_voucher_approvals.revision_no', 'purchase_vouchers.revision_no'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->status)))
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.inventory.purchase_approvals.index', compact('approvals'));
    }

    public function approve(Request $request, PurchaseVoucher $purchaseVoucher, PurchaseVoucherService $service)
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $service->approve($purchaseVoucher, (int) $request->user()->id, $data['comment'] ?? null);
        return redirect()->route('inventory.purchase-vouchers.show', $purchaseVoucher)
            ->with('success', 'Purchase voucher approved successfully.');
    }

    public function reject(Request $request, PurchaseVoucher $purchaseVoucher, PurchaseVoucherService $service)
    {
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        $service->reject($purchaseVoucher, (int) $request->user()->id, $data['comment']);
        return redirect()->route('inventory.purchase-vouchers.show', $purchaseVoucher)
            ->with('success', 'Purchase voucher rejected and returned for correction.');
    }
}
