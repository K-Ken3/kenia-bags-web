<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {email? : Administrator email address}';

    protected $description = 'Reset an administrator password and revoke existing sessions';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Administrator email');
        $user = User::where('role', 'admin')->where('email', $email)->first();

        if (! $user) {
            $this->error('No administrator account matches that email address.');

            return self::FAILURE;
        }

        $password = $this->secret('New administrator password (minimum 12 characters)');
        $confirmation = $this->secret('Confirm the new password');

        if (! is_string($password) || $password !== $confirmation) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password])->save();

            if (Schema::hasTable((string) config('session.table', 'sessions'))) {
                DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            AuditLog::create([
                'user_id' => null,
                'action' => 'auth.admin_password_reset',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => ['source' => 'console'],
            ]);
        });

        Log::notice('Administrator password reset from the console.', ['user_id' => $user->id]);
        $this->info('Administrator password updated and existing sessions revoked.');

        return self::SUCCESS;
    }
}
