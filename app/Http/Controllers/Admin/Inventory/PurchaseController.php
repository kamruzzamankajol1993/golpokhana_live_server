<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\PurchaseVoucher;
use App\Models\RestaurantSetting;
use App\Models\Unit;
use App\Models\Vendor;
use App\Services\Inventory\InventorySiteContext;
use App\Services\Inventory\PurchaseReceivingService;
use App\Services\Inventory\PurchaseService;
use App\Services\Inventory\PurchaseVoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;

class PurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-purchase-create|inventory-purchase-receive')->only(['index', 'show', 'invoicePdf', 'downloadOriginalInvoice']);
        $this->middleware('permission:inventory-purchase-create')->only(['create', 'store', 'edit', 'update', 'destroy']);
        $this->middleware('permission:inventory-purchase-receive')->only('receive');
    }

    public function index(Request $request, InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        $purchases = Purchase::query()
            ->with(['vendor', 'receiver', 'voucher'])
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(fn ($q) => $q->where('purchase_no', 'like', $search)
                    ->orWhere('invoice_no', 'like', $search)
                    ->orWhere('reference_no', 'like', $search)
                    ->orWhereHas('voucher', fn ($v) => $v->where('voucher_no', 'like', $search)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->status)))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', (int) $request->vendor_id))
            ->orderByDesc('purchase_date')->orderByDesc('id')
            ->paginate(20)->appends($request->query());

        $vendors = Vendor::query()->active()->orderBy('name')->get();
        return view('admin.inventory.purchases.index', compact('purchases', 'vendors'));
    }

    public function create(Request $request, InventorySiteContext $site, PurchaseVoucherService $voucherService)
    {
        $site->ensureDefaultLocations();
        $voucherId = (int) $request->query('voucher_id', 0);
        if ($voucherId < 1) {
            return redirect()->route('inventory.purchase-vouchers.create')
                ->with('error', 'Step 3 workflow requires an approved Purchase Voucher before receiving supplier stock.');
        }

        $sourceVoucher = PurchaseVoucher::query()
            ->with(['vendor', 'items.ingredient.unitConversions.unit', 'items.unit', 'items.packageConversion', 'purchase'])
            ->findOrFail($voucherId);
        $voucherService->assertReadyForSupply($sourceVoucher);

        if ($sourceVoucher->purchase) {
            return $sourceVoucher->purchase->isEditable()
                ? redirect()->route('inventory.purchases.edit', $sourceVoucher->purchase)
                : redirect()->route('inventory.purchases.show', $sourceVoucher->purchase);
        }

        $purchase = new Purchase([
            'purchase_voucher_id' => $sourceVoucher->id,
            'vendor_id' => $sourceVoucher->vendor_id,
            'purchase_date' => now()->format('Y-m-d'),
            'discount' => $sourceVoucher->discount,
            'tax' => $sourceVoucher->tax,
            'notes' => 'Received against approved voucher ' . $sourceVoucher->voucher_no,
        ]);

        return view('admin.inventory.purchases.form', $this->formData($site) + compact('purchase', 'sourceVoucher'));
    }

    public function store(
        Request $request,
        InventorySiteContext $site,
        PurchaseService $service,
        PurchaseReceivingService $receivingService,
        PurchaseVoucherService $voucherService
    ) {
        $data = $this->validated($request, true);
        $site->ensureDefaultLocations();
        $voucher = PurchaseVoucher::query()->with('items')->findOrFail((int) $data['purchase_voucher_id']);
        $voucherService->assertReadyForSupply($voucher);
        if ($voucher->purchase()->exists()) {
            throw ValidationException::withMessages(['voucher' => 'A purchase already exists for this voucher.']);
        }

        $data['vendor_id'] = $voucher->vendor_id;
        $data['purchase_voucher_id'] = $voucher->id;
        $vendor = $voucher->vendor()->firstOrFail();
        $receiveNow = ($data['submit_action'] ?? 'receive') === 'receive';
        if ($receiveNow && !$request->user()?->can('inventory-purchase-receive')) {
            abort(403, 'You do not have permission to receive purchases.');
        }

        $newStoredPath = null;
        $oldStoredPath = null;
        $reapproval = false;
        try {
            $purchase = DB::transaction(function () use ($request, $service, $receivingService, $voucherService, $vendor, $voucher, $data, $receiveNow, &$newStoredPath, &$oldStoredPath, &$reapproval) {
                $purchase = $service->saveDraft($vendor, $data, $data['items'], null, $request->user()?->id);
                $this->persistOriginalInvoice($request, $purchase, $newStoredPath, $oldStoredPath);

                if ($receiveNow) {
                    $reasons = $voucherService->overrunReasons($voucher, $purchase);
                    if ($reasons !== []) {
                        $voucherService->requestReapprovalFromPurchase($voucher, $purchase, $request->user()?->id, $reasons);
                        $reapproval = true;
                    } else {
                        $purchase = $receivingService->receive($purchase, $request->user()?->id);
                    }
                }
                return $purchase;
            });
        } catch (\Throwable $e) {
            if ($newStoredPath) {
                Storage::disk('local')->delete($newStoredPath);
            }
            throw $e;
        }

        if ($oldStoredPath && $oldStoredPath !== $newStoredPath) {
            Storage::disk('local')->delete($oldStoredPath);
        }

        if ($reapproval) {
            return redirect()->route('inventory.purchase-vouchers.show', $voucher)
                ->with('error', 'Actual supplied quantity/value exceeded the approved voucher. Stock was NOT increased; a new approval round has been started automatically.');
        }

        return redirect()->route('inventory.purchases.show', $purchase)->with(
            'success',
            $receiveNow ? 'Supplier invoice recorded and supply received. Store Stock has been increased.' : 'Supplier invoice saved as Draft. Stock is unchanged.'
        );
    }

    public function show(Purchase $purchase, InventorySiteContext $site)
    {
        $this->assertSitePurchase($purchase, $site);
        $purchase->load(['items.ingredient.baseUnit', 'items.unit', 'items.packageConversion', 'vendor', 'creator', 'receiver', 'receivedMovement', 'voucher.items.ingredient.baseUnit']);
        return view('admin.inventory.purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase, InventorySiteContext $site, PurchaseVoucherService $voucherService)
    {
        $this->assertSitePurchase($purchase, $site);
        if (!$purchase->isEditable()) {
            return redirect()->route('inventory.purchases.show', $purchase)->with('error', 'Received purchases are immutable.');
        }
        $purchase->load(['items.ingredient.unitConversions.unit', 'items.unit', 'items.packageConversion', 'voucher.items']);
        $sourceVoucher = $purchase->voucher;
        if ($sourceVoucher && !$sourceVoucher->canReceiveSupply()) {
            return redirect()->route('inventory.purchase-vouchers.show', $sourceVoucher)
                ->with('error', 'This purchase is waiting for voucher approval/re-approval. It cannot be changed or received yet.');
        }
        return view('admin.inventory.purchases.form', $this->formData($site) + compact('purchase', 'sourceVoucher'));
    }

    public function update(
        Request $request,
        Purchase $purchase,
        InventorySiteContext $site,
        PurchaseService $service,
        PurchaseReceivingService $receivingService,
        PurchaseVoucherService $voucherService
    ) {
        $data = $this->validated($request, (bool) $purchase->purchase_voucher_id);
        $site->ensureDefaultLocations();
        $purchase->load('voucher.items');
        $voucher = $purchase->voucher;
        if ($voucher) {
            $voucherService->assertReadyForSupply($voucher);
            $data['vendor_id'] = $voucher->vendor_id;
            $data['purchase_voucher_id'] = $voucher->id;
        }
        $vendor = Vendor::query()->findOrFail((int) $data['vendor_id']);
        $receiveNow = ($data['submit_action'] ?? 'draft') === 'receive';
        if ($receiveNow && !$request->user()?->can('inventory-purchase-receive')) {
            abort(403, 'You do not have permission to receive purchases.');
        }

        $newStoredPath = null;
        $oldStoredPath = null;
        $reapproval = false;
        try {
            $purchase = DB::transaction(function () use ($request, $service, $receivingService, $voucherService, $vendor, $data, $purchase, $voucher, $receiveNow, &$newStoredPath, &$oldStoredPath, &$reapproval) {
                $purchase = $service->saveDraft($vendor, $data, $data['items'], $purchase, $request->user()?->id);
                $this->persistOriginalInvoice($request, $purchase, $newStoredPath, $oldStoredPath);

                if ($receiveNow) {
                    if ($voucher) {
                        $reasons = $voucherService->overrunReasons($voucher, $purchase);
                        if ($reasons !== []) {
                            $voucherService->requestReapprovalFromPurchase($voucher, $purchase, $request->user()?->id, $reasons);
                            $reapproval = true;
                        } else {
                            $purchase = $receivingService->receive($purchase, $request->user()?->id);
                        }
                    } else {
                        // Backward compatibility for legacy Draft purchases created before Step 3.
                        $purchase = $receivingService->receive($purchase, $request->user()?->id);
                    }
                }
                return $purchase;
            });
        } catch (\Throwable $e) {
            if ($newStoredPath) {
                Storage::disk('local')->delete($newStoredPath);
            }
            throw $e;
        }

        if ($oldStoredPath && $oldStoredPath !== $newStoredPath) {
            Storage::disk('local')->delete($oldStoredPath);
        }

        if ($reapproval && $voucher) {
            return redirect()->route('inventory.purchase-vouchers.show', $voucher)
                ->with('error', 'Actual supplied quantity/value exceeded approval. Stock was NOT increased; re-approval has started.');
        }

        return redirect()->route('inventory.purchases.show', $purchase)->with(
            'success',
            $receiveNow ? 'Supplier invoice updated and supply received. Store Stock has been increased.' : 'Draft purchase updated. Stock is unchanged.'
        );
    }

    public function receive(Request $request, Purchase $purchase, InventorySiteContext $site, PurchaseReceivingService $service, PurchaseVoucherService $voucherService)
    {
        $site->ensureDefaultLocations();
        $purchase->load(['voucher.items', 'items.ingredient']);
        if ($purchase->voucher) {
            $voucher = $purchase->voucher;
            $voucherService->assertReadyForSupply($voucher);
            $reasons = $voucherService->overrunReasons($voucher, $purchase);
            if ($reasons !== []) {
                $voucherService->requestReapprovalFromPurchase($voucher, $purchase, $request->user()?->id, $reasons);
                return redirect()->route('inventory.purchase-vouchers.show', $voucher)
                    ->with('error', 'Supply exceeds the approved quantity/value. Re-approval started; stock was not increased.');
            }
        }

        $purchase = $service->receive($purchase, $request->user()?->id);
        return redirect()->route('inventory.purchases.show', $purchase)->with('success', 'Purchase received and Store Stock increased through Transaction History.');
    }

    public function destroy(Request $request, Purchase $purchase, InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        if (!$purchase->isEditable()) {
            throw ValidationException::withMessages(['purchase' => 'Only a draft purchase can be deleted.']);
        }
        $originalPath = $purchase->original_invoice_path;
        $purchase->delete();
        if ($originalPath) {
            Storage::disk('local')->delete($originalPath);
        }
        return redirect()->route('inventory.purchases.index')->with('success', 'Draft purchase deleted. No stock was affected.');
    }

    public function invoicePdf(Purchase $purchase, InventorySiteContext $site)
    {
        $this->assertSitePurchase($purchase, $site);
        $purchase->load(['items.ingredient.baseUnit', 'items.unit', 'items.packageConversion', 'vendor', 'creator', 'receiver', 'voucher.items.ingredient.baseUnit']);
        $restaurant = RestaurantSetting::query()->first();
        return $this->renderInvoicePdf($purchase, $restaurant);
    }

    public function downloadOriginalInvoice(Purchase $purchase, InventorySiteContext $site)
    {
        $this->assertSitePurchase($purchase, $site);
        $path = (string) ($purchase->original_invoice_path ?? '');
        if ($path === '' || !Storage::disk('local')->exists($path)) {
            return redirect()->route('inventory.purchases.show', $purchase)->with('error', 'Original invoice file is not available.');
        }
        $downloadName = basename((string) ($purchase->original_invoice_name ?: ('original-invoice-' . $purchase->purchase_no)));
        return Storage::disk('local')->download($path, $downloadName, [
            'Content-Type' => $purchase->original_invoice_mime ?: 'application/octet-stream',
        ]);
    }

    private function assertSitePurchase(Purchase $purchase, InventorySiteContext $site): void
    {
        $site->ensureDefaultLocations();
    }

    private function renderInvoicePdf(Purchase $purchase, ?RestaurantSetting $restaurant)
    {
        $tempDir = storage_path('app/mpdf-purchase-invoices');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14,
            'margin_top' => 14, 'margin_bottom' => 16, 'tempDir' => $tempDir,
            'autoScriptToLang' => true, 'autoLangToFont' => true, 'default_font' => 'freesans',
        ]);
        $fileName = 'purchase-invoice-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $purchase->purchase_no) . '.pdf';
        $mpdf->SetTitle('Purchase Invoice ' . $purchase->purchase_no);
        $mpdf->SetFooter('Purchase ' . $purchase->purchase_no . '||Page {PAGENO} of {nbpg}');
        $mpdf->WriteHTML(view('admin.inventory.purchases.invoice_pdf', compact('purchase', 'restaurant'))->render());
        return response($mpdf->Output($fileName, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function persistOriginalInvoice(Request $request, Purchase $purchase, ?string &$newStoredPath, ?string &$oldStoredPath): void
    {
        if (!$request->hasFile('original_invoice_file')) {
            return;
        }
        $file = $request->file('original_invoice_file');
        $newStoredPath = $file->store('inventory/purchase-original-invoices', 'local');
        $oldStoredPath = $purchase->original_invoice_path ?: null;
        $purchase->forceFill([
            'original_invoice_path' => $newStoredPath,
            'original_invoice_name' => basename((string) $file->getClientOriginalName()),
            'original_invoice_mime' => $file->getMimeType(),
            'original_invoice_size' => $file->getSize(),
        ])->save();
    }

    private function formData(InventorySiteContext $site): array
    {
        $ingredients = Ingredient::query()->active()->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')->get();
        return [
            'vendors' => Vendor::query()->active()->orderBy('name')->get(),
            'ingredients' => $ingredients,
            'units' => Unit::query()->active()->orderBy('dimension')->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, bool $voucherRequired = false): array
    {
        return $request->validate([
            'purchase_voucher_id' => [$voucherRequired ? 'required' : 'nullable', 'integer', 'exists:purchase_vouchers,id'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'purchase_date' => ['required', 'date'],
            'invoice_no' => ['nullable', 'string', 'max:120'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'original_invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'discount' => ['nullable', 'numeric', 'gte:0'],
            'tax' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'submit_action' => ['nullable', Rule::in(['draft', 'receive'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_choice' => ['required', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ]);
    }
}
