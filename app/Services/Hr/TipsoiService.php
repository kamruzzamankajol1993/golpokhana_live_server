<?php

namespace App\Services\Hr;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Models\TipsoiDevice;
use App\Models\TipsoiRemotePerson;
use App\Models\TipsoiAttendanceLog;
use App\Models\TipsoiSyncHistory;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TipsoiService
{
    private const DEMO_URL = 'https://test.api-inovace360.com/api/v1';
    private const LIVE_URL = 'https://api-inovace360.com/api/v1';

    // TIPSOI Device API V2.4 documented paths.
    private const PATH_RAW_LOGS = 'logs';
    private const PATH_ATTENDANCE_LOGS = 'attendance_logs';
    private const PATH_PEOPLE = 'people';
    private const PATH_DEVICES = 'devices';

    public function activeConfiguration(): array
    {
        $setting = AttendanceSetting::first();

        if (!$setting || !$setting->tipsoi_enabled) {
            throw new RuntimeException('Tipsoi integration is disabled. Enable it from HR Settings > Attendance.');
        }

        $mode = in_array($setting->tipsoi_mode, ['demo', 'live'], true) ? $setting->tipsoi_mode : 'live';
        $baseUrl = $mode === 'demo'
            ? ($setting->tipsoi_demo_url ?: self::DEMO_URL)
            : ($setting->tipsoi_live_url ?: self::LIVE_URL);
        $apiKey = $mode === 'demo'
            ? $setting->tipsoi_demo_api_key
            : $setting->tipsoi_live_api_key;

        if (blank($apiKey)) {
            throw new RuntimeException(strtoupper($mode) . ' Tipsoi API key is missing.');
        }

        $sslMode = in_array($setting->tipsoi_ssl_mode, ['auto', 'verify', 'disable'], true)
            ? $setting->tipsoi_ssl_mode
            : 'auto';

        return [
            'setting' => $setting,
            'mode' => $mode,
            'base_url' => rtrim((string) $baseUrl, '/'),
            'api_key' => (string) $apiKey,
            'ssl_mode' => $sslMode,
            'verify_ssl' => $this->shouldVerifySsl($sslMode),
        ];
    }

    public function testConnection(): array
    {
        $config = $this->activeConfiguration();
        $people = $this->getPeople($config);
        $devices = $this->getDevices($config);

        return [
            'mode' => $config['mode'],
            'base_url' => $config['base_url'],
            'remote_people' => count($people),
            'remote_devices' => count($devices),
            'ssl_mode' => $config['ssl_mode'],
            'message' => 'Tipsoi connection successful.',
        ];
    }

    public function pullEmployees(): array
    {
        $config = $this->activeConfiguration();
        $people = $this->getPeople($config);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($people as $person) {
            if (!is_array($person)) {
                $skipped++;
                continue;
            }

            try {
                $identifier = trim((string) ($person['identifier'] ?? ''));
                $personId = $person['id'] ?? null;
                $employee = $this->findEmployee($identifier, $personId);

                if (!$employee) {
                    $employee = Employee::create([
                        'employee_code' => $this->uniqueEmployeeCode($identifier, $personId),
                        'name' => trim((string) ($person['name'] ?? '')) ?: 'Tipsoi Employee',
                        'phone' => '',
                        'join_date' => now()->toDateString(),
                        'employment_status' => 'active',
                        'is_waiter' => false,
                        'can_login' => false,
                    ]);
                    $created++;
                } else {
                    $updated++;
                }

                $this->applyRemotePerson($employee, $person, true);
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = mb_substr($e->getMessage(), 0, 300);
                Log::warning('[TIPSOI][KARACHI] Employee pull row failed', [
                    'identifier' => $person['identifier'] ?? null,
                    'person_id' => $person['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "Tipsoi employee pull complete: {$created} created, {$updated} updated, {$skipped} skipped.";
        $this->rememberSyncResult('success', $message);

        return compact('created', 'updated', 'skipped', 'errors', 'message');
    }

    public function pushEmployee(Employee $employee): array
    {
        $config = $this->activeConfiguration();
        $identifier = trim((string) ($employee->tipsoi_identifier ?: $employee->employee_code));
        if ($identifier === '') {
            $identifier = 'EMP-' . $employee->id;
        }

        $name = trim((string) $employee->name) ?: ('Employee ' . $employee->id);
        $primary = mb_substr(trim((string) ($employee->tipsoi_primary_display_text ?: $name)), 0, 10);
        $secondary = mb_substr(trim((string) ($employee->tipsoi_secondary_display_text ?: $employee->employee_code ?: $identifier)), 0, 10);

        $payload = [
            'identifier' => $identifier,
            'name' => $name,
            'primary_display_text' => $primary ?: 'Employee',
            'secondary_display_text' => $secondary ?: 'Employee',
            'rfid' => (string) ($employee->tipsoi_rfid ?? ''),
        ];

        try {
            $response = $this->postMultipart($config, self::PATH_PEOPLE, $payload);
            $response->throw();
            $json = $response->json() ?: [];
            $remote = is_array($json['payload'] ?? null) ? $json['payload'] : [];

            if (!$remote) {
                $remote = $this->findRemotePersonByIdentifier($identifier, $config) ?: [];
            }

            $this->applyRemotePerson($employee, array_merge(['identifier' => $identifier], $remote), false);
            $employee->forceFill([
                'tipsoi_identifier' => $identifier,
                'tipsoi_synced_at' => now(),
                'tipsoi_sync_status' => 'synced',
                'tipsoi_sync_error' => null,
            ])->save();

            // Image/image_base64 is optional in the V2.4 Person Create/Update API.
            // Push it separately so a photo problem never prevents the base person from syncing.
            $this->syncEmployeePhotoBestEffort($employee, $config, $payload);

            return [
                'success' => true,
                'employee_id' => $employee->id,
                'identifier' => $identifier,
                'message' => $employee->name . ' pushed to Tipsoi successfully.',
            ];
        } catch (Throwable $e) {
            // Some Tipsoi installations can persist a person even when the create
            // response itself fails. Recover by re-reading the remote person before
            // marking the local employee as failed.
            try {
                $remote = $this->findRemotePersonByIdentifier($identifier, $config);
                if ($remote) {
                    $this->applyRemotePerson($employee, array_merge(['identifier' => $identifier], $remote), false);
                    $employee->forceFill([
                        'tipsoi_identifier' => $identifier,
                        'tipsoi_synced_at' => now(),
                        'tipsoi_sync_status' => 'synced',
                        'tipsoi_sync_error' => null,
                    ])->save();
                    $this->syncEmployeePhotoBestEffort($employee, $config, $payload);

                    return [
                        'success' => true,
                        'employee_id' => $employee->id,
                        'identifier' => $identifier,
                        'message' => $employee->name . ' exists in Tipsoi and was linked successfully.',
                        'recovered' => true,
                    ];
                }
            } catch (Throwable $recoveryError) {
                Log::warning('[TIPSOI][KARACHI] Employee push recovery failed', [
                    'employee_id' => $employee->id,
                    'identifier' => $identifier,
                    'push_error' => $e->getMessage(),
                    'recovery_error' => $recoveryError->getMessage(),
                ]);
            }

            $employee->forceFill([
                'tipsoi_identifier' => $identifier,
                'tipsoi_sync_status' => 'failed',
                'tipsoi_sync_error' => mb_substr($e->getMessage(), 0, 5000),
            ])->save();
            throw $e;
        }
    }

    public function pushEmployees(?Collection $employees = null): array
    {
        $employees ??= Employee::query()->where('employment_status', 'active')->orderBy('id')->get();
        $employees = $employees->values();

        if ($employees->isEmpty()) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [],
                'message' => 'No employees selected for Tipsoi push.',
            ];
        }

        $config = $this->activeConfiguration();
        $success = 0;
        $failed = 0;
        $errors = [];

        // Device API V2.4 documents Batch Person Create as POST /people
        // with a JSON body shaped as {"people":[...]}. Use that endpoint first;
        // if a deployment rejects the batch request, fall back to the documented
        // single-person multipart request so one bad batch does not block HR work.
        foreach ($employees->chunk(10) as $chunk) {
            $people = $chunk->map(fn (Employee $employee) => $this->personPayload($employee))->values()->all();

            try {
                $response = $this->client($config)
                    ->withQueryParameters(['api_token' => $config['api_key']])
                    ->asJson()
                    ->post($this->endpoint($config, self::PATH_PEOPLE), ['people' => $people]);
                $response->throw();

                $json = $response->json() ?: [];
                $remotePayload = collect(is_array($json['payload'] ?? null) ? $json['payload'] : [])->keyBy('identifier');

                foreach ($chunk as $employee) {
                    $identifier = trim((string) ($employee->tipsoi_identifier ?: $employee->employee_code)) ?: ('EMP-' . $employee->id);
                    $remote = $remotePayload->get($identifier);
                    if (!is_array($remote) || !$remote) {
                        $remote = $this->findRemotePersonByIdentifier($identifier, $config) ?: ['identifier' => $identifier];
                    }

                    $this->applyRemotePerson($employee, array_merge(['identifier' => $identifier], $remote), false);
                    $employee->forceFill([
                        'tipsoi_identifier' => $identifier,
                        'tipsoi_synced_at' => now(),
                        'tipsoi_sync_status' => 'synced',
                        'tipsoi_sync_error' => null,
                    ])->save();
                    $this->syncEmployeePhotoBestEffort($employee, $config, $this->personPayload($employee));
                    $success++;
                }
            } catch (Throwable $batchError) {
                Log::warning('[TIPSOI][KARACHI] Batch person create failed; falling back to single person sync', [
                    'employee_ids' => $chunk->pluck('id')->all(),
                    'error' => $batchError->getMessage(),
                ]);

                foreach ($chunk as $employee) {
                    try {
                        $this->pushEmployee($employee);
                        $success++;
                    } catch (Throwable $e) {
                        $failed++;
                        $errors[] = $employee->employee_code . ': ' . mb_substr($e->getMessage(), 0, 250);
                    }
                }
            }
        }

        $message = "Tipsoi employee batch push complete: {$success} successful, {$failed} failed.";
        $this->rememberSyncResult($failed ? 'partial' : 'success', $message);

        return compact('success', 'failed', 'errors', 'message');
    }

    public function pullAttendance(string $from, string $to): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);
        $config = $this->activeConfiguration();
        $history = TipsoiSyncHistory::create([
            'sync_type' => 'attendance',
            'mode' => $config['mode'],
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'status' => 'running',
            'created_by' => auth()->id(),
        ]);

        $page = 1;
        $lastPage = 1;
        $saved = 0;
        $skipped = 0;

        try {
            do {
                $response = $this->client($config)->get(
                    $this->endpoint($config, self::PATH_ATTENDANCE_LOGS),
                    $this->queryWithToken($config, [
                        'start' => $fromDate,
                        'end' => $toDate,
                        'per_page' => 100,
                        'page' => $page,
                    ])
                );
                $response->throw();
                $json = $response->json() ?: [];
                $rows = data_get($json, 'attendances.data', []);

                foreach (is_array($rows) ? $rows : [] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $identifier = trim((string) ($row['person_identifier'] ?? ''));
                    $personId = $row['person_id'] ?? null;
                    $employee = $this->findEmployee($identifier, $personId);
                    $logs = is_array($row['logs'] ?? null) ? $row['logs'] : [];

                    if (!$employee) {
                        $skipped += max(1, count($logs));
                        $this->cacheRemotePerson([
                            'id' => $personId,
                            'identifier' => $identifier,
                            'name' => $row['name'] ?? null,
                            'rfid' => $row['rfid'] ?? null,
                            'primary_display_text' => $row['primary_display_text'] ?? null,
                            'secondary_display_text' => $row['secondary_display_text'] ?? null,
                        ], null);
                        continue;
                    }

                    $this->applyRemotePerson($employee, [
                        'id' => $personId,
                        'identifier' => $identifier,
                        'name' => $row['name'] ?? null,
                        'rfid' => $row['rfid'] ?? null,
                        'primary_display_text' => $row['primary_display_text'] ?? null,
                        'secondary_display_text' => $row['secondary_display_text'] ?? null,
                    ], false);

                    foreach ($logs as $dateKey => $log) {
                        if (!is_array($log)) {
                            continue;
                        }

                        $date = (string) ($log['date'] ?? $dateKey);
                        if (!$date) {
                            $skipped++;
                            continue;
                        }

                        // TIPSOI attendance_logs is calendar-day based. Keep it as a
                        // fallback, but normalize full datetimes and API hours instead of
                        // turning equal/single punches into a fake 24-hour checkout. Raw
                        // punches are rebuilt into schedule-day attendance just below.
                        $resolved = $this->calculateTipsoiSummaryAttendance($employee, $date, $log);
                        $this->correctJoinDateFromAttendance($employee, $date);

                        $existing = Attendance::query()
                            ->where('employee_id', $employee->id)
                            ->whereDate('attendance_date', $date)
                            ->first();

                        // Once HR edits a TIPSOI row manually, background sync must not
                        // silently overwrite that deliberate correction.
                        if (!$existing || $existing->source !== 'manual') {
                            Attendance::updateOrCreate(
                                ['employee_id' => $employee->id, 'attendance_date' => $date],
                                array_merge($resolved, [
                                    'source' => 'system',
                                    'notes' => null,
                                    'marked_by' => null,
                                    'tipsoi_project_id' => $row['project_id'] ?? null,
                                    'tipsoi_person_id' => $personId,
                                    'tipsoi_person_identifier' => $identifier ?: $employee->employee_code,
                                    'tipsoi_person_name' => $row['name'] ?? $employee->name,
                                    'tipsoi_rfid' => $row['rfid'] ?? null,
                                    'tipsoi_primary_display_text' => $row['primary_display_text'] ?? null,
                                    'tipsoi_secondary_display_text' => $row['secondary_display_text'] ?? null,
                                    'tipsoi_hours' => $log['hours'] ?? null,
                                    'tipsoi_external_id' => 'tipsoi-' . ($personId ?: $employee->id) . '-' . $date,
                                    'tipsoi_synced_at' => now(),
                                    'tipsoi_push_status' => 'remote',
                                    'tipsoi_push_error' => null,
                                ])
                            );
                            $saved++;
                        }
                    }
                }

                $lastPage = max(1, (int) data_get($json, 'attendances.last_page', 1));
                $page++;
            } while ($page <= $lastPage && $page <= 1000);

            // An overnight business day can finish after midnight. When a
            // historical day/range is pulled, include the following calendar day
            // in raw punches so its checkout is available for normalization.
            $rawToDate = Carbon::parse($toDate)->lt(today())
                ? Carbon::parse($toDate)->addDay()->toDateString()
                : $toDate;
            $raw = $this->syncRawLogs($fromDate, $rawToDate, false);
            $message = "Tipsoi attendance pull complete: {$saved} summary record(s), {$raw['saved']} raw punch(es), {$raw['rebuilt']} normalized attendance day(s), {$skipped} skipped.";
            $this->rememberSyncResult('success', $message);
            $history->update([
                'summary_records' => $saved,
                'raw_logs' => $raw['saved'],
                'skipped_records' => $skipped + $raw['skipped'],
                'status' => 'success',
                'message' => $message,
            ]);

            return [
                'saved' => $saved,
                'raw_logs' => $raw['saved'],
                'skipped' => $skipped + $raw['skipped'],
                'message' => $message,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
            ];
        } catch (Throwable $e) {
            $history->update([
                'summary_records' => $saved,
                'skipped_records' => $skipped,
                'status' => 'failed',
                'message' => mb_substr($e->getMessage(), 0, 5000),
            ]);
            $this->rememberSyncResult('failed', 'Tipsoi attendance pull failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Pull raw device punches using the documented GET /logs endpoint.
     * sync_time is the recommended criteria in the vendor documentation.
     */
    public function getRawLogs(string $start, string $end, int $page = 1, int $perPage = 500): array
    {
        $config = $this->activeConfiguration();
        $response = $this->client($config)->get(
            $this->endpoint($config, self::PATH_RAW_LOGS),
            $this->queryWithToken($config, [
                'start' => $start,
                'end' => $end,
                'page' => max(1, $page),
                'per_page' => max(1, min(500, $perPage)),
                'criteria' => 'sync_time',
                'order_key' => 'sync_time',
                'order_direction' => 'asc',
            ])
        );
        $response->throw();
        return $response->json() ?: [];
    }

    public function syncRawLogs(string $from, string $to, bool $remember = true): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($from, $to);
        $page = 1;
        $lastPage = 1;
        $saved = 0;
        $skipped = 0;
        $touchedBusinessDays = [];
        $touchedCalendarDays = [];

        do {
            $json = $this->getRawLogs($fromDate . ' 00:00:00', $toDate . ' 23:59:59', $page, 500);
            $rows = is_array($json['data'] ?? null) ? $json['data'] : [];
            $project = is_array($json['project'] ?? null) ? $json['project'] : [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    $skipped++;
                    continue;
                }

                $identifier = trim((string) ($row['person_identifier'] ?? ''));
                $personId = $row['person_id'] ?? null;
                $employee = $this->findEmployee($identifier, $personId);
                $uid = trim((string) ($row['uid'] ?? ''));
                if ($uid === '') {
                    $uid = sha1(implode('|', [
                        $row['device_identifier'] ?? '',
                        $identifier,
                        $row['logged_time'] ?? '',
                        $row['type'] ?? '',
                    ]));
                }

                TipsoiAttendanceLog::updateOrCreate(
                    ['uid' => $uid],
                    [
                        'sync_time' => $this->parseNullableDateTime($row['sync_time'] ?? null),
                        'logged_time' => $this->parseNullableDateTime($row['logged_time'] ?? null),
                        'type' => $row['type'] ?? null,
                        'device_identifier' => $row['device_identifier'] ?? null,
                        'location' => $row['location'] ?? null,
                        'person_id' => $personId,
                        'person_identifier' => $identifier ?: null,
                        'rfid' => $row['rfid'] ?? null,
                        'primary_display_text' => $row['primary_display_text'] ?? null,
                        'secondary_display_text' => $row['secondary_display_text'] ?? null,
                        'project_code' => $project['code'] ?? null,
                        'project_name' => $project['name'] ?? null,
                        'project_organization' => $project['organization'] ?? null,
                        'employee_id' => $employee?->id,
                        'raw_payload' => $row,
                    ]
                );
                $saved++;

                if (!$employee) {
                    $skipped++;
                    continue;
                }

                // Use the literal TIPSOI logged_time string from raw_payload. The
                // database column is TIMESTAMP and may be timezone-converted by
                // MySQL; the vendor string is the correct wall-clock punch time.
                $localLoggedAt = $this->parseTipsoiLocalDateTime($row['logged_time'] ?? null);
                if (!$localLoggedAt) {
                    continue;
                }

                $businessDate = $this->businessDateForPunch($employee, $localLoggedAt);
                $businessKey = $employee->id . '|' . $businessDate;
                $calendarKey = $employee->id . '|' . $localLoggedAt->toDateString();
                $touchedBusinessDays[$businessKey] = [$employee->id, $businessDate];
                $touchedCalendarDays[$calendarKey] = [$employee->id, $localLoggedAt->toDateString()];
            }

            $lastPage = max(1, (int) data_get($json, 'meta.last_page', 1));
            $page++;
        } while ($page <= $lastPage && $page <= 1000);

        $rebuilt = 0;
        $rebuiltKeys = [];
        foreach ($touchedBusinessDays as $key => [$employeeId, $businessDate]) {
            if ($this->rebuildAttendanceFromRawPunches((int) $employeeId, (string) $businessDate)) {
                $rebuilt++;
                $rebuiltKeys[$key] = true;
            }
        }

        // attendance_logs groups by calendar date, which creates false rows for
        // overnight-shift checkout punches (e.g. 02:23 AM becoming a new day).
        // Remove only system/TIPSOI fallback rows for a touched calendar day when
        // raw-punch normalization assigned every punch to another business day.
        foreach ($touchedCalendarDays as $calendarKey => [$employeeId, $calendarDate]) {
            if (isset($rebuiltKeys[$calendarKey])) {
                continue;
            }

            Attendance::query()
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', $calendarDate)
                ->where('source', 'system')
                ->whereNotNull('tipsoi_synced_at')
                ->where(function ($query) {
                    $query->whereNull('tipsoi_external_id')
                        ->orWhere('tipsoi_external_id', 'not like', 'tipsoi-raw-%');
                })
                ->delete();
        }

        $message = "Tipsoi raw punch sync complete: {$saved} saved/updated, {$rebuilt} normalized attendance day(s), {$skipped} skipped.";
        if ($remember) {
            $this->rememberSyncResult('success', $message);
        }

        return compact('saved', 'rebuilt', 'skipped', 'message');
    }

    public function refreshPeopleMapping(): array
    {
        $config = $this->activeConfiguration();
        $people = $this->getPeople($config);
        $matched = 0;
        $unmatched = 0;

        foreach ($people as $person) {
            if (!is_array($person)) {
                continue;
            }

            $identifier = trim((string) ($person['identifier'] ?? ''));
            $employee = $this->findEmployee($identifier, $person['id'] ?? null);
            if ($employee) {
                $this->applyRemotePerson($employee, $person, false);
                $matched++;
            } else {
                $this->cacheRemotePerson($person, null);
                $unmatched++;
            }
        }

        $message = "Tipsoi people refreshed: " . count($people) . " remote, {$matched} matched, {$unmatched} unmatched.";
        $this->rememberSyncResult('success', $message);

        return [
            'remote_count' => count($people),
            'matched' => $matched,
            'unmatched' => $unmatched,
            'message' => $message,
        ];
    }

    public function linkEmployeeFromRemotePerson(Employee $employee, TipsoiRemotePerson $remotePerson): void
    {
        $payload = is_array($remotePerson->raw_payload) ? $remotePerson->raw_payload : [];
        $payload = array_merge($payload, [
            'id' => $remotePerson->tipsoi_person_id,
            'id_in_device' => $remotePerson->id_in_device,
            'identifier' => $remotePerson->identifier,
            'old_identifier' => $remotePerson->old_identifier,
            'name' => $remotePerson->name,
            'photo_url' => $remotePerson->photo_url,
            'rfid' => $remotePerson->rfid,
            'primary_display_text' => $remotePerson->primary_display_text,
            'secondary_display_text' => $remotePerson->secondary_display_text,
            'description' => $remotePerson->description,
            'person_type' => $remotePerson->person_type,
            'nid' => $remotePerson->nid,
            'from_module' => $remotePerson->from_module,
            'updated_at' => $remotePerson->remote_updated_at,
            'total_fingerprints' => $remotePerson->total_fingerprints,
        ]);

        $this->applyRemotePerson($employee, $payload, false);
        $remotePerson->forceFill(['employee_id' => $employee->id, 'last_refreshed_at' => now()])->save();
    }

    /** Get Device List API and cache all documented device fields locally. */
    public function getDevices(?array $config = null): array
    {
        $config ??= $this->activeConfiguration();
        $response = $this->client($config)->get(
            $this->endpoint($config, self::PATH_DEVICES),
            $this->queryWithToken($config)
        );
        $response->throw();
        $json = $response->json();

        if (isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }

        return is_array($json) ? $json : [];
    }

    public function syncDevices(): array
    {
        $rows = $this->getDevices();
        $saved = 0;

        foreach ($rows as $row) {
            if (!is_array($row) || blank($row['identifier'] ?? null)) {
                continue;
            }

            TipsoiDevice::query()->updateOrCreate(
                ['identifier' => (string) $row['identifier']],
                [
                    'tipsoi_id' => $row['id'] ?? null,
                    'device_category_id' => $row['device_category_id'] ?? null,
                    'vendor_id' => $row['vendor_id'] ?? null,
                    'server_url' => $row['server_url'] ?? null,
                    'firmware_version' => $row['firmware_version'] ?? null,
                    'phone_number' => $row['phone_number'] ?? null,
                    'sim_id' => $row['sim_id'] ?? null,
                    'description' => $row['description'] ?? null,
                    'location' => $row['location'] ?? null,
                    'imei_number' => $row['imei_number'] ?? null,
                    'timezone_offset_minutes' => $row['timezone_offset_minutes'] ?? null,
                    'type' => $row['type'] ?? null,
                    'server_id' => $row['server_id'] ?? null,
                    'has_enrollment_feature' => (bool) ($row['has_enrollment_feature'] ?? false),
                    'is_mqtt_enabled' => (bool) ($row['is_mqtt_enabled'] ?? false),
                    'mqtt_allow_batch_rfid' => (bool) ($row['mqtt_allow_batch_rfid'] ?? false),
                    'connected' => (bool) ($row['connected'] ?? false),
                    'data_dump_requested' => (bool) ($row['data_dump_requested'] ?? false),
                    'last_communication_at' => $this->parseNullableDateTime($row['last_communication_at'] ?? null),
                    'device_type_id' => $row['device_type_id'] ?? null,
                    'total_allocated' => (int) ($row['total_allocated'] ?? 0),
                    'last_seen' => $row['last_seen'] ?? null,
                    'status' => $row['status'] ?? null,
                    'synced_at' => now(),
                    'raw_payload' => $row,
                ]
            );
            $saved++;
        }

        $message = "Tipsoi device refresh complete: {$saved} device(s) cached.";
        $this->rememberSyncResult('success', $message);

        return ['saved' => $saved, 'devices' => $rows, 'message' => $message];
    }

    public function startEnrollment(string $deviceIdentifier, string $personIdentifier, string $hand, string $finger): array
    {
        if (!in_array($hand, ['left', 'right'], true)) {
            throw new RuntimeException('Invalid hand. Accepted values: left, right.');
        }
        if (!in_array($finger, ['thumb', 'index', 'middle', 'ring', 'pinky'], true)) {
            throw new RuntimeException('Invalid finger. Accepted values: thumb, index, middle, ring, pinky.');
        }

        $config = $this->activeConfiguration();
        $response = $this->client($config)
            ->withQueryParameters(['api_token' => $config['api_key']])
            ->post(
                $this->endpoint($config, 'devices/' . rawurlencode($deviceIdentifier) . '/startEnrollment'),
                ['person_identifier' => $personIdentifier, 'hand' => $hand, 'finger' => $finger]
            );
        $response->throw();
        return $response->json() ?: [];
    }

    public function stopEnrollment(string $deviceIdentifier): array
    {
        $config = $this->activeConfiguration();
        $response = $this->client($config)
            ->withQueryParameters(['api_token' => $config['api_key']])
            ->post($this->endpoint($config, 'devices/' . rawurlencode($deviceIdentifier) . '/stopEnrollment'));
        $response->throw();
        return $response->json() ?: [];
    }

    public function enrollmentStatus(string|int $deviceId, string|int $personId): array
    {
        $config = $this->activeConfiguration();
        $response = $this->client($config)->get(
            $this->endpoint($config, 'devices/enrollment_status'),
            $this->queryWithToken($config, ['device_id' => $deviceId, 'person_id' => $personId])
        );
        $response->throw();
        return $response->json() ?: [];
    }

    public function allocatePerson(string $deviceIdentifier, string $personIdentifier, string $action): array
    {
        if (!in_array($action, ['allocate', 'revoke'], true)) {
            throw new RuntimeException('Invalid allocation action.');
        }

        $config = $this->activeConfiguration();
        $response = $this->client($config)
            ->withQueryParameters(['api_token' => $config['api_key']])
            ->post(
                $this->endpoint($config, 'devices/' . rawurlencode($deviceIdentifier) . '/allocations'),
                [[
                    'person_identifier' => $personIdentifier,
                    'action' => $action,
                ]]
            );
        $response->throw();
        return $response->json() ?: [];
    }

    public function batchAllocation(array $personIdentifiers, array $deviceIds, string $action): array
    {
        if (!in_array($action, ['allocate', 'revoke'], true)) {
            throw new RuntimeException('Invalid allocation action.');
        }

        $config = $this->activeConfiguration();
        $response = $this->client($config)
            ->withQueryParameters(['api_token' => $config['api_key']])
            ->post($this->endpoint($config, 'devices/batch-allocations'), [
                'action' => $action,
                'person_identifiers' => array_values($personIdentifiers),
                'device_ids' => array_values($deviceIds),
            ]);
        $response->throw();
        return $response->json() ?: [];
    }

    public function calculateAttendance(Employee $employee, string $date, ?string $checkIn, ?string $checkOut): array
    {
        $checkInAt = $checkIn ? Carbon::parse("{$date} {$checkIn}") : null;
        $checkOutAt = $checkOut ? Carbon::parse("{$date} {$checkOut}") : null;

        // Manual/time-only input may represent an overnight checkout. Full
        // TIPSOI datetimes use calculateAttendanceAt() directly and never need
        // this guess.
        if ($checkOutAt && $checkInAt && $checkOutAt->lessThanOrEqualTo($checkInAt)) {
            $checkOutAt->addDay();
        }

        return $this->calculateAttendanceAt($employee, $date, $checkInAt, $checkOutAt);
    }

    private function calculateAttendanceAt(Employee $employee, string $date, ?Carbon $checkInAt, ?Carbon $checkOutAt): array
    {
        $schedule = $this->resolveSchedule($employee, $date);
        $start = $schedule['start_time'];
        $end = $schedule['end_time'];
        $grace = $schedule['grace_minutes'];
        $shift = $schedule['shift'];
        $setting = $schedule['setting'];

        if (!$checkInAt && !$checkOutAt) {
            return [
                'shift_id' => $shift?->id,
                'check_in' => null,
                'check_out' => null,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_minutes' => 0,
                'worked_minutes' => 0,
                'status' => 'absent',
            ];
        }

        $scheduledStart = Carbon::parse("{$date} {$start}");
        $scheduledEnd = Carbon::parse("{$date} {$end}");
        if ($shift?->is_overnight || $scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
            $scheduledEnd->addDay();
        }

        // A single TIPSOI event is a valid attendance punch but not a checkout.
        // Never manufacture an end time or a 24-hour work duration.
        if ($checkInAt && $checkOutAt && $checkOutAt->lessThan($checkInAt)) {
            $checkOutAt = null;
        }

        $lateMinutes = 0;
        if ($checkInAt && $checkInAt->greaterThan($scheduledStart->copy()->addMinutes($grace))) {
            $lateMinutes = max(0, $scheduledStart->diffInMinutes($checkInAt));
        }

        $earlyLeaveMinutes = ($checkOutAt && $checkOutAt->lessThan($scheduledEnd))
            ? max(0, $checkOutAt->diffInMinutes($scheduledEnd))
            : 0;

        $workedMinutes = ($checkInAt && $checkOutAt)
            ? max(0, $checkInAt->diffInMinutes($checkOutAt))
            : 0;
        if ($workedMinutes > 0 && $shift) {
            $workedMinutes = max(0, $workedMinutes - (int) ($shift->break_minutes ?? 0));
        }

        $overtimeMinutes = 0;
        if ($checkOutAt && $checkOutAt->greaterThan($scheduledEnd) && ($setting->auto_calculate_overtime ?? true)) {
            $rawOvertime = max(0, $scheduledEnd->diffInMinutes($checkOutAt));
            $minimum = (int) ($setting->minimum_overtime_minutes ?? 0);
            $overtimeMinutes = $rawOvertime >= $minimum ? $rawOvertime : 0;
        }

        return [
            'shift_id' => $shift?->id,
            'check_in' => $checkInAt,
            'check_out' => $checkOutAt,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'worked_minutes' => $workedMinutes,
            'status' => $lateMinutes > 0 && ($setting->auto_calculate_late ?? true) ? 'late' : 'present',
        ];
    }

    public function resolveSchedule(Employee $employee, string $date): array
    {
        $setting = AttendanceSetting::first() ?? new AttendanceSetting([
            'grace_minutes' => 10,
            'global_start_time' => '09:00:00',
            'global_end_time' => '17:00:00',
        ]);

        $roster = ShiftRoster::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('roster_date', $date)
            ->first();
        $shift = $roster?->shift;

        if (!$shift && $employee->default_shift_id) {
            $shift = $employee->relationLoaded('defaultShift')
                ? $employee->defaultShift
                : Shift::find($employee->default_shift_id);
        }

        $start = $shift?->start_time ?: ($setting->global_start_time ?: '09:00:00');
        $end = $shift?->end_time ?: ($setting->global_end_time ?: '17:00:00');
        $grace = $shift && $shift->grace_minutes !== null
            ? (int) $shift->grace_minutes
            : (int) ($setting->grace_minutes ?? 0);

        return [
            'shift' => $shift,
            'setting' => $setting,
            'start_time' => substr((string) $start, 0, 8),
            'end_time' => substr((string) $end, 0, 8),
            'grace_minutes' => max(0, $grace),
            'uses_global_fallback' => !$shift,
        ];
    }

    private function getPeople(array $config): array
    {
        $response = $this->client($config)->get(
            $this->endpoint($config, self::PATH_PEOPLE),
            $this->queryWithToken($config)
        );
        $response->throw();
        $json = $response->json();

        if (isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }

        return is_array($json) ? $json : [];
    }

    private function findRemotePersonByIdentifier(string $identifier, array $config): ?array
    {
        foreach ($this->getPeople($config) as $person) {
            if (is_array($person) && trim((string) ($person['identifier'] ?? '')) === $identifier) {
                return $person;
            }
        }
        return null;
    }

    private function applyRemotePerson(Employee $employee, array $person, bool $updateName): void
    {
        $remotePersonId = $person['id'] ?? null;
        $remoteIdentifier = trim((string) ($person['identifier'] ?? ''));

        $attributes = [
            'tipsoi_person_id' => $remotePersonId ?: $employee->tipsoi_person_id,
            'tipsoi_id_in_device' => $person['id_in_device'] ?? $employee->tipsoi_id_in_device,
            'tipsoi_identifier' => $remoteIdentifier !== '' ? $remoteIdentifier : ($employee->tipsoi_identifier ?: $employee->employee_code),
            'tipsoi_old_identifier' => $person['old_identifier'] ?? $employee->tipsoi_old_identifier,
            'tipsoi_rfid' => array_key_exists('rfid', $person) ? ($person['rfid'] ?: null) : $employee->tipsoi_rfid,
            'tipsoi_primary_display_text' => $person['primary_display_text'] ?? $employee->tipsoi_primary_display_text,
            'tipsoi_secondary_display_text' => $person['secondary_display_text'] ?? $employee->tipsoi_secondary_display_text,
            'tipsoi_photo_url' => $person['photo_url'] ?? $employee->tipsoi_photo_url,
            'tipsoi_description' => $person['description'] ?? $employee->tipsoi_description,
            'tipsoi_person_type' => $person['person_type'] ?? $employee->tipsoi_person_type,
            'tipsoi_nid' => $person['nid'] ?? $employee->tipsoi_nid,
            'tipsoi_from_module' => $person['from_module'] ?? $employee->tipsoi_from_module,
            'tipsoi_total_fingerprints' => (int) ($person['total_fingerprints'] ?? $employee->tipsoi_total_fingerprints ?? 0),
            'tipsoi_remote_updated_at' => $this->parseNullableDateTime($person['updated_at'] ?? null) ?: $employee->tipsoi_remote_updated_at,
            'tipsoi_synced_at' => now(),
            'tipsoi_sync_status' => 'synced',
            'tipsoi_sync_error' => null,
        ];

        if ($updateName && filled($person['name'] ?? null)) {
            $attributes['name'] = trim((string) $person['name']);
        }

        $employee->forceFill($attributes)->save();
        $this->cacheRemotePerson($person, $employee);
    }

    private function cacheRemotePerson(array $person, ?Employee $employee): ?TipsoiRemotePerson
    {
        $personId = $person['id'] ?? null;
        $identifier = trim((string) ($person['identifier'] ?? ''));
        if (!$personId && $identifier === '') {
            return null;
        }

        $match = $personId
            ? ['tipsoi_person_id' => $personId]
            : ['identifier' => $identifier];

        return TipsoiRemotePerson::updateOrCreate($match, [
            'tipsoi_person_id' => $personId,
            'id_in_device' => $person['id_in_device'] ?? null,
            'identifier' => $identifier ?: null,
            'old_identifier' => $person['old_identifier'] ?? null,
            'name' => $person['name'] ?? null,
            'photo_url' => $person['photo_url'] ?? null,
            'rfid' => $person['rfid'] ?? null,
            'primary_display_text' => $person['primary_display_text'] ?? null,
            'secondary_display_text' => $person['secondary_display_text'] ?? null,
            'description' => $person['description'] ?? null,
            'person_type' => $person['person_type'] ?? null,
            'nid' => $person['nid'] ?? null,
            'from_module' => $person['from_module'] ?? null,
            'remote_updated_at' => $this->parseNullableDateTime($person['updated_at'] ?? null),
            'total_fingerprints' => (int) ($person['total_fingerprints'] ?? 0),
            'employee_id' => $employee?->id,
            'raw_payload' => $person,
            'last_refreshed_at' => now(),
        ]);
    }

    private function findEmployee(string $identifier, mixed $personId): ?Employee
    {
        if ($personId) {
            $employee = Employee::where('tipsoi_person_id', $personId)->first();
            if ($employee) {
                return $employee;
            }
        }

        if ($identifier !== '') {
            return Employee::query()
                ->where('tipsoi_identifier', $identifier)
                ->orWhere('employee_code', $identifier)
                ->first();
        }

        return null;
    }

    private function uniqueEmployeeCode(string $identifier, mixed $personId): string
    {
        $base = trim($identifier) ?: ('TIP-' . ($personId ?: uniqid()));
        $base = mb_substr(preg_replace('/[^A-Za-z0-9\-_]/', '-', $base) ?: 'TIP', 0, 45);
        $candidate = $base;
        $counter = 1;

        while (Employee::where('employee_code', $candidate)->exists()) {
            $candidate = mb_substr($base, 0, 40) . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function personPayload(Employee $employee): array
    {
        $identifier = trim((string) ($employee->tipsoi_identifier ?: $employee->employee_code));
        if ($identifier === '') {
            $identifier = 'EMP-' . $employee->id;
        }

        $name = trim((string) $employee->name) ?: ('Employee ' . $employee->id);
        $primary = mb_substr(trim((string) ($employee->tipsoi_primary_display_text ?: 'Welcome')), 0, 10);
        $secondary = mb_substr(trim((string) ($employee->tipsoi_secondary_display_text ?: $name)), 0, 10);

        return [
            'identifier' => $identifier,
            'name' => $name,
            'primary_display_text' => $primary ?: 'Welcome',
            'secondary_display_text' => $secondary ?: 'Employee',
            'rfid' => (string) ($employee->tipsoi_rfid ?? ''),
        ];
    }

    private function syncEmployeePhotoBestEffort(Employee $employee, array $config, array $basePayload): void
    {
        $relativePath = trim((string) ($employee->image ?? ''));
        if ($relativePath === '') {
            return;
        }

        $photoPath = public_path(ltrim($relativePath, '/'));
        if (!is_file($photoPath)) {
            return;
        }

        $size = @filesize($photoPath);
        // V2.4 documentation allows up to 3 MB for Person image uploads.
        if ($size !== false && $size > 3 * 1024 * 1024) {
            Log::warning('[TIPSOI][KARACHI] Employee photo skipped because it exceeds 3 MB', [
                'employee_id' => $employee->id,
                'size_bytes' => $size,
            ]);
            return;
        }

        $bytes = @file_get_contents($photoPath);
        if ($bytes === false || $bytes === '') {
            return;
        }

        try {
            $payload = $basePayload;
            $payload['image_base64'] = base64_encode($bytes);
            $response = $this->postMultipart($config, self::PATH_PEOPLE, $payload, false);
            $response->throw();
        } catch (Throwable $e) {
            Log::warning('[TIPSOI][KARACHI] Base person synced but optional employee photo update failed', [
                'employee_id' => $employee->id,
                'identifier' => $basePayload['identifier'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeRange(string $from, string $to): array
    {
        $fromDate = Carbon::parse($from)->toDateString();
        $toDate = Carbon::parse($to)->toDateString();
        if (Carbon::parse($fromDate)->gt(Carbon::parse($toDate))) {
            throw new RuntimeException('From date cannot be after To date.');
        }
        return [$fromDate, $toDate];
    }

    private function calculateTipsoiSummaryAttendance(Employee $employee, string $date, array $log): array
    {
        $checkInAt = $this->parseTipsoiLocalDateTime($log['start'] ?? null);
        $checkOutAt = $this->parseTipsoiLocalDateTime($log['end'] ?? null);
        $apiMinutes = $this->parseTipsoiHoursToMinutes($log['hours'] ?? null);

        if ($checkInAt && $checkOutAt) {
            $rawMinutes = $checkInAt->diffInMinutes($checkOutAt, false);
            if ($rawMinutes < 0) {
                $checkOutAt = null;
            } elseif ($apiMinutes === 0 && $rawMinutes <= 5) {
                // TIPSOI commonly returns start=end (or repeated face scans only)
                // for a one-punch day. This is not a 24-hour attendance.
                $checkOutAt = null;
            }
        }

        $resolved = $this->calculateAttendanceAt($employee, $date, $checkInAt, $checkOutAt);
        if ($apiMinutes !== null) {
            // attendance_logs hours is authoritative for its calendar-day
            // fallback. Raw shift-window normalization can replace it later.
            $resolved['worked_minutes'] = $apiMinutes;
        }

        return $resolved;
    }

    private function rebuildAttendanceFromRawPunches(int $employeeId, string $businessDate): bool
    {
        $employee = Employee::query()->find($employeeId);
        if (!$employee) {
            return false;
        }

        [$boundaryStart, $boundaryEnd] = $this->businessDateBoundaries($employee, $businessDate);

        // TIMESTAMP columns may be shifted by DB timezone. Query with a generous
        // buffer, then filter by raw_payload.logged_time (the vendor wall clock).
        $logs = TipsoiAttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('logged_time', [
                $boundaryStart->copy()->subDay(),
                $boundaryEnd->copy()->addDay(),
            ])
            ->orderBy('logged_time')
            ->get();

        $events = $logs->map(function (TipsoiAttendanceLog $log) {
            $at = $this->rawLocalLoggedAt($log);
            return $at ? ['at' => $at, 'log' => $log] : null;
        })->filter()->filter(function (array $event) use ($employee, $businessDate, $boundaryStart, $boundaryEnd) {
            /** @var Carbon $at */
            $at = $event['at'];
            return $at->gte($boundaryStart)
                && $at->lt($boundaryEnd)
                && $this->businessDateForPunch($employee, $at) === $businessDate;
        })->sortBy(fn (array $event) => $event['at']->timestamp)->values();

        if ($events->isEmpty()) {
            return false;
        }

        // A face terminal often emits many scans seconds apart. Treat scans
        // within 5 minutes as one punch event so 17:41:25 + 17:41:27 does not
        // become a fake 2-second shift.
        $clusters = [];
        foreach ($events as $event) {
            if ($clusters === []) {
                $clusters[] = [$event];
                continue;
            }

            $lastClusterIndex = count($clusters) - 1;
            $lastEvent = $clusters[$lastClusterIndex][count($clusters[$lastClusterIndex]) - 1];
            if ($lastEvent['at']->diffInSeconds($event['at']) <= 300) {
                $clusters[$lastClusterIndex][] = $event;
            } else {
                $clusters[] = [$event];
            }
        }

        $firstEvent = $clusters[0][0];
        $lastCluster = $clusters[count($clusters) - 1];
        $lastEvent = $lastCluster[count($lastCluster) - 1];
        $checkInAt = $firstEvent['at']->copy();
        $checkOutAt = count($clusters) > 1 ? $lastEvent['at']->copy() : null;

        $resolved = $this->calculateAttendanceAt($employee, $businessDate, $checkInAt, $checkOutAt);
        $existing = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $businessDate)
            ->first();

        if ($existing?->source === 'manual') {
            return true;
        }

        /** @var TipsoiAttendanceLog $metadataLog */
        $metadataLog = $lastEvent['log'];
        $this->correctJoinDateFromAttendance($employee, $businessDate);

        Attendance::updateOrCreate(
            ['employee_id' => $employeeId, 'attendance_date' => $businessDate],
            array_merge($resolved, [
                'source' => 'system',
                'notes' => null,
                'marked_by' => null,
                'tipsoi_project_id' => $existing?->tipsoi_project_id,
                'tipsoi_person_id' => $metadataLog->person_id ?: $employee->tipsoi_person_id,
                'tipsoi_person_identifier' => $metadataLog->person_identifier ?: ($employee->tipsoi_identifier ?: $employee->employee_code),
                'tipsoi_person_name' => $existing?->tipsoi_person_name ?: $employee->name,
                'tipsoi_rfid' => $metadataLog->rfid ?: $employee->tipsoi_rfid,
                'tipsoi_primary_display_text' => $metadataLog->primary_display_text ?: $employee->tipsoi_primary_display_text,
                'tipsoi_secondary_display_text' => $metadataLog->secondary_display_text ?: $employee->tipsoi_secondary_display_text,
                'tipsoi_hours' => $this->formatMinutesAsTipsoiHours((int) $resolved['worked_minutes']),
                'tipsoi_external_id' => 'tipsoi-raw-' . $employeeId . '-' . $businessDate,
                'tipsoi_synced_at' => now(),
                'tipsoi_push_status' => 'remote',
                'tipsoi_push_error' => null,
            ])
        );

        return true;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function businessDateBoundaries(Employee $employee, string $businessDate): array
    {
        $date = Carbon::parse($businessDate)->startOfDay();
        $previousDate = $date->copy()->subDay()->toDateString();
        $nextDate = $date->copy()->addDay()->toDateString();

        $previous = $this->scheduleWindow($employee, $previousDate);
        $current = $this->scheduleWindow($employee, $businessDate);
        $next = $this->scheduleWindow($employee, $nextDate);

        $boundaryStart = $this->midpointBetween($previous['end'], $current['start']) ?: $current['start']->copy()->subHours(8);
        $boundaryEnd = $this->midpointBetween($current['end'], $next['start']) ?: $current['end']->copy()->addHours(8);

        return [$boundaryStart, $boundaryEnd];
    }

    private function businessDateForPunch(Employee $employee, Carbon $punch): string
    {
        $calendarDate = $punch->toDateString();
        $previousDate = $punch->copy()->subDay()->toDateString();
        $previous = $this->scheduleWindow($employee, $previousDate);
        $current = $this->scheduleWindow($employee, $calendarDate);
        $boundary = $this->midpointBetween($previous['end'], $current['start']);

        if (!$boundary) {
            return $calendarDate;
        }

        return $punch->lt($boundary) ? $previousDate : $calendarDate;
    }

    /** @return array{start: Carbon, end: Carbon} */
    private function scheduleWindow(Employee $employee, string $date): array
    {
        $schedule = $this->resolveSchedule($employee, $date);
        $start = Carbon::parse($date . ' ' . $schedule['start_time']);
        $end = Carbon::parse($date . ' ' . $schedule['end_time']);
        if ($schedule['shift']?->is_overnight || $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }

    private function midpointBetween(Carbon $left, Carbon $right): ?Carbon
    {
        $seconds = $left->diffInSeconds($right, false);
        if ($seconds <= 0) {
            return null;
        }

        return $left->copy()->addSeconds((int) floor($seconds / 2));
    }

    private function rawLocalLoggedAt(TipsoiAttendanceLog $log): ?Carbon
    {
        $payload = is_array($log->raw_payload) ? $log->raw_payload : [];
        return $this->parseTipsoiLocalDateTime($payload['logged_time'] ?? null)
            ?: ($log->logged_time ? Carbon::parse($log->logged_time->format('Y-m-d H:i:s')) : null);
    }

    private function parseTipsoiLocalDateTime(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        $value = trim((string) $value);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
                // Try the next vendor format.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseTipsoiHoursToMinutes(mixed $hours): ?int
    {
        if ($hours === null || trim((string) $hours) === '') {
            return null;
        }

        $parts = explode(':', trim((string) $hours), 2);
        if (!is_numeric($parts[0] ?? null)) {
            return null;
        }

        return max(0, ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0));
    }

    private function formatMinutesAsTipsoiHours(int $minutes): string
    {
        $minutes = max(0, $minutes);
        return intdiv($minutes, 60) . ':' . ($minutes % 60);
    }

    private function correctJoinDateFromAttendance(Employee $employee, string $attendanceDate): void
    {
        if (!$employee->join_date) {
            return;
        }

        $joinDate = Carbon::parse($employee->join_date)->toDateString();
        if ($joinDate > $attendanceDate) {
            // The remote People API has no joining-date field. Auto-created
            // employees initially use today, but an older TIPSOI punch proves
            // the person was already employed. Move only backward, never forward.
            $employee->forceFill(['join_date' => $attendanceDate])->save();
        }
    }

    private function timeOnly(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseNullableDateTime(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function rememberSyncResult(string $status, string $message): void
    {
        $setting = AttendanceSetting::first();
        if (!$setting) {
            return;
        }

        $setting->forceFill([
            'tipsoi_last_sync_at' => now(),
            'tipsoi_last_sync_status' => $status,
            'tipsoi_last_sync_message' => mb_substr($message, 0, 5000),
        ])->save();
    }

    private function client(array $config, bool $retry = true): PendingRequest
    {
        $client = Http::acceptJson()
            ->timeout(45)
            ->connectTimeout(15);

        if ($retry) {
            $client = $client->retry(2, 500);
        }

        if (!($config['verify_ssl'] ?? true)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    private function shouldVerifySsl(string $sslMode): bool
    {
        if ($sslMode === 'verify') {
            return true;
        }
        if ($sslMode === 'disable') {
            return false;
        }

        if (app()->environment(['local', 'testing'])) {
            return false;
        }
        if (PHP_SAPI === 'cli-server') {
            return false;
        }

        try {
            if (!app()->runningInConsole()) {
                $host = strtolower((string) request()->getHost());
                if (in_array($host, ['', 'localhost', '127.0.0.1', '::1'], true)) {
                    return false;
                }
                if (str_ends_with($host, '.test') || str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
                    return false;
                }
            }
        } catch (Throwable) {
        }

        return true;
    }

    private function endpoint(array $config, string $path): string
    {
        return $config['base_url'] . '/' . ltrim($path, '/');
    }

    private function queryWithToken(array $config, array $query = []): array
    {
        return array_merge(['api_token' => $config['api_key']], $query);
    }

    private function postMultipart(array $config, string $path, array $fields, bool $retry = true)
    {
        $parts = [];
        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = [
                'name' => (string) $name,
                'contents' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            ];
        }

        $url = $this->endpoint($config, $path);
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . http_build_query(['api_token' => $config['api_key']]);

        return $this->client($config, $retry)->send('POST', $url, ['multipart' => $parts]);
    }
}
