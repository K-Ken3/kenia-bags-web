<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Validates the configuration a production deployment depends on. Output never
 * includes key material, API keys or webhook URLs.
 */
class PreflightProduction extends Command
{
    protected $signature = 'ops:preflight {--strict : Treat warnings as failures}';

    protected $description = 'Check production configuration and security preconditions without exposing secrets';

    private const PASS = 'PASS';

    private const WARN = 'WARN';

    private const FAIL = 'FAIL';

    private const SKIP = 'SKIP';

    public function handle(): int
    {
        $production = config('app.env') === 'production';
        $rows = [];

        $rows[] = $production
            ? [self::PASS, 'APP_ENV', 'production']
            : [self::SKIP, 'APP_ENV', 'not production; production-only checks are skipped'];

        $rows[] = $this->keyCheck();
        $rows[] = $this->check(
            ! config('app.debug'),
            'APP_DEBUG',
            'disabled',
            'must be false in production; stack traces leak source and environment data',
            $production,
        );
        $rows[] = $this->urlCheck($production);
        $rows[] = $this->check(
            in_array(config('session.driver'), ['database', 'redis', 'memcached', 'file'], true),
            'SESSION_DRIVER',
            (string) config('session.driver'),
            'use database or redis so sessions survive restarts and can be revoked',
            $production,
        );
        $rows[] = $this->check(
            (bool) config('session.encrypt'),
            'SESSION_ENCRYPT',
            config('session.encrypt') ? 'enabled' : 'disabled',
            'session payloads must be encrypted at rest',
            $production,
        );
        $rows[] = $this->check(
            (bool) config('session.secure'),
            'SESSION_SECURE_COOKIE',
            config('session.secure') ? 'enabled' : 'disabled',
            'cookies must only be sent over HTTPS',
            $production,
        );
        $rows[] = $this->check(
            (bool) config('session.http_only'),
            'SESSION_HTTP_ONLY',
            config('session.http_only') ? 'enabled' : 'disabled',
            'session cookies must not be readable from JavaScript',
            $production,
        );
        $rows[] = $this->check(
            in_array(config('session.same_site'), ['lax', 'strict'], true),
            'SESSION_SAME_SITE',
            (string) (config('session.same_site') ?? 'null'),
            'use lax or strict to limit cross-site requests',
            $production,
        );
        $rows[] = $this->hostCheck($production);
        $rows[] = $this->proxyCheck();
        $rows[] = $this->check(
            ! in_array(config('cache.default'), ['array', 'null'], true),
            'CACHE_STORE',
            (string) config('cache.default'),
            'array cache loses alerts, locks and rate-limit state between requests',
            $production,
        );
        $rows[] = $this->check(
            config('queue.default') !== 'sync',
            'QUEUE_CONNECTION',
            (string) config('queue.default'),
            'sync runs queued work inside the request; run a worker instead',
            $production,
        );
        $rows[] = $this->failedJobsCheck();
        $rows[] = $this->check(
            ! (bool) config('payments.sandbox_enabled'),
            'PAYMENT_SANDBOX_ENABLED',
            config('payments.sandbox_enabled') ? 'true' : 'false',
            'sandbox payment simulation must never be enabled in production',
            $production,
        );
        $rows[] = $this->webhookCheck();
        $rows[] = $this->xentriPayCheck($production);
        $rows[] = $this->monitoringCheck($production);
        $rows[] = $this->logCheck($production);
        $rows[] = $this->writableCheck('storage/app', storage_path('app'));
        $rows[] = $this->writableCheck('storage/framework', storage_path('framework'));
        $rows[] = $this->writableCheck('bootstrap/cache', base_path('bootstrap/cache'));
        $rows[] = $this->databaseCheck();
        $rows[] = $this->adminMfaCheck($production);

        $this->newLine();
        $this->table(['Check', 'Status', 'Detail'], array_map(
            fn (array $row) => [$row[1], $this->paint($row[0]), $row[2]],
            $rows,
        ));

        if (! $production) {
            $this->warn('APP_ENV is not "production"; production-only checks were skipped.');
        }

        $failures = count(array_filter($rows, fn (array $row) => $row[0] === self::FAIL));
        $warnings = count(array_filter($rows, fn (array $row) => $row[0] === self::WARN));

        $this->newLine();
        $this->line(sprintf('%d failed, %d warnings.', $failures, $warnings));

        if ($failures > 0 || ($this->option('strict') && $warnings > 0)) {
            $this->error('Preflight failed. Resolve the items above before deploying.');

            return self::FAILURE;
        }

        $this->info('Preflight passed.');

        return self::SUCCESS;
    }

    private function check(bool $ok, string $name, string $detail, string $remedy, bool $production, bool $warnOnly = false): array
    {
        if ($ok) {
            return [self::PASS, $name, $detail];
        }

        if (! $production) {
            return [self::SKIP, $name, 'not evaluated outside production'];
        }

        return $warnOnly ? [self::WARN, $name, $remedy] : [self::FAIL, $name, $remedy];
    }

    private function keyCheck(): array
    {
        $key = (string) config('app.key');
        $valid = $key !== '';

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $valid = is_string($decoded) && strlen($decoded) >= 32;
        } elseif ($valid) {
            $valid = strlen($key) >= 32;
        }

        return $valid
            ? [self::PASS, 'APP_KEY', 'set ('.strlen($key).' characters, value withheld)']
            : [self::FAIL, 'APP_KEY', 'missing or shorter than 32 bytes; run php artisan key:generate'];
    }

    private function urlCheck(bool $production): array
    {
        $url = (string) config('app.url');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = (string) parse_url($url, PHP_URL_HOST);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        return $this->check(
            $scheme === 'https' && $host !== '' && ! $local,
            'APP_URL',
            $scheme.'://'.$host,
            'APP_URL must be the public https hostname; http or localhost breaks secure cookies and absolute URLs',
            $production,
        );
    }

    private function hostCheck(bool $production): array
    {
        $hosts = (array) config('app.trusted_hosts', []);
        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $ok = $hosts !== [] && in_array(strtolower($appHost), array_map('strtolower', $hosts), true);

        return $this->check(
            $ok,
            'TRUSTED_HOSTS',
            $hosts === [] ? 'empty' : implode(', ', $hosts),
            'set TRUSTED_HOSTS to the public hostname (defaults to APP_URL host) to reject Host header injection',
            $production,
        );
    }

    private function proxyCheck(): array
    {
        $proxies = (array) config('app.trusted_proxies', []);
        $trustHeaders = (bool) config('app.trust_proxy_headers');

        if ($proxies !== []) {
            return [self::PASS, 'TRUSTED_PROXIES', count($proxies).' explicit entr'.(count($proxies) === 1 ? 'y' : 'ies')];
        }

        if ($trustHeaders) {
            return [self::WARN, 'TRUSTED_PROXIES', 'empty; rightmost X-Forwarded-For is trusted because direct container access must be blocked by the platform'];
        }

        return [self::WARN, 'TRUSTED_PROXIES', 'empty and TRUST_PROXY_HEADERS=false; client IPs and scheme come from the proxy hop'];
    }

    private function failedJobsCheck(): array
    {
        try {
            $exists = Schema::hasTable('failed_jobs');
        } catch (Throwable) {
            $exists = false;
        }

        return $exists
            ? [self::PASS, 'failed_jobs table', 'present']
            : [self::WARN, 'failed_jobs table', 'missing; failed queue jobs cannot be recorded until migrations run'];
    }

    private function webhookCheck(): array
    {
        $enabled = (bool) config('payments.webhook_enabled');
        $secret = (string) config('payments.webhook_secret');

        if ($enabled && strlen($secret) < 16) {
            return [self::FAIL, 'PAYMENT_WEBHOOK_SECRET', 'webhook enabled but the secret is missing or shorter than 16 characters'];
        }

        if ($enabled) {
            return [self::PASS, 'PAYMENT_WEBHOOK_ENABLED', 'enabled with secret (value withheld)'];
        }

        return [self::WARN, 'PAYMENT_WEBHOOK_ENABLED', 'disabled; /webhooks/payment returns 404 until explicitly enabled'];
    }

    private function xentriPayCheck(bool $production): array
    {
        if (! config('payments.xentripay.enabled')) {
            return [self::WARN, 'XentriPay', 'disabled; online mobile money and card checkout is unavailable'];
        }

        $baseUrl = (string) config('payments.xentripay.base_url');
        $key = (string) config('payments.xentripay.api_key');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $testHost = str_contains((string) parse_url($baseUrl, PHP_URL_HOST), 'test.');

        if ($scheme !== 'https') {
            return [self::FAIL, 'XENTRIPAY_BASE_URL', 'must be https; the API key is sent as a header'];
        }

        if ($key === '') {
            return [self::FAIL, 'XENTRIPAY_API_KEY', 'missing; XentriPay is enabled but cannot authenticate'];
        }

        if ($testHost) {
            return $production
                ? [self::WARN, 'XentriPay', 'enabled against the sandbox host; complete the live onboarding before accepting real money']
                : [self::PASS, 'XentriPay', 'enabled against the sandbox host (non-production environment)'];
        }

        if ((string) config('payments.xentripay.webhook_secret') === '') {
            return [self::WARN, 'XENTRIPAY_WEBHOOK_SECRET', 'not set; settlement relies on status polling (app:poll-xentripay)'];
        }

        return [self::PASS, 'XentriPay', 'enabled against the live host with a webhook secret (value withheld)'];
    }

    private function monitoringCheck(bool $production): array
    {
        if (! config('monitoring.enabled')) {
            return $this->check(false, 'MONITORING_ENABLED', 'disabled', 'enable monitoring in production so errors and failed payments are recorded and alerted', $production);
        }

        $webhook = (string) config('monitoring.alert_webhook');

        if ($webhook === '') {
            return [self::WARN, 'MONITORING_ALERT_WEBHOOK', 'not set; events are stored but no operator is paged'];
        }

        if (! str_starts_with($webhook, 'https://')) {
            return [self::FAIL, 'MONITORING_ALERT_WEBHOOK', 'must be an https endpoint; alert payloads contain operational context'];
        }

        return [self::PASS, 'MONITORING_ALERT_WEBHOOK', 'configured (value withheld); delivery must be verified separately'];
    }

    private function logCheck(bool $production): array
    {
        $channel = (string) config('logging.default');
        $ok = in_array($channel, ['stderr', 'errorlog', 'syslog', 'stack'], true);

        return $this->check(
            $ok,
            'LOG_CHANNEL',
            $channel,
            'use stderr (or a stack that includes it) so container logs reach the platform collector',
            $production,
            warnOnly: true,
        );
    }

    private function writableCheck(string $label, string $path): array
    {
        return is_dir($path) && is_writable($path)
            ? [self::PASS, $label.' writable', 'yes']
            : [self::FAIL, $label.' writable', 'not writable; uploads, caches or compiled views will fail'];
    }

    private function databaseCheck(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return [self::PASS, 'Database', 'reachable ('.config('database.default').')'];
        } catch (Throwable) {
            return [self::FAIL, 'Database', 'unreachable; the application cannot serve requests'];
        }
    }

    private function adminMfaCheck(bool $production): array
    {
        try {
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                return [self::WARN, 'Admin MFA', 'two_factor columns missing; run migrations before deploying'];
            }

            $admins = User::query()->where('role', 'admin');
            $total = (clone $admins)->count();
            $enrolled = (clone $admins)->whereNotNull('two_factor_confirmed_at')->count();
        } catch (Throwable) {
            return [self::WARN, 'Admin MFA', 'could not query administrators'];
        }

        if ($total === 0) {
            return [self::WARN, 'Admin MFA', 'no administrator account exists yet; create one and complete enrollment before launch'];
        }

        if ($enrolled < $total) {
            return [self::WARN, 'Admin MFA', ($total - $enrolled).' of '.$total.' administrators have not completed enrollment; they cannot reach the admin area until they do'];
        }

        return [self::PASS, 'Admin MFA', $enrolled.' of '.$total.' administrators enrolled'];
    }

    private function paint(string $status): string
    {
        return match ($status) {
            self::PASS => '<fg=green>PASS</>',
            self::WARN => '<fg=yellow>WARN</>',
            self::FAIL => '<fg=red>FAIL</>',
            default => '<fg=gray>SKIP</>',
        };
    }
}
