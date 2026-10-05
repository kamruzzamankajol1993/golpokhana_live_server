<?php

namespace App\Http\Middleware;

use App\Models\OfflinePosDevice;
use App\Models\OfflinePosSyncLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyOfflinePosKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $givenKey = trim((string) $request->header('X-OFFLINE-POS-KEY'));
        $givenUuid = trim((string) $request->header('X-OFFLINE-DEVICE-UUID'));

        $device = OfflinePosDevice::query()
            ->where('device_key', $givenKey)
            ->where('status', 1)
            ->first();

        if (!$device) {
            Log::warning('OFFLINE POS AUTH FAILED', [
                'path'=>$request->path(),
                'device_uuid'=>$givenUuid ?: null,
                'key_present'=>$givenKey !== '',
                'ip'=>$request->ip(),
            ]);
            $this->storeSyncLog(null, $request, null, 'failed', 'Unauthorized offline POS request. IP: ' . ($request->ip() ?: 'unknown'));

            return response()->json([
                'status' => false,
                'error_code' => 'invalid_device_key',
                'message' => 'Unauthorized offline POS request.',
            ], 401);
        }

        // New offline clients send their stored UUID on every sync request.
        // UUID remains optional for backward compatibility with already deployed clients.
        $storedUuid = trim((string) $device->device_uuid);
        if ($givenUuid !== '' && $storedUuid !== '' && !hash_equals($storedUuid, $givenUuid)) {
            Log::warning('OFFLINE POS DEVICE UUID MISMATCH', [
                'path'=>$request->path(),
                'device_id'=>$device->id,
                'device_name'=>$device->device_name,
                'given_uuid'=>$givenUuid,
                'ip'=>$request->ip(),
            ]);
            $this->storeSyncLog($device, $request, null, 'failed', 'Device UUID mismatch. Given UUID: ' . $givenUuid);

            return response()->json([
                'status' => false,
                'error_code' => 'device_uuid_mismatch',
                'message' => 'Offline POS device UUID mismatch.',
            ], 401);
        }

        $request->attributes->set('offline_pos_device', $device);

        try {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        } catch (Throwable $e) {
            Log::warning('OFFLINE POS LAST SEEN UPDATE FAILED', ['device_id' => $device->id, 'message' => $e->getMessage()]);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->storeSyncLog($device, $request, null, 'failed', $this->requestSummary($request) . ' | Exception: ' . $e->getMessage());
            throw $e;
        }

        [$status, $responseSummary] = $this->responseStatus($response);
        $details = trim($this->requestSummary($request) . ($responseSummary !== '' ? ' | ' . $responseSummary : ''));
        $this->storeSyncLog($device, $request, $response, $status, $details);

        return $response;
    }

    private function storeSyncLog(?OfflinePosDevice $device, Request $request, ?Response $response, string $status, string $message): void
    {
        try {
            if (!Schema::hasTable('offline_pos_sync_logs')) {
                return;
            }

            $type = $this->syncType($request);
            $message = mb_substr($message, 0, 65000);

            // Auto sync can hit the same successful endpoint every 3/5 minutes.
            // Keep one copy of an equivalent success, while every error/partial
            // result is always stored for troubleshooting.
            if (strtolower($status) === 'success' && $this->hasEquivalentSuccess($device?->id, $type, $message)) {
                return;
            }

            OfflinePosSyncLog::query()->create([
                'device_id' => $device?->id,
                'type' => $type,
                'status' => $status,
                'message' => $message,
            ]);
        } catch (Throwable $e) {
            // Logging must never break a real sync request.
            Log::warning('OFFLINE POS DB SYNC LOG WRITE FAILED', [
                'device_id' => $device?->id,
                'path' => $request->path(),
                'http_status' => $response?->getStatusCode(),
                'message' => $e->getMessage(),
            ]);
        }
    }


    private function hasEquivalentSuccess(?int $deviceId, string $type, string $message): bool
    {
        $signature = $this->successSignature($message);

        return OfflinePosSyncLog::query()
            ->where('device_id', $deviceId)
            ->where('type', $type)
            ->where('status', 'success')
            ->latest('id')
            ->limit(100)
            ->pluck('message')
            ->contains(fn ($saved) => $this->successSignature((string) $saved) === $signature);
    }

    private function successSignature(string $message): string
    {
        // last_synced_at and IP change between otherwise identical heartbeat/pull
        // requests, so remove only those volatile values before comparing.
        $message = preg_replace('/last_synced_at=[^|]+/i', 'last_synced_at=*', $message) ?? $message;
        $message = preg_replace('/ip=[^|]+/i', 'ip=*', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? trim($message);

        return mb_strtolower($message);
    }

    private function syncType(Request $request): string
    {
        $path = preg_replace('#^api/offline-pos/v1/?#', '', trim($request->path(), '/')) ?: 'root';
        $direction = $request->isMethod('get') ? 'PULL' : 'PUSH';

        if (str_contains($path, 'auth/') || str_contains($path, 'verify') || str_contains($path, 'password')) {
            $direction = 'AUTH';
        } elseif (str_contains($path, 'kitchen/')) {
            $direction = $request->isMethod('get') ? 'PULL' : 'ACTION';
        } elseif (str_contains($path, 'pos-sessions/') && !$request->isMethod('get')) {
            $direction = 'ACTION';
        }

        return mb_substr($direction . ' ' . strtoupper($request->method()) . ' ' . $path, 0, 255);
    }

    private function requestSummary(Request $request): string
    {
        $parts = ['HTTP ' . strtoupper($request->method()), '/' . trim($request->path(), '/')];

        $datasets = ['orders', 'customers', 'table_bookings', 'pos_sessions'];
        foreach ($datasets as $dataset) {
            $rows = $request->input($dataset);
            if (is_array($rows)) {
                $parts[] = $dataset . '=' . count($rows);
            }
        }

        $orders = $request->input('orders');
        if (is_array($orders) && $orders !== []) {
            $references = [];
            foreach ($orders as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $ref = trim((string) ($row['order_number'] ?? $row['invoice_no'] ?? $row['local_uuid'] ?? $row['offline_uuid'] ?? $row['server_id'] ?? ''));
                if ($ref !== '') {
                    $references[] = $ref;
                }
                if (count($references) >= 6) {
                    break;
                }
            }
            if ($references !== []) {
                $parts[] = 'orders[' . implode(', ', $references) . ']';
            }
        }

        $lastSyncedAt = trim((string) $request->query('last_synced_at', ''));
        if ($lastSyncedAt !== '') {
            $parts[] = 'last_synced_at=' . $lastSyncedAt;
        }

        if ($request->ip()) {
            $parts[] = 'ip=' . $request->ip();
        }

        return implode(' | ', $parts);
    }

    private function responseStatus(Response $response): array
    {
        $httpStatus = $response->getStatusCode();
        $content = (string) $response->getContent();
        $decoded = json_decode($content, true);

        $failed = 0;
        $synced = 0;
        $messages = [];

        if (is_array($decoded)) {
            if (isset($decoded['summary']) && is_array($decoded['summary'])) {
                foreach ($decoded['summary'] as $key => $value) {
                    if (is_string($key) && str_ends_with($key, '_failed') && is_numeric($value)) {
                        $failed += (int) $value;
                    }
                    if (is_string($key) && str_ends_with($key, '_synced') && is_numeric($value)) {
                        $synced += (int) $value;
                    }
                }

                // Read messages from result rows without counting statuses a second time.
                $this->collectMessages($decoded, $messages);
            } else {
                $this->scanResult($decoded, $failed, $synced, $messages);
            }
        }

        if ($httpStatus >= 400) {
            $status = 'failed';
        } elseif ($failed > 0 && $synced > 0) {
            $status = 'partial';
        } elseif ($failed > 0) {
            $status = 'failed';
        } else {
            $status = 'success';
        }

        $summary = 'HTTP ' . $httpStatus;
        if ($synced > 0 || $failed > 0) {
            $summary .= ' | synced=' . $synced . ', failed=' . $failed;
        }
        if ($messages !== []) {
            $summary .= ' | ' . implode(' ; ', array_slice(array_values(array_unique($messages)), 0, 5));
        } elseif (is_array($decoded) && isset($decoded['message']) && is_scalar($decoded['message'])) {
            $summary .= ' | ' . trim((string) $decoded['message']);
        }

        return [$status, $summary];
    }

    private function scanResult(array $data, int &$failed, int &$synced, array &$messages): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'status') {
                if (is_string($value)) {
                    $normalized = strtolower($value);
                    if ($normalized === 'failed' || $normalized === 'error') {
                        $failed++;
                    } elseif (in_array($normalized, ['synced', 'success', 'ok'], true)) {
                        $synced++;
                    }
                } elseif ($value === false) {
                    $failed++;
                }
            }
            if ($key === 'message' && is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    $messages[] = mb_substr($text, 0, 500);
                }
            }

            if (is_array($value)) {
                $this->scanResult($value, $failed, $synced, $messages);
            }
        }
    }
    private function collectMessages(array $data, array &$messages): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'message' && is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    $messages[] = mb_substr($text, 0, 500);
                }
            }
            if (is_array($value)) {
                $this->collectMessages($value, $messages);
            }
        }
    }

}
