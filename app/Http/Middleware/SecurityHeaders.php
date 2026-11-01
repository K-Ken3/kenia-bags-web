<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->environment('production')) {
            $imageSources = ["'self'", 'data:'];
            $catalogDisk = config('filesystems.disks.'.config('store.catalog_disk', 'public'), []);
            if (($catalogDisk['driver'] ?? null) === 's3') {
                $catalogUrl = $catalogDisk['url'] ?? $catalogDisk['endpoint'] ?? null;
                $catalogHost = $catalogUrl ? parse_url($catalogUrl, PHP_URL_HOST) : null;
                if ($catalogHost) {
                    $imageSources[] = 'https://'.$catalogHost;
                } elseif (! empty($catalogDisk['bucket']) && ! empty($catalogDisk['region'])) {
                    $imageSources[] = 'https://'.$catalogDisk['bucket'].'.s3.'.$catalogDisk['region'].'.amazonaws.com';
                }
            }

            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self'; style-src 'self'; img-src ".implode(' ', $imageSources)."; font-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; upgrade-insecure-requests",
            );
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
