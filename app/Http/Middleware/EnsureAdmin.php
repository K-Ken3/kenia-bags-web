<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        abort_unless($request->user()->isAdmin(), 403);

        if (! $request->user()->hasConfirmedTwoFactor()) {
            return redirect()->route('admin.mfa.setup');
        }

        if (! $request->session()->has('admin_mfa_verified_at')) {
            return redirect()->route('admin.mfa.challenge');
        }

        $session = $request->session();
        $now = now()->getTimestamp();
        $loginAt = (int) $session->get('admin_login_at', $now);
        $lastActivity = (int) $session->get('admin_last_activity', $now);

        $idleLimit = max(1, (int) config('auth.admin_idle_timeout', 60)) * 60;
        $absoluteLimit = max($idleLimit, (int) config('auth.admin_session_lifetime', 480) * 60);

        if (($now - $lastActivity) > $idleLimit || ($now - $loginAt) > $absoluteLimit) {
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with('error', 'Your administrator session expired. Please sign in again.');
        }

        $session->put('admin_login_at', $loginAt);
        $session->put('admin_last_activity', $now);

        return $next($request);
    }
}
