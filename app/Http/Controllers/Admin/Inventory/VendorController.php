<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-vendors-manage');
    }

    public function index(Request $request)
    {
        $vendors = Vendor::query()
            ->withCount('purchases')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('tin', 'like', $search)
                    ->orWhere('bin', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.inventory.vendors.index', compact('vendors'));
    }

    public function create()
    {
        return view('admin.inventory.vendors.form', ['vendor' => new Vendor()]);
    }

    public function store(Request $request)
    {
        $vendor = Vendor::query()->create($this->validated($request));
        $this->persistDocuments($request, $vendor);

        return redirect()->route('inventory.vendors.show', $vendor)->with('success', 'Vendor created successfully.');
    }

    public function show(Vendor $vendor)
    {
        $vendor->loadCount('purchases');

        $purchases = $vendor->purchases()
            ->with(['receiver', 'voucher'])
            ->withCount('items')
            ->withSum('vendorPayments', 'amount')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        $payments = $vendor->payments()
            ->with(['purchase', 'creator'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $outstandingPurchases = $vendor->purchases()
            ->where('status', Purchase::STATUS_RECEIVED)
            ->withSum('vendorPayments', 'amount')
            ->orderByDesc('purchase_date')
            ->get()
            ->filter(fn (Purchase $purchase) => $purchase->dueAmount() > 0.0001)
            ->values();

        $totalPurchase = (float) $vendor->purchases()->where('status', Purchase::STATUS_RECEIVED)->sum('total');
        $totalPaid = (float) $vendor->payments()->sum('amount');
        $totalDue = max(0, round($totalPurchase - $totalPaid, 4));

        $cardTypes = VendorPayment::CARD_TYPES;
        $mfsProviders = VendorPayment::MFS_PROVIDERS;

        return view('admin.inventory.vendors.show', compact(
            'vendor', 'purchases', 'payments', 'outstandingPurchases',
            'totalPurchase', 'totalPaid', 'totalDue', 'cardTypes', 'mfsProviders'
        ));
    }

    public function edit(Vendor $vendor)
    {
        return view('admin.inventory.vendors.form', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($this->validated($request, $vendor));
        $this->persistDocuments($request, $vendor);

        return redirect()->route('inventory.vendors.show', $vendor)->with('success', 'Vendor updated successfully.');
    }

    public function storePayment(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'integer', 'exists:purchases,id'],
            'payment_date' => ['required', 'date'],
            'payment_type' => ['required', Rule::in([
                VendorPayment::TYPE_CASH,
                VendorPayment::TYPE_CARD,
                VendorPayment::TYPE_MFS,
                VendorPayment::TYPE_SPLIT,
            ])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_in_cash' => ['nullable', 'numeric', 'gte:0'],
            'paid_in_card' => ['nullable', 'numeric', 'gte:0'],
            'paid_in_mfs' => ['nullable', 'numeric', 'gte:0'],
            'card_type' => ['nullable', Rule::in(VendorPayment::CARD_TYPES)],
            'mfs_provider' => ['nullable', Rule::in(VendorPayment::MFS_PROVIDERS)],
            'card_reference' => ['nullable', 'string', 'max:255'],
            'mfs_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        $amount = round((float) $data['amount'], 4);
        $cash = 0.0;
        $card = 0.0;
        $mfs = 0.0;
        $cardType = null;
        $mfsProvider = null;
        $cardReference = null;
        $mfsReference = null;

        if ($data['payment_type'] === VendorPayment::TYPE_CASH) {
            $cash = $amount;
        } elseif ($data['payment_type'] === VendorPayment::TYPE_CARD) {
            $card = $amount;
            $cardType = trim((string) ($data['card_type'] ?? ''));
            $cardReference = trim((string) ($data['card_reference'] ?? ''));
            if ($cardType === '' || $cardReference === '') {
                throw ValidationException::withMessages([
                    'card_reference' => 'Card type and Bank / Card reference are required for Card payment.',
                ]);
            }
        } elseif ($data['payment_type'] === VendorPayment::TYPE_MFS) {
            $mfs = $amount;
            $mfsProvider = trim((string) ($data['mfs_provider'] ?? ''));
            $mfsReference = trim((string) ($data['mfs_reference'] ?? ''));
            if ($mfsProvider === '' || $mfsReference === '') {
                throw ValidationException::withMessages([
                    'mfs_reference' => 'MFS provider and reference are required for MFS payment.',
                ]);
            }
        } else {
            $cash = round(max(0, (float) ($data['paid_in_cash'] ?? 0)), 4);
            $card = round(max(0, (float) ($data['paid_in_card'] ?? 0)), 4);
            $mfs = round(max(0, (float) ($data['paid_in_mfs'] ?? 0)), 4);
            $splitTotal = round($cash + $card + $mfs, 4);

            if ($splitTotal <= 0 || abs($splitTotal - $amount) > 0.0099) {
                throw ValidationException::withMessages([
                    'amount' => 'For Split payment, Cash + Card + MFS must exactly match the total payment amount.',
                ]);
            }

            if ($card > 0) {
                $cardType = trim((string) ($data['card_type'] ?? ''));
                $cardReference = trim((string) ($data['card_reference'] ?? ''));
                if ($cardType === '' || $cardReference === '') {
                    throw ValidationException::withMessages([
                        'card_reference' => 'Card type and Bank / Card reference are required when Split includes Card.',
                    ]);
                }
            }

            if ($mfs > 0) {
                $mfsProvider = trim((string) ($data['mfs_provider'] ?? ''));
                $mfsReference = trim((string) ($data['mfs_reference'] ?? ''));
                if ($mfsProvider === '' || $mfsReference === '') {
                    throw ValidationException::withMessages([
                        'mfs_reference' => 'MFS provider and reference are required when Split includes MFS.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($request, $vendor, $data, $amount, $cash, $card, $mfs, $cardType, $mfsProvider, $cardReference, $mfsReference) {
            $purchase = Purchase::query()
                ->whereKey((int) $data['purchase_id'])
                ->where('vendor_id', $vendor->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchase->status !== Purchase::STATUS_RECEIVED) {
                throw ValidationException::withMessages([
                    'purchase_id' => 'Vendor payment can be posted only against a received purchase.',
                ]);
            }

            $alreadyPaid = (float) VendorPayment::query()
                ->where('purchase_id', $purchase->id)
                ->lockForUpdate()
                ->get()
                ->sum('amount');
            $due = max(0, round((float) $purchase->total - $alreadyPaid, 4));

            if ($amount > $due + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment cannot exceed the purchase due of ৳' . number_format($due, 2) . '.',
                ]);
            }

            VendorPayment::query()->create([
                'vendor_id' => $vendor->id,
                'purchase_id' => $purchase->id,
                'payment_no' => $this->nextPaymentNumber(),
                'payment_date' => $data['payment_date'],
                'payment_type' => $data['payment_type'],
                'amount' => $amount,
                'paid_in_cash' => $cash,
                'paid_in_card' => $card,
                'paid_in_mfs' => $mfs,
                'card_type' => $cardType,
                'mfs_provider' => $mfsProvider,
                'card_reference' => $cardReference,
                'mfs_reference' => $mfsReference,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()?->id,
            ]);
        }, 5);

        return redirect()->route('inventory.vendors.show', $vendor)->with('success', 'Vendor payment posted successfully.');
    }

    public function document(Vendor $vendor, string $type)
    {
        $map = [
            'tin' => ['path' => 'tin_file_path', 'name' => 'tin_file_name'],
            'bin' => ['path' => 'bin_file_path', 'name' => 'bin_file_name'],
            'tax' => ['path' => 'tax_file_path', 'name' => 'tax_file_name'],
        ];

        abort_unless(isset($map[$type]), 404);
        $path = (string) ($vendor->{$map[$type]['path']} ?? '');
        abort_if($path === '' || !Storage::disk('local')->exists($path), 404);

        $downloadName = basename((string) ($vendor->{$map[$type]['name']} ?: strtoupper($type) . '-document'));
        return Storage::disk('local')->download($path, $downloadName);
    }

    public function destroy(Vendor $vendor)
    {
        if ($vendor->purchases()->exists() || $vendor->purchaseVouchers()->exists() || $vendor->payments()->exists()) {
            return redirect()->route('inventory.vendors.index')
                ->with('error', 'This vendor has inventory or payment history and cannot be deleted. Set it Inactive instead.');
        }

        try {
            $paths = array_filter([$vendor->tin_file_path, $vendor->bin_file_path, $vendor->tax_file_path]);
            $vendor->delete();
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }
        } catch (QueryException $exception) {
            return redirect()->route('inventory.vendors.index')
                ->with('error', 'This vendor is linked to inventory history and cannot be deleted. Set it Inactive instead.');
        }

        return redirect()->route('inventory.vendors.index')->with('success', 'Vendor deleted successfully.');
    }

    private function validated(Request $request, ?Vendor $vendor = null): array
    {
        $request->merge([
            'name' => trim((string) $request->name),
            'phone' => $request->filled('phone') ? trim((string) $request->phone) : null,
            'email' => $request->filled('email') ? trim((string) $request->email) : null,
            'tin' => $request->filled('tin') ? trim((string) $request->tin) : null,
            'bin' => $request->filled('bin') ? trim((string) $request->bin) : null,
            'tds' => $request->filled('tds') ? trim((string) $request->tds) : null,
            'vds' => $request->filled('vds') ? trim((string) $request->vds) : null,
            'tax' => $request->filled('tax') ? trim((string) $request->tax) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('vendors', 'email')->ignore($vendor?->id)],
            'address' => ['nullable', 'string', 'max:2000'],
            'tin' => ['nullable', 'string', 'max:160'],
            'bin' => ['nullable', 'string', 'max:160'],
            'tds' => ['nullable', 'string', 'max:160'],
            'vds' => ['nullable', 'string', 'max:160'],
            'tax' => ['nullable', 'string', 'max:160'],
            'tin_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'bin_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'tax_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        unset($data['tin_file'], $data['bin_file'], $data['tax_file']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function persistDocuments(Request $request, Vendor $vendor): void
    {
        $map = [
            'tin_file' => ['path' => 'tin_file_path', 'name' => 'tin_file_name'],
            'bin_file' => ['path' => 'bin_file_path', 'name' => 'bin_file_name'],
            'tax_file' => ['path' => 'tax_file_path', 'name' => 'tax_file_name'],
        ];

        foreach ($map as $input => $columns) {
            if (!$request->hasFile($input)) {
                continue;
            }

            $file = $request->file($input);
            $newPath = $file->store('inventory/vendor-documents/' . $vendor->id, 'local');
            $oldPath = $vendor->{$columns['path']};

            try {
                $vendor->forceFill([
                    $columns['path'] => $newPath,
                    $columns['name'] => basename((string) $file->getClientOriginalName()),
                ])->save();
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($newPath);
                throw $e;
            }

            if ($oldPath && $oldPath !== $newPath) {
                Storage::disk('local')->delete($oldPath);
            }
        }
    }

    private function nextPaymentNumber(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = 'VPAY-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(5));
            if (!VendorPayment::query()->where('payment_no', $number)->exists()) {
                return $number;
            }
        }

        throw ValidationException::withMessages(['payment' => 'Could not allocate a unique vendor payment number. Please try again.']);
    }
}
