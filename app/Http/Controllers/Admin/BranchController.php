<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchModeManager;
use App\Services\SuperAdminPosSessionManager;
use App\Services\BranchNumberingService;
use App\Services\BranchSettingResolver;
use App\Services\AuditLogger;
use App\Services\Inventory\StockLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isFullSuperAdmin(), 403, 'Branch Management is available only to the full Super Admin.');
    }

    private function authorizeFullSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isFullSuperAdmin(), 403, 'Only the full Super Admin can change Branch Mode.');
    }

    public function index(Request $request, BranchModeManager $mode)
    {
        $this->authorizeSuperAdmin($request);

        $branches = Branch::query()
            ->withCount(['users' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        return view('admin.branch.index', [
            'branches' => $branches,
            'modeSetting' => $mode->setting(),
            'hasSecondaryData' => $mode->hasSecondaryBranchBusinessData(),
        ]);
    }

    public function updateMode(
        Request $request,
        BranchModeManager $mode,
        AuditLogger $audit,
        SuperAdminPosSessionManager $posSessions
    )
    {
        $this->authorizeFullSuperAdmin($request);
        $data = $request->validate(['mode' => ['required', Rule::in(['single', 'multiple'])]]);

        $beforeMode = $mode->setting()->mode;

        if ($data['mode'] === 'multiple') {
            $posSessions->closeAllForUser($request->user(), 'super_admin_session.branch_mode_changed');
            $mode->activateMultipleMode();
            $mainBranchId = (int) $mode->mainBranchId();
            $request->session()->put('active_branch_id', $mainBranchId);
            $audit->log('branch_mode.changed', $mainBranchId, 'Branch mode changed to multiple.', ['mode' => $beforeMode], ['mode' => 'multiple', 'active_branch_id' => $mainBranchId]);
            return back()->with('success', 'Multiple Branch Mode activated. Main Branch is selected.');
        }

        $posSessions->closeAllForUser($request->user(), 'super_admin_session.branch_mode_changed');
        $mode->switchToSingleMode();
        $mainBranchId = (int) $mode->mainBranchId();
        $request->session()->put('active_branch_id', $mainBranchId);
        $audit->log('branch_mode.changed', $mainBranchId, 'Branch mode changed to single.', ['mode' => $beforeMode], ['mode' => 'single', 'active_branch_id' => $mainBranchId]);
        return back()->with('success', 'Single Branch Mode activated. Main Branch is selected.');
    }

    public function store(Request $request, BranchModeManager $mode, BranchSettingResolver $settings, BranchNumberingService $numbering, AuditLogger $audit, StockLocationService $stockLocations)
    {
        $this->authorizeSuperAdmin($request);
        abort_unless($mode->isMultipleMode(), 422, 'Activate Multiple Branch Mode before adding branches.');

        $request->merge([
            'code' => strtoupper(trim((string) $request->code)),
            'slug' => $request->filled('slug') ? Str::slug((string) $request->slug) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', 'unique:branches,code'],
            'slug' => ['nullable', 'string', 'max:180', 'unique:branches,slug'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $slug = $data['slug'] ?: Str::slug($data['name']);
        $slug = $slug !== '' ? $slug : 'branch-' . Str::lower(Str::random(8));
        if (Branch::query()->where('slug', $slug)->exists()) {
            return back()->withErrors(['slug' => 'This branch slug is already in use.'])->withInput();
        }

        $branch = Branch::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'slug' => $slug,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'is_main' => false,
            'status' => true,
        ]);

        // Phase 5: every new branch starts with an independent copy of branch-scoped settings
        // and its own locked Order/Invoice/KOT sequence state.
        $settings->seedBranch((int) $branch->id);
        $numbering->initializeBranch((int) $branch->id);
        if (Schema::hasTable('stock_locations')) {
            $stockLocations->ensureDefaultLocations((int) $branch->id);
        }
        $audit->log('branch.created', (int) $branch->id, 'Branch created: ' . $branch->name, null, $branch->getAttributes(), Branch::class, (int) $branch->id, ['branch_code' => $branch->code]);

        return back()->with('success', 'Branch created successfully. Branch settings, numbering sequences, and inventory stock locations are ready.');
    }

    public function update(Request $request, Branch $branch, BranchModeManager $mode, AuditLogger $audit)
    {
        $this->authorizeSuperAdmin($request);
        abort_unless($mode->isMultipleMode(), 422);

        $request->merge([
            'code' => strtoupper(trim((string) $request->code)),
            'slug' => Str::slug((string) $request->slug),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('branches', 'code')->ignore($branch->id)],
            'slug' => ['required', 'string', 'max:180', Rule::unique('branches', 'slug')->ignore($branch->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $branch->only(['name', 'code', 'slug', 'phone', 'email', 'address', 'status']);

        $branch->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'slug' => $data['slug'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
        $audit->log('branch.updated', (int) $branch->id, 'Branch updated: ' . $branch->name, $before, $branch->only(array_keys($before)), Branch::class, (int) $branch->id, ['branch_code' => $branch->code]);

        return back()->with('success', 'Branch updated successfully.');
    }

    public function toggleStatus(Request $request, Branch $branch, BranchModeManager $mode, AuditLogger $audit)
    {
        $this->authorizeSuperAdmin($request);
        abort_unless($mode->isMultipleMode(), 422);

        if ($branch->is_main && $branch->status) {
            return back()->with('error', 'Main Branch cannot be deactivated.');
        }

        $oldStatus = (bool) $branch->status;
        $branch->update(['status' => !$branch->status]);
        $audit->log('branch.status_changed', (int) $branch->id, 'Branch status changed: ' . $branch->name, ['status' => $oldStatus], ['status' => (bool) $branch->status], Branch::class, (int) $branch->id, ['branch_code' => $branch->code]);

        if (!$branch->status && (int) $request->session()->get('active_branch_id') === (int) $branch->id) {
            $request->session()->forget('active_branch_id');
        }

        return back()->with('success', 'Branch status updated.');
    }

    public function destroy(Request $request, Branch $branch, BranchModeManager $mode, AuditLogger $audit)
    {
        $this->authorizeSuperAdmin($request);
        abort_unless($mode->isMultipleMode(), 422);

        if ($branch->is_main) {
            return back()->with('error', 'Main Branch cannot be deleted.');
        }

        if ($mode->branchHasBusinessData((int) $branch->id)) {
            return back()->with('error', 'This branch contains business data. Deactivate it instead of deleting it.');
        }

        DB::transaction(function () use ($branch, $audit) {
            $audit->log('branch.deleted', (int) $branch->id, 'Empty branch deleted: ' . $branch->name, $branch->getAttributes(), null, Branch::class, (int) $branch->id, ['branch_code' => $branch->code]);
            // Phase 5 creates branch-scoped configuration rows automatically. They are
            // configuration, not business data, so remove them before deleting an otherwise
            // empty branch; their restrictOnDelete FKs intentionally prevent accidental loss.
            foreach (BranchSettingResolver::SETTING_MODELS as $modelClass) {
                $model = new $modelClass();
                if (Schema::hasTable($model->getTable()) && Schema::hasColumn($model->getTable(), 'branch_id')) {
                    DB::table($model->getTable())->where('branch_id', $branch->id)->delete();
                }
            }

            if (Schema::hasTable('branch_number_sequences')) {
                DB::table('branch_number_sequences')->where('branch_id', $branch->id)->delete();
            }

            if (Schema::hasTable('stock_locations')) {
                DB::table('stock_locations')->where('branch_id', $branch->id)->delete();
            }

            $branch->delete();
        });

        return back()->with('success', 'Empty branch deleted successfully.');
    }
}
