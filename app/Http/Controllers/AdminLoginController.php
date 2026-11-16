<?php

namespace App\Http\Controllers;

use App\Support\Monitoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function create(Request $request)
    {
        if (Auth::check() && Auth::user()?->isAdmin()) {
            $user = Auth::user();

            if (! $user->hasConfirmedTwoFactor()) {
                return redirect()->route('admin.mfa.setup');
            }

            return $request->session()->has('admin_mfa_verified_at')
                ? redirect()->route('admin.dashboard')
                : redirect()->route('admin.mfa.challenge');
        }

        return store_page('Auth/AdminLogin');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in a few minutes.',
            ]);
        }

        if (! Auth::attempt([...$credentials, 'role' => 'admin'], false)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            Log::warning('Administrator login failed.', [
                'email_hash' => hash('sha256', Str::lower($credentials['email'])),
                'ip' => $request->ip(),
            ]);
            app(Monitoring::class)->record('admin_auth_failure', 'warning', 'Administrator sign-in failed.', [
                'ip_hash' => substr(hash('sha256', (string) $request->ip()), 0, 16),
            ]);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match an administrator account.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();
        $request->session()->forget('admin_mfa_verified_at');
        $request->session()->put('admin_mfa_pending', true);
        $request->session()->put('admin_login_at', now()->getTimestamp());
        $request->session()->put('admin_last_activity', now()->getTimestamp());

        return redirect()->route(
            Auth::user()?->hasConfirmedTwoFactor() ? 'admin.mfa.challenge' : 'admin.mfa.setup',
        );
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
