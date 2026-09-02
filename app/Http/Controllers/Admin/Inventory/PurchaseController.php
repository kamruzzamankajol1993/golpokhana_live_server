<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireSpecificBranch;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\RestaurantSetting;
use App\Models\Unit;
use App\Models\Vendor;
use App\Services\BranchSettingResolver;
use App\Services\Inventory\PurchaseReceivingService;
use App\Services\Inventory\PurchaseService;
use App\Support\BranchContext;
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
        $this->middleware(RequireSpecificBranch::class)->only(['store', 'update', 'destroy', 'receive']);
    }

    public function index(Request $request)
    {
        $purchases = Purchase::query()
            ->with(['vendor', 'branch', 'receiver'])
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(fn ($q) => $q->where('purchase_no', 'like', $search)->orWhere('invoice_no', 'like', $search)->orWhere('reference_no', 'like', $search));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->status)))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', (int) $request->vendor_id))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $vendors = Vendor::query()->active()->orderBy('name')->get();
        return view('admin.inventory.purchases.index', compact('purchases', 'vendors'));
    }

    public function create(BranchContext $context)
    {
        return view('admin.inventory.purchases.form', $this->formData($context) + ['purchase' => new Purchase()]);
    }

    public function store(Request $request, BranchContext $context, PurchaseService $service, PurchaseReceivingService $receivingService)
    {
        $data = $this->validated($request);
        $branchId = $context->requireSpecificBranch();
        $vendor = Vendor::query()->findOrFail((int) $data['vendor_id']);
        $receiveNow = ($data['submit_action'] ?? 'draft') === 'receive';

        if ($receiveNow && !$request->user()?->can('inventory-purchase-receive')) {
            abort(403, 'You do not have permission to receive purchases.');
        }

        $newStoredPath = null;
        $oldStoredPath = null;
        try {
            $purchase = DB::transaction(function () use ($request, $service, $receivingService, $branchId, $vendor, $data, $receiveNow, &$newStoredPath, &$oldStoredPath) {
                $purchase = $service->saveDraft($branchId, $vendor, $data, $data['items'], null, $request->user()?->id);
                $this->persistOriginalInvoice($request, $purchase, $newStoredPath, $oldStoredPath);

                if ($receiveNow) {
                    $purchase = $receivingService->receive($purchase, $request->user()?->id);
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

        return redirect()->route('inventory.purchases.show', $purchase)->with(
            'success',
            $receiveNow ? 'Purchase saved and received. Main Stock has been increased.' : 'Draft purchase created. Stock is unchanged until Receive/Post.'
        );
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['items.ingredient.baseUnit', 'items.unit', 'items.packageConversion', 'vendor', 'branch', 'creator', 'receiver', 'receivedMovement']);
        return view('admin.inventory.purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase, BranchContext $context)
    {
        if (!$purchase->isEditable()) {
            return redirect()->route('inventory.purchases.show', $purchase)->with('error', 'Received purchases are immutable. Use a return/reversal workflow in the control phase for corrections.');
        }
        $purchase->load(['items.ingredient.unitConversions.unit', 'items.unit', 'items.packageConversion']);
        return view('admin.inventory.purchases.form', $this->formData($context) + compact('purchase'));
    }

    public function update(Request $request, Purchase $purchase, BranchContext $context, PurchaseService $service, PurchaseReceivingService $receivingService)
    {
        $data = $this->validated($request);
        $branchId = $context->requireSpecificBranch();
        if ((int) $purchase->branch_id !== $branchId) {
            throw ValidationException::withMessages(['branch_id' => 'The selected branch does not match this purchase.']);
        }
        $vendor = Vendor::query()->findOrFail((int) $data['vendor_id']);
        $receiveNow = ($data['submit_action'] ?? 'draft') === 'receive';

        if ($receiveNow && !$request->user()?->can('inventory-purchase-receive')) {
            abort(403, 'You do not have permission to receive purchases.');
        }

        $newStoredPath = null;
        $oldStoredPath = null;
        try {
            $purchase = DB::transaction(function () use ($request, $service, $receivingService, $branchId, $vendor, $data, $purchase, $receiveNow, &$newStoredPath, &$oldStoredPath) {
                $purchase = $service->saveDraft($branchId, $vendor, $data, $data['items'], $purchase, $request->user()?->id);
                $this->persistOriginalInvoice($request, $purchase, $newStoredPath, $oldStoredPath);

                if ($receiveNow) {
                    $purchase = $receivingService->receive($purchase, $request->user()?->id);
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

        return redirect()->route('inventory.purchases.show', $purchase)->with(
            'success',
            $receiveNow ? 'Purchase updated and received. Main Stock has been increased.' : 'Draft purchase updated. Stock is still unchanged.'
        );
    }

    public function receive(Request $request, Purchase $purchase, BranchContext $context, PurchaseReceivingService $service)
    {
        $branchId = $context->requireSpecificBranch();
        if ((int) $purchase->branch_id !== $branchId) {
            throw ValidationException::withMessages(['branch_id' => 'The selected branch does not match this purchase.']);
        }

        $purchase = $service->receive($purchase, $request->user()?->id);
        return redirect()->route('inventory.purchases.show', $purchase)->with('success', 'Purchase received and Main Stock increased through the immutable ledger.');
    }

    public function destroy(Request $request, Purchase $purchase, BranchContext $context)
    {
        $branchId = $context->requireSpecificBranch();
        if ((int) $purchase->branch_id !== $branchId || !$purchase->isEditable()) {
            throw ValidationException::withMessages(['purchase' => 'Only a draft purchase from the selected branch can be deleted.']);
        }

        $originalPath = $purchase->original_invoice_path;
        $purchase->delete();
        if ($originalPath) {
            Storage::disk('local')->delete($originalPath);
        }

        return redirect()->route('inventory.purchases.index')->with('success', 'Draft purchase deleted. No stock was affected.');
    }

    public function invoicePdf(Purchase $purchase, BranchSettingResolver $settings)
    {
        $purchase->load(['items.ingredient.baseUnit', 'items.unit', 'items.packageConversion', 'vendor', 'branch', 'creator', 'receiver']);
        $restaurant = $settings->get(RestaurantSetting::class, (int) $purchase->branch_id, true);

        return $this->renderInvoicePdf($purchase, $restaurant, $purchase->branch);
    }

    public function downloadOriginalInvoice(Purchase $purchase)
    {
        $path = (string) ($purchase->original_invoice_path ?? '');
        if ($path === '' || !Storage::disk('local')->exists($path)) {
            return redirect()->route('inventory.purchases.show', $purchase)->with('error', 'Original invoice file is not available.');
        }

        $downloadName = basename((string) ($purchase->original_invoice_name ?: ('original-invoice-' . $purchase->purchase_no)));
        return Storage::disk('local')->download($path, $downloadName, [
            'Content-Type' => $purchase->original_invoice_mime ?: 'application/octet-stream',
        ]);
    }

    private function renderInvoicePdf(Purchase $purchase, ?RestaurantSetting $restaurant, ?Branch $branch)
    {
        $tempDir = storage_path('app/mpdf-purchase-invoices');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
        ]);

        $fileName = 'purchase-invoice-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $purchase->purchase_no) . '.pdf';
        $mpdf->SetTitle('Purchase Invoice ' . $purchase->purchase_no);
        $mpdf->SetFooter('Purchase ' . $purchase->purchase_no . '||Page {PAGENO} of {nbpg}');
        $mpdf->WriteHTML(view('admin.inventory.purchases.invoice_pdf', compact('purchase', 'restaurant', 'branch'))->render());

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
        $directory = 'inventory/purchase-original-invoices/branch-' . (int) $purchase->branch_id;
        $newStoredPath = $file->store($directory, 'local');
        $oldStoredPath = $purchase->original_invoice_path ?: null;

        $purchase->forceFill([
            'original_invoice_path' => $newStoredPath,
            'original_invoice_name' => basename((string) $file->getClientOriginalName()),
            'original_invoice_mime' => $file->getMimeType(),
            'original_invoice_size' => $file->getSize(),
        ])->save();
    }

    private function formData(BranchContext $context): array
    {
        $ingredients = Ingredient::query()
            ->active()
            ->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->where('purchase_allowed', true)->with('unit')])
            ->orderBy('name')
            ->get();

        return [
            'vendors' => Vendor::query()->active()->orderBy('name')->get(),
            'ingredients' => $ingredients,
            'units' => Unit::query()->active()->orderBy('dimension')->orderBy('name')->get(),
            'branches' => $context->user()?->isSuperAdmin() ? Branch::query()->active()->orderByDesc('is_main')->orderBy('name')->get() : collect(),
            'currentBranchId' => $context->branchId(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
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
