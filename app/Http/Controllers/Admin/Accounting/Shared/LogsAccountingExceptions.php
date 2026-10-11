<?php

namespace App\Http\Controllers\Admin\Accounting\Shared;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Uniform Accounting controller error logging.
 * The caller always rethrows errors so Laravel can preserve 403/422 responses,
 * DB::transaction rollbacks, and the existing user-facing validation messages.
 */
trait LogsAccountingExceptions
{
    protected function logAccountingException(\Throwable $exception, string $action): void
    {
        // Validation, permission and HTTP errors are expected client responses, not server faults.
        if ($exception instanceof ValidationException
            || $exception instanceof AuthorizationException
            || $exception instanceof AuthenticationException
            || $exception instanceof HttpExceptionInterface) {
            return;
        }

        try {
            Log::error('Accounting controller operation failed', [
                'action' => $action,
                'path' => request()->path(), // Excludes query strings and form request payloads.
                'user_id' => auth()->id(),
                'exception_type' => get_class($exception),
                'exception' => $exception, // Laravel's exception formatter retains the error trace.
            ]);
        } catch (\Throwable $loggingFailure) {
            // A logging outage must not mask or replace the original accounting exception.
        }
    }
}
