<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireSpecificBranch;
use App\Models\InventoryException;
use App\Models\OrderInventoryConsumption;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExceptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view|inventory-adjustment-post')->only(['index', 'show']);
        $this->middleware('permission:inventory-adjustment-post')->only('resolve');
        $this->middleware(RequireSpecificBranch::class)->only('resolve');
    }

    public function index(Request $request)
    {
        $exceptions = InventoryException::query()
            ->with(['branch', 'ingredient.baseUnit', 'location', 'resolver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(function ($sub) use ($search) {
                    $sub->where('exception_type', 'like', $search)
                        ->orWhere('resolution_note', 'like', $search)
                        ->orWhereHas('ingredient', fn ($ingredient) => $ingredient->where('name', 'like', $search)->orWhere('code', 'like', $search))
                        ->orWhereHas('location', fn ($location) => $location->where('name', 'like', $search)->orWhere('code', 'like', $search));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->status)))
            ->when($request->filled('exception_type'), fn ($q) => $q->where('exception_type', $request->exception_type))
            ->orderByRaw("CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END")
            ->orderByDesc('detected_at')
            ->paginate(25)->appends($request->query());

        $types = InventoryException::query()->select('exception_type')->distinct()->orderBy('exception_type')->pluck('exception_type');
        return view('admin.inventory.exceptions.index', compact('exceptions', 'types'));
    }

    public function show(InventoryException $exception)
    {
        $exception->load(['branch', 'ingredient.baseUnit', 'location', 'resolver']);
        $consumption = null;
        if ($exception->reference_type === OrderInventoryConsumption::class && $exception->reference_id) {
            $consumption = OrderInventoryConsumption::query()->find($exception->reference_id);
        }
        return view('admin.inventory.exceptions.show', compact('exception', 'consumption'));
    }

    public function resolve(Request $request, InventoryException $exception, BranchContext $context)
    {
        $branchId = $context->requireSpecificBranch();
        if ((int) $exception->branch_id !== $branchId) {
            throw ValidationException::withMessages(['branch_id' => 'The selected branch does not match this inventory exception.']);
        }
        $data = $request->validate(['resolution_note' => ['required', 'string', 'max:3000']]);
        if ($exception->status === InventoryException::STATUS_RESOLVED) {
            return back()->with('success', 'This inventory exception is already resolved.');
        }

        $exception->status = InventoryException::STATUS_RESOLVED;
        $exception->resolved_at = now();
        $exception->resolved_by = $request->user()?->id;
        $existingContext = trim((string) $exception->resolution_note);
        $exception->resolution_note = $existingContext !== ''
            ? $existingContext . "\nResolution: " . $data['resolution_note']
            : $data['resolution_note'];
        $exception->save();

        return back()->with('success', 'Inventory exception marked resolved. No stock balance was overwritten.');
    }
}
