<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\PurchaseVoucher;
use App\Models\RestaurantSetting;
use App\Models\Unit;
use App\Models\Vendor;
use App\Services\Inventory\InventorySiteContext;
use App\Services\Inventory\PurchaseVoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;

class PurchaseVoucherController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-purchase-voucher-manage')->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy', 'submit', 'sendToVendor', 'receiveSupply',
        ]);
        $this->middleware('permission:inventory-purchase-voucher-manage|inventory-purchase-approve')->only(['show', 'pdf']);
    }

    public function index(Request $request)
    {
        $vouchers = PurchaseVoucher::query()
            ->with(['vendor', 'creator'])
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(fn ($q) => $q->where('voucher_no', 'like', $search)
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $search)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->status)))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', (int) $request->vendor_id))
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $vendors = Vendor::query()->active()->orderBy('name')->get();
        return view('admin.inventory.purchase_vouchers.index', compact('vouchers', 'vendors'));
    }

    public function create(InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        return view('admin.inventory.purchase_vouchers.form', $this->formData() + [
            'voucher' => new PurchaseVoucher(),
        ]);
    }

    public function store(Request $request, PurchaseVoucherService $service)
    {
        $data = $this->validated($request);
        $vendor = Vendor::query()->findOrFail((int) $data['vendor_id']);
        $submitNow = ($data['submit_action'] ?? 'draft') === 'submit';
        $voucher = DB::transaction(function () use ($service, $vendor, $data, $request, $submitNow) {
            $voucher = $service->saveDraft($vendor, $data, $data['items'], null, $request->user()?->id);
            return $submitNow ? $service->submit($voucher, $request->user()?->id) : $voucher;
        });
        $message = $submitNow
            ? 'Purchase voucher created and sent for approval.'
            : 'Purchase voucher saved as Draft. No purchase or stock has been created.';

        return redirect()->route('inventory.purchase-vouchers.show', $voucher)->with('success', $message);
    }

    public function show(PurchaseVoucher $purchaseVoucher, PurchaseVoucherService $service)
    {
        $this->authorizeView($purchaseVoucher);
        $purchaseVoucher->load([
            'vendor', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion',
            'creator', 'sentToVendorBy', 'currentApprovals.approver', 'revisions.archivedBy', 'purchase', 'convertedPurchase',
        ]);
        $canAct = auth()->id() ? $service->canUserAct($purchaseVoucher, (int) auth()->id()) : false;
        return view('admin.inventory.purchase_vouchers.show', ['voucher' => $purchaseVoucher, 'canAct' => $canAct]);
    }

    public function edit(PurchaseVoucher $purchaseVoucher)
    {
        if (!$purchaseVoucher->isEditable()) {
            return redirect()->route('inventory.purchase-vouchers.show', $purchaseVoucher)
                ->with('error', 'Only a Draft or Rejected voucher can be edited.');
        }
        $purchaseVoucher->load(['items.ingredient.unitConversions.unit', 'items.unit', 'items.packageConversion']);
        return view('admin.inventory.purchase_vouchers.form', $this->formData() + ['voucher' => $purchaseVoucher]);
    }

    public function update(Request $request, PurchaseVoucher $purchaseVoucher, PurchaseVoucherService $service)
    {
        $data = $this->validated($request);
        $vendor = Vendor::query()->findOrFail((int) $data['vendor_id']);
        $submitNow = ($data['submit_action'] ?? 'draft') === 'submit';
        $voucher = DB::transaction(function () use ($service, $vendor, $data, $purchaseVoucher, $request, $submitNow) {
            $voucher = $service->saveDraft($vendor, $data, $data['items'], $purchaseVoucher, $request->user()?->id);
            return $submitNow ? $service->submit($voucher, $request->user()?->id) : $voucher;
        });
        $message = $submitNow
            ? 'Purchase voucher updated and sent for approval.'
            : 'Purchase voucher Draft updated.';

        return redirect()->route('inventory.purchase-vouchers.show', $voucher)->with('success', $message);
    }

    public function destroy(PurchaseVoucher $purchaseVoucher)
    {
        if (!$purchaseVoucher->isEditable() || $purchaseVoucher->purchase()->exists()) {
            throw ValidationException::withMessages(['voucher' => 'Only a Draft or Rejected voucher with no supplier receipt can be deleted.']);
        }
        $purchaseVoucher->delete();
        return redirect()->route('inventory.purchase-vouchers.index')->with('success', 'Purchase voucher deleted.');
    }

    public function submit(Request $request, PurchaseVoucher $purchaseVoucher, PurchaseVoucherService $service)
    {
        $service->submit($purchaseVoucher, $request->user()?->id);
        return redirect()->route('inventory.purchase-vouchers.show', $purchaseVoucher)
            ->with('success', 'Voucher sent to the configured approval chain.');
    }

    public function sendToVendor(Request $request, PurchaseVoucher $purchaseVoucher, PurchaseVoucherService $service)
    {
        $service->sendToVendor($purchaseVoucher, (int) $request->user()->id);
        return redirect()->route('inventory.purchase-vouchers.show', $purchaseVoucher)
            ->with('success', 'Approved voucher marked as Sent to Vendor. You can now download/share the approved PDF.');
    }

    public function receiveSupply(PurchaseVoucher $purchaseVoucher, PurchaseVoucherService $service)
    {
        $purchaseVoucher->load('purchase');
        $service->assertReadyForSupply($purchaseVoucher);

        if ($purchaseVoucher->purchase) {
            return redirect()->route('inventory.purchases.edit', $purchaseVoucher->purchase);
        }

        return redirect()->route('inventory.purchases.create', ['voucher_id' => $purchaseVoucher->id]);
    }

    public function pdf(PurchaseVoucher $purchaseVoucher)
    {
        $this->authorizeView($purchaseVoucher);
        $purchaseVoucher->load([
            'vendor', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion',
            'creator', 'sentToVendorBy', 'currentApprovals.approver',
        ]);
        $restaurant = RestaurantSetting::query()->first();
        $tempDir = storage_path('app/mpdf-purchase-vouchers');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4',
            'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 14,
            'tempDir' => $tempDir, 'autoScriptToLang' => true, 'autoLangToFont' => true, 'default_font' => 'freesans',
        ]);
        $mpdf->SetTitle('Purchase Voucher ' . $purchaseVoucher->voucher_no);
        $mpdf->WriteHTML(view('admin.inventory.purchase_vouchers.voucher_pdf', [
            'voucher' => $purchaseVoucher,
            'restaurant' => $restaurant,
        ])->render());

        $name = 'purchase-voucher-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $purchaseVoucher->voucher_no) . '-r' . $purchaseVoucher->revision_no . '.pdf';
        return response($mpdf->Output($name, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }


    private function authorizeView(PurchaseVoucher $voucher): void
    {
        $user = request()->user();
        if ($user?->can('inventory-purchase-voucher-manage')) {
            return;
        }
        $assigned = $user && $voucher->approvals()->where('approver_user_id', $user->id)->exists();
        abort_unless($assigned, 403, 'This purchase voucher is not assigned to you.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')],
            'voucher_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'submit_action' => ['nullable', Rule::in(['draft', 'submit'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', Rule::exists('ingredients', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_choice' => ['required', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function formData(): array
    {
        return [
            'vendors' => Vendor::query()->active()->orderBy('name')->get(),
            'ingredients' => Ingredient::query()
                ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
                ->where('is_active', true)->where('track_inventory', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('dimension')->orderBy('name')->get(),
        ];
    }
}
