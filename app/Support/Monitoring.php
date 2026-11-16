<?php

namespace App\Support;

use App\Models\OperationalEvent;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Monitoring
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_KEY_PARTS = [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey', 'signature',
        'authorization', 'cookie', 'csrf', 'session', 'card', 'cvv', 'pin',
        'email', 'phone', 'msisdn', 'account', 'payload', 'body', 'proof',
    ];

    /**
     * Persist an operational event and raise an alert for warnings/criticals.
     * Context must contain identifiers and counters only, never customer PII,
     * request bodies or credentials.
     */
    public function record(string $type, string $severity, string $message, array $context = [], ?string $dedupeKey = null): void
    {
        if (! config('monitoring.enabled')) {
            return;
        }

        $severity = in_array($severity, OperationalEvent::SEVERITIES, true) ? $severity : 'warning';
        $message = $this->truncate($this->scrubText($message), 250);
        $context = $this->sanitizeContext($context);

        try {
            OperationalEvent::create([
                'type' => $this->truncate($type, 64),
                'severity' => $severity,
                'message' => $message,
                'context' => $context,
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            // Monitoring must never take the application down; the log still records it.
        }

        $level = match ($severity) {
            'critical' => 'critical',
            'info' => 'info',
            default => 'warning',
        };
        Log::log($level, '[ops] '.$message, ['type' => $type] + $context);

        if ($severity !== 'info') {
            $this->alert($severity, $message, $context, $dedupeKey);
        }
    }

    /**
     * Send a deduplicated operator alert to the configured webhook.
     */
    public function alert(string $severity, string $message, array $context = [], ?string $dedupeKey = null): void
    {
        $url = (string) config('monitoring.alert_webhook');

        if (! config('monitoring.enabled') || $url === '') {
            return;
        }

        $dedupeKey = $dedupeKey ?? $severity.'|'.$message;
        $ttl = max(60, (int) config('monitoring.alert_min_seconds', 300));

        if (! Cache::add('ops:alert:'.sha1($dedupeKey), 1, $ttl)) {
            return;
        }

        $payload = [
            'text' => '['.strtoupper($severity).'] '.config('app.name', 'Store').': '.$message,
            'severity' => $severity,
            'environment' => app()->environment(),
            'context' => $this->sanitizeContext($context),
            'occurred_at' => now()->toIso8601String(),
        ];

        try {
            Http::timeout(5)->acceptJson()->post($url, $payload);
        } catch (Throwable $exception) {
            Log::error('Monitoring alert webhook delivery failed.', [
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * Record 5xx responses (and uncaught console failures) without capturing
     * request bodies, credentials or the raw exception message.
     */
    public function reportException(Throwable $exception): void
    {
        if ($this->shouldIgnore($exception)) {
            return;
        }

        $request = app()->runningInConsole() ? null : request();
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        $this->record(
            'server_error',
            'critical',
            'Unhandled '.$status.' error: '.class_basename($exception),
            array_filter([
                'exception' => $exception::class,
                'status' => $status,
                'route' => $request instanceof Request
                    ? ($request->route()?->uri() ?? $request->path())
                    : null,
                'method' => $request?->method(),
                'user_id' => $request?->user()?->id,
                'fingerprint' => substr(sha1($exception::class.'|'.$exception->getFile().'|'.$exception->getLine()), 0, 16),
                'mode' => $request ? 'http' : 'console',
            ], fn ($value) => $value !== null),
            'server_error:'.sha1($exception::class.'|'.$exception->getFile().'|'.$exception->getLine()),
        );
    }

    /**
     * Remove customer contact details and credential-shaped values from free
     * text before it is stored or forwarded to an operator.
     */
    public function scrubText(string $value): string
    {
        $value = preg_replace('/(bearer\s+)[a-z0-9._\-]{8,}/i', '$1[redacted]', $value) ?? $value;
        $value = preg_replace('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', '[redacted-email]', $value) ?? $value;
        $value = preg_replace('/(?:\+250|0)\d{8,9}\b/', '[redacted-phone]', $value) ?? $value;
        $value = preg_replace('/\+\d{10,15}\b/', '[redacted-phone]', $value) ?? $value;

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;
    }

    private function sanitizeContext(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $key = (string) $key;

            if ($this->isSensitiveKey($key)) {
                $clean[$key] = self::REDACTED;

                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $clean[$key] = $value;
            } elseif (is_string($value)) {
                $clean[$key] = $this->truncate($value, 500);
            } elseif ($value === null) {
                $clean[$key] = null;
            } else {
                $clean[$key] = '[complex]';
            }
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    private function truncate(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }

    private function shouldIgnore(Throwable $exception): bool
    {
        if ($exception instanceof ValidationException
            || $exception instanceof AuthenticationException
            || $exception instanceof HttpResponseException) {
            return true;
        }

        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500;
    }
}
