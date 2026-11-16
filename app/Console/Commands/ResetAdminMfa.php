<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ResetAdminMfa extends Command
{
    protected $signature = 'admin:reset-mfa {email? : Administrator email address} {--force : Skip the confirmation prompt}';

    protected $description = 'Reset an administrator authenticator and revoke existing sessions (lost-device recovery)';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Administrator email');
        $user = User::where('role', 'admin')->where('email', $email)->first();

        if (! $user) {
            $this->error('No administrator account matches that email address.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Reset MFA for '.$user->email.'? They will have to enroll again.')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_timestep' => null,
            ])->save();

            if (Schema::hasTable((string) config('session.table', 'sessions'))) {
                DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            AuditLog::create([
                'user_id' => null,
                'action' => 'auth.admin_mfa_reset',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => ['source' => 'console'],
            ]);
        });

        Log::notice('Administrator MFA reset from the console.', ['user_id' => $user->id]);
        $this->info('Administrator MFA reset and existing sessions revoked. The next sign-in must enroll again.');

        return self::SUCCESS;
    }
}
