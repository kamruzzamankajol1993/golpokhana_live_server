<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfflinePosDevice;
use App\Models\OfflinePosSyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

class OfflinePosSyncLogController extends Controller
{
    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->canManageOfflinePosDevices(), 403, 'Offline POS Sync Logs are not available for this user.');
    }


    public function destroySelected(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        if (!Schema::hasTable('offline_pos_sync_logs')) {
            return back()->with('error', 'Offline POS sync log table is not available.');
        }

        $ids = collect($request->input('log_ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Select at least one sync log to delete.');
        }

        $deleted = OfflinePosSyncLog::query()->whereIn('id', $ids->all())->delete();

        return back()->with('success', $deleted . ' offline sync log(s) deleted.');
    }

    public function index(Request $request)
    {
        $this->authorizeAccess($request);

        $tableReady = Schema::hasTable('offline_pos_sync_logs');
        $devices = OfflinePosDevice::query()->orderBy('device_name')->orderBy('id')->get(['id', 'device_name', 'device_uuid']);

        if (!$tableReady) {
            return view('admin.offline_pos_sync_logs.index', [
                'tableReady' => false,
                'devices' => $devices,
                'logs' => null,
                'stats' => ['total' => 0, 'success' => 0, 'partial' => 0, 'failed' => 0],
                'types' => collect(),
            ]);
        }

        $base = OfflinePosSyncLog::query();

        $status = trim((string) $request->query('status', ''));
        $deviceId = trim((string) $request->query('device_id', ''));
        $type = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('q', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        if ($status !== '') {
            $base->where('status', $status);
        }
        if ($deviceId !== '') {
            $base->where('device_id', $deviceId);
        }
        if ($type !== '') {
            $base->where('type', $type);
        }
        if ($dateFrom !== '') {
            $base->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $base->whereDate('created_at', '<=', $dateTo);
        }
        if ($search !== '') {
            $base->where(function ($query) use ($search) {
                $query->where('message', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhereHas('device', function ($deviceQuery) use ($search) {
                        $deviceQuery->where('device_name', 'like', '%' . $search . '%')
                            ->orWhere('device_uuid', 'like', '%' . $search . '%');
                    });
            });
        }

        $logs = (clone $base)
            ->with('device:id,device_name,device_uuid')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => OfflinePosSyncLog::count(),
            'success' => OfflinePosSyncLog::where('status', 'success')->count(),
            'partial' => OfflinePosSyncLog::where('status', 'partial')->count(),
            'failed' => OfflinePosSyncLog::where('status', 'failed')->count(),
        ];

        $types = OfflinePosSyncLog::query()
            ->select('type')
            ->whereNotNull('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return view('admin.offline_pos_sync_logs.index', compact('tableReady', 'devices', 'logs', 'stats', 'types'));
    }
}
