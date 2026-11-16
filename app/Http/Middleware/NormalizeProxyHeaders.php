<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalizes forwarded headers from a platform edge that publishes no stable
 * proxy CIDRs (Railway).
 *
 * Railway may append the real client address to X-Forwarded-For without
 * stripping client-supplied values. Laravel's default trusted-proxy handling
 * reads the leftmost value, which a client can spoof. This middleware takes the
 * rightmost valid address instead, rewrites REMOTE_ADDR, and collapses the
 * header so every later component (sessions, throttling, URL generation) sees
 * one trustworthy value.
 *
 * This is only safe when direct access to the application is blocked at the
 * network layer. See docs/DEPLOYMENT.md.
 */
class NormalizeProxyHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.trust_proxy_headers') || config('app.trusted_proxies')) {
            return $next($request);
        }

        $clientIp = $this->rightmostValidIp($request->headers->get('X-Forwarded-For'));
        if ($clientIp === null) {
            $realIp = trim((string) $request->headers->get('X-Real-IP'));
            if (filter_var($realIp, FILTER_VALIDATE_IP)) {
                $clientIp = $realIp;
            }
        }

        if ($clientIp !== null) {
            $request->server->set('REMOTE_ADDR', $clientIp);
            $request->headers->set('X-Forwarded-For', $clientIp);
        }

        $proto = strtolower(trim(explode(',', (string) $request->headers->get('X-Forwarded-Proto'))[0]));
        if (in_array($proto, ['http', 'https'], true)) {
            $request->server->set('HTTPS', $proto === 'https' ? 'on' : 'off');
            $request->server->set('REQUEST_SCHEME', $proto);
        }

        return $next($request);
    }

    private function rightmostValidIp(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        foreach (array_reverse(explode(',', $header)) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return null;
    }
}
