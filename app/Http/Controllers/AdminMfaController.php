<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AdminMfaService;
use App\Support\Monitoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminMfaController extends Controller
{
    public function __construct(private AdminMfaService $mfa) {}

    public function setup(Request $request)
    {
        $user = $this->admin($request);

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route(
                $request->session()->has('admin_mfa_verified_at') ? 'admin.security' : 'admin.mfa.challenge',
            );
        }

        if (! is_string($user->two_factor_secret) || $user->two_factor_secret === '') {
            $user->forceFill(['two_factor_secret' => $this->mfa->generateSecret()])->save();
        }

        return store_page('Auth/AdminMfaSetup', [
            'email' => $user->email,
            'secret' => $user->two_factor_secret,
            'qrCode' => $this->mfa->qrCodeDataUri($this->mfa->otpauthUrl($user, $user->two_factor_secret)),
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $this->admin($request);

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route('admin.security');
        }
        if (! is_string($user->two_factor_secret) || $user->two_factor_secret === '') {
            return redirect()->route('admin.mfa.setup')->with('error', 'Start the authenticator setup again.');
        }

        $key = 'admin-mfa-setup:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Please wait a few minutes and try again.',
            ]);
        }

        $timestep = $this->mfa->verifyCode($user, $request->string('code')->toString());
        if ($timestep === null) {
            RateLimiter::hit($key, 300);
            Log::warning('Administrator MFA confirmation failed.', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);
            app(Monitoring::class)->record('admin_mfa_failure', 'warning', 'Administrator MFA enrollment code rejected.', [
                'user_id' => $user->id,
                'ip_hash' => substr(hash('sha256', (string) $request->ip()), 0, 16),
                'stage' => 'enrollment',
            ]);

            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Check your authenticator clock and try again.',
            ]);
        }

        $recoveryCodes = $this->mfa->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $this->mfa->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_timestep' => $timestep,
        ])->save();

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->markVerified($request);
        $this->audit($user, 'auth.mfa_enabled');
        Log::info('Administrator MFA enabled.', ['user_id' => $user->id]);

        return redirect()->route('admin.security')
            ->with('success', 'Two-factor authentication is enabled. Store the recovery codes somewhere safe.')
            ->with('recovery_codes', $recoveryCodes);
    }

    public function challenge(Request $request)
    {
        $user = $this->admin($request);

        if (! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('admin.mfa.setup');
        }
        if ($request->session()->has('admin_mfa_verified_at')) {
            return redirect()->route('admin.dashboard');
        }

        return store_page('Auth/AdminMfaChallenge', ['email' => $user->email]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:40'],
        ]);
        $user = $this->admin($request);

        if (! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('admin.mfa.setup');
        }
        if ($request->session()->has('admin_mfa_verified_at')) {
            return redirect()->route('admin.dashboard');
        }

        $key = 'admin-mfa:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many verification attempts. Please wait a few minutes and try again.',
            ]);
        }

        $recoveryCode = trim((string) ($data['recovery_code'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $usedRecoveryCode = false;
        $verified = false;

        if ($recoveryCode !== '') {
            $usedRecoveryCode = $this->mfa->consumeRecoveryCode($user, $recoveryCode);
            $verified = $usedRecoveryCode;
        } elseif (preg_match('/^\d{6}$/', $code) === 1) {
            $timestep = $this->mfa->verifyCode($user, $code);
            if ($timestep !== null) {
                $user->forceFill(['two_factor_last_used_timestep' => $timestep])->save();
                $verified = true;
            }
        }

        if (! $verified) {
            RateLimiter::hit($key, 300);
            Log::warning('Administrator MFA challenge failed.', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'method' => $usedRecoveryCode ? 'recovery_code' : 'totp',
            ]);
            app(Monitoring::class)->record('admin_mfa_failure', 'warning', 'Administrator MFA challenge rejected.', [
                'user_id' => $user->id,
                'ip_hash' => substr(hash('sha256', (string) $request->ip()), 0, 16),
                'stage' => 'challenge',
            ]);

            throw ValidationException::withMessages([
                'code' => 'That verification code is not valid.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->markVerified($request);
        $this->invalidateOtherSessions($request, $user);
        $this->audit($user, $usedRecoveryCode ? 'auth.recovery_code_used' : 'auth.mfa_verified');
        Log::info('Administrator completed MFA verification.', [
            'user_id' => $user->id,
            'method' => $usedRecoveryCode ? 'recovery_code' : 'totp',
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function security(Request $request)
    {
        $user = $this->admin($request);
        $recoveryCodes = $request->session()->pull('recovery_codes');

        return store_page('Admin/Security', [
            'mfaEnabled' => $user->hasConfirmedTwoFactor(),
            'confirmedAt' => $user->two_factor_confirmed_at?->toIso8601String(),
            'recoveryCodeCount' => count($user->two_factor_recovery_codes ?? []),
            'recoveryCodes' => is_array($recoveryCodes) ? $recoveryCodes : [],
        ]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $this->admin($request);

        $codes = $this->mfa->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $this->mfa->hashRecoveryCodes($codes)])->save();

        $request->session()->regenerate();
        $this->markVerified($request);
        $this->invalidateOtherSessions($request, $user);
        $this->audit($user, 'auth.recovery_codes_regenerated', ['count' => count($codes)]);
        Log::notice('Administrator recovery codes regenerated.', ['user_id' => $user->id]);

        return redirect()->route('admin.security')
            ->with('success', 'New recovery codes generated. Previous codes no longer work.')
            ->with('recovery_codes', $codes);
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        return $user;
    }

    private function markVerified(Request $request): void
    {
        $now = now()->getTimestamp();
        $request->session()->forget('admin_mfa_pending');
        $request->session()->put('admin_mfa_verified_at', $now);
        $request->session()->put('admin_login_at', $now);
        $request->session()->put('admin_last_activity', $now);
    }

    private function invalidateOtherSessions(Request $request, User $user): void
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table', 'sessions'))) {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    private function audit(User $user, string $action, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }
}
