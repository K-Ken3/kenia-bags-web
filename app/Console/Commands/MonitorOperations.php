<?php

namespace App\Console\Commands;

use App\Models\OperationalEvent;
use App\Models\Payment;
use App\Support\Monitoring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonitorOperations extends Command
{
    protected $signature = 'ops:monitor';

    protected $description = 'Evaluate operational signals and alert the operator';

    public function handle(Monitoring $monitoring): int
    {
        if (! config('monitoring.enabled')) {
            $this->info('Operational monitoring is disabled for this environment.');

            return self::SUCCESS;
        }

        $window = max(1, (int) config('monitoring.window_minutes', 15));
        $since = now()->subMinutes($window);
        $thresholds = (array) config('monitoring.thresholds', []);

        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '>=', $since)->count()
            : 0;

        $stuckMinutes = max(1, (int) ($thresholds['stuck_payments_minutes'] ?? 20));
        $stuckPayments = Payment::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($stuckMinutes))
            ->whereHas('order', fn ($query) => $query->where('order_status', 'awaiting_payment'))
            ->count();

        $counts = OperationalEvent::query()
            ->since($since)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $serverErrors = (int) ($counts['server_error'] ?? 0);
        $webhookFailures = (int) ($counts['webhook_failure'] ?? 0);
        $reconciliationFailures = (int) ($counts['payment_reconciliation_failure'] ?? 0);
        $adminFailures = (int) ($counts['admin_auth_failure'] ?? 0) + (int) ($counts['admin_mfa_failure'] ?? 0);
        $clientErrors = (int) ($counts['client_error'] ?? 0);

        $this->newLine();
        $this->table(['Signal', 'Value'], [
            ['Window', $window.' minutes'],
            ['Failed queue jobs', $failedJobs],
            ['Pending payments older than '.$stuckMinutes.'m', $stuckPayments],
            ['Server errors (5xx)', $serverErrors],
            ['Webhook failures', $webhookFailures],
            ['Payment reconciliation failures', $reconciliationFailures],
            ['Failed admin sign-in / MFA', $adminFailures],
            ['Browser errors reported', $clientErrors],
        ]);

        $critical = false;

        if ($serverErrors > 0) {
            $critical = true;
            $monitoring->alert('critical', $serverErrors.' server error(s) in the last '.$window.' minutes.', ['count' => $serverErrors], 'monitor:server_errors');
        }

        if ($failedJobs >= max(1, (int) ($thresholds['failed_jobs'] ?? 5))) {
            $critical = true;
            $monitoring->alert('critical', $failedJobs.' failed queue job(s) in the last '.$window.' minutes.', ['count' => $failedJobs], 'monitor:failed_jobs');
        }

        if ($stuckPayments >= max(1, (int) ($thresholds['stuck_payments_count'] ?? 3))) {
            $critical = true;
            $monitoring->alert('critical', $stuckPayments.' pending payment(s) older than '.$stuckMinutes.' minutes need reconciliation.', ['count' => $stuckPayments], 'monitor:stuck_payments');
        }

        if ($webhookFailures >= max(1, (int) ($thresholds['webhook_failures'] ?? 5))) {
            $monitoring->alert('critical', $webhookFailures.' payment webhook failure(s) in the last '.$window.' minutes. Check provider signatures and payloads.', ['count' => $webhookFailures], 'monitor:webhook_failures');
        }

        if ($reconciliationFailures > 0) {
            $critical = true;
            $monitoring->alert('critical', $reconciliationFailures.' payment webhook(s) failed amount/currency/order reconciliation.', ['count' => $reconciliationFailures], 'monitor:reconciliation');
        }

        if ($adminFailures >= max(1, (int) ($thresholds['admin_failures'] ?? 10))) {
            $monitoring->alert('warning', $adminFailures.' failed administrator sign-in/MFA attempt(s) in the last '.$window.' minutes.', ['count' => $adminFailures], 'monitor:admin_failures');
        }

        if ((string) config('monitoring.alert_webhook') === '') {
            $this->warn('MONITORING_ALERT_WEBHOOK is not configured; alerts are written to logs only.');
        }

        Cache::forever('ops:monitor:last_run', now()->toIso8601String());

        return $critical ? self::FAILURE : self::SUCCESS;
    }
}
