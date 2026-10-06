<?php

namespace App\Services\Hr;

use App\Models\AttendanceSetting;
use App\Models\TipsoiSyncHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TipsoiAttendanceAutoSyncService
{
    public function __construct(private readonly TipsoiService $tipsoiService)
    {
    }

    /**
     * Keep TIPSOI attendance caught up through today.
     *
     * Login and first Attendance-page visits can force a current-month refresh.
     * Browser heartbeat and scheduler runs are incremental and are protected by
     * a shared lock/throttle so multiple tabs/cron jobs cannot overlap.
     */
    public function syncThroughToday(bool $forceCurrentMonth = false, int $minimumIntervalSeconds = 0): array
    {
        $lock = Cache::lock('karachi-tipsoi-attendance-auto-sync', 300);

        if (!$lock->get()) {
            return [
                'ok' => true,
                'busy' => true,
                'skipped' => true,
                'message' => 'Another TIPSOI attendance sync is already running.',
            ];
        }

        try {
            $setting = AttendanceSetting::query()->first();

            if (!$setting?->tipsoi_enabled) {
                return [
                    'ok' => true,
                    'disabled' => true,
                    'skipped' => true,
                    'message' => 'TIPSOI attendance sync is disabled.',
                ];
            }

            if (
                $minimumIntervalSeconds > 0
                && $setting->tipsoi_last_sync_status === 'success'
                && $setting->tipsoi_last_sync_at
                && $setting->tipsoi_last_sync_at->gt(now()->subSeconds($minimumIntervalSeconds))
            ) {
                return [
                    'ok' => true,
                    'throttled' => true,
                    'skipped' => true,
                    'message' => 'TIPSOI attendance was synced recently.',
                ];
            }

            $today = today();
            $fromDate = $this->resolveFromDate($today, $forceCurrentMonth);

            $saved = 0;
            $rawLogs = 0;
            $skipped = 0;
            $chunks = 0;

            foreach ($this->monthlyChunks($fromDate, $today) as [$chunkFrom, $chunkTo]) {
                $result = $this->tipsoiService->pullAttendance(
                    $chunkFrom->toDateString(),
                    $chunkTo->toDateString()
                );

                $saved += (int) ($result['saved'] ?? 0);
                $rawLogs += (int) ($result['raw_logs'] ?? 0);
                $skipped += (int) ($result['skipped'] ?? 0);
                $chunks++;
            }

            return [
                'ok' => true,
                'busy' => false,
                'from_date' => $fromDate->toDateString(),
                'to_date' => $today->toDateString(),
                'saved' => $saved,
                'raw_logs' => $rawLogs,
                'skipped' => $skipped,
                'chunks' => $chunks,
                'message' => sprintf(
                    'TIPSOI attendance sync complete (%s to %s): %d attendance record(s), %d raw punch(es), %d skipped.',
                    $fromDate->format('d M Y'),
                    $today->format('d M Y'),
                    $saved,
                    $rawLogs,
                    $skipped,
                ),
            ];
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // A long remote request can outlive the cache lock TTL. A fresh
                // run can safely acquire a new lock when that happens.
            }
        }
    }

    private function resolveFromDate(Carbon $today, bool $forceCurrentMonth): Carbon
    {
        $monthStart = $today->copy()->startOfMonth();

        $lastSuccessfullyCoveredDate = TipsoiSyncHistory::query()
            ->where('sync_type', 'attendance')
            ->where('status', 'success')
            ->whereNotNull('to_date')
            ->whereDate('to_date', '<=', $today->toDateString())
            ->max('to_date');

        if (!$lastSuccessfullyCoveredDate) {
            return $monthStart;
        }

        $lastCovered = Carbon::parse($lastSuccessfullyCoveredDate)->startOfDay();
        if ($lastCovered->gt($today)) {
            $lastCovered = $today->copy();
        }

        if (!$forceCurrentMonth) {
            // Re-fetch the last covered date so a late punch that reached TIPSOI
            // after the previous sync is still picked up on the next heartbeat.
            return $lastCovered;
        }

        // Login/first Attendance visit refreshes the full current month. If an
        // older successful coverage date exists, keep that older catch-up tail.
        return $lastCovered->lt($monthStart) ? $lastCovered : $monthStart;
    }

    /** @return array<int, array{0: Carbon, 1: Carbon}> */
    private function monthlyChunks(Carbon $from, Carbon $to): array
    {
        $chunks = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $chunkEnd = $cursor->copy()->endOfMonth()->startOfDay();
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            $chunks[] = [$cursor->copy(), $chunkEnd->copy()];
            $cursor = $chunkEnd->copy()->addDay();
        }

        return $chunks;
    }
}
