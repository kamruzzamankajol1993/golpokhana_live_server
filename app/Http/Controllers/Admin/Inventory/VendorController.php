<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('phone', 'like', $search)->orWhere('email', 'like', $search));
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
        Vendor::query()->create($this->validated($request));
        return redirect()->route('inventory.vendors.index')->with('success', 'Vendor created successfully.');
    }

    public function edit(Vendor $vendor)
    {
        return view('admin.inventory.vendors.form', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($this->validated($request, $vendor));
        return redirect()->route('inventory.vendors.index')->with('success', 'Vendor updated successfully.');
    }

    public function destroy(Vendor $vendor)
    {
        if ($vendor->purchases()->exists()) {
            return redirect()->route('inventory.vendors.index')
                ->with('error', 'This vendor already has purchase history and cannot be deleted. Set it Inactive instead.');
        }

        try {
            $vendor->delete();
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
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('vendors', 'email')->ignore($vendor?->id)],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
