<?php

namespace App\Console\Commands;

use App\Models\OperationalEvent;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationalHealth extends Command
{
    protected $signature = 'ops:health';

    protected $description = 'Print an operator health summary without exposing credentials';

    public function handle(): int
    {
        $database = true;
        $cache = true;

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $database = false;
        }

        try {
            Cache::put('ops:health:probe', '1', 10);
            $cache = Cache::get('ops:health:probe') === '1';
        } catch (Throwable) {
            $cache = false;
        }

        $dayAgo = now()->subDay();

        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '>=', $dayAgo)->count()
            : 0;

        $events = OperationalEvent::query()
            ->since($dayAgo)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $thresholds = (array) config('monitoring.thresholds', []);
        $stuckMinutes = max(1, (int) ($thresholds['stuck_payments_minutes'] ?? 20));
        $stuckPayments = Payment::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($stuckMinutes))
            ->whereHas('order', fn ($query) => $query->where('order_status', 'awaiting_payment'))
            ->count();

        $lastMonitorRun = Cache::get('ops:monitor:last_run');

        $this->newLine();
        $this->table(['Check', 'Value'], [
            ['Environment', app()->environment()],
            ['PHP', PHP_VERSION],
            ['Database connection', $database ? 'ok ('.config('database.default').')' : 'unreachable'],
            ['Cache store', $cache ? 'ok ('.config('cache.default').')' : 'unreachable'],
            ['Session driver', (string) config('session.driver')],
            ['Queue connection', (string) config('queue.default')],
            ['Monitoring enabled', config('monitoring.enabled') ? 'yes' : 'no'],
            ['Alert webhook configured', config('monitoring.alert_webhook') ? 'yes' : 'no'],
            ['Last ops:monitor run', $lastMonitorRun ?: 'never'],
            ['Failed queue jobs (24h)', $failedJobs],
            ['Stuck pending payments', $stuckPayments],
            ['Server errors (24h)', (int) ($events['server_error'] ?? 0)],
            ['Webhook failures (24h)', (int) ($events['webhook_failure'] ?? 0)],
            ['Reconciliation failures (24h)', (int) ($events['payment_reconciliation_failure'] ?? 0)],
            ['Admin auth failures (24h)', (int) ($events['admin_auth_failure'] ?? 0) + (int) ($events['admin_mfa_failure'] ?? 0)],
            ['Browser errors reported (24h)', (int) ($events['client_error'] ?? 0)],
        ]);

        $healthy = $database && $cache;

        if (config('monitoring.enabled') && $lastMonitorRun === null) {
            $this->warn('The scheduler has not recorded an ops:monitor run yet. Verify the scheduler is running.');
        }

        if (! $healthy) {
            $this->error('Database or cache is unreachable. The application cannot serve requests reliably.');
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
