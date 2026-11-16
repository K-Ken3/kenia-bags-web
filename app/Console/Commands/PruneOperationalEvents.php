<?php

namespace App\Console\Commands;

use App\Models\OperationalEvent;
use Illuminate\Console\Command;

class PruneOperationalEvents extends Command
{
    protected $signature = 'ops:prune-events';

    protected $description = 'Delete operational events older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) config('monitoring.retention_days', 30));
        $cutoff = now()->subDays($days);

        $deleted = OperationalEvent::where('occurred_at', '<', $cutoff)->delete();

        $this->info('Pruned '.$deleted.' operational event(s) older than '.$days.' days.');

        return self::SUCCESS;
    }
}
