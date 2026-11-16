<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTrustedHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('app.trusted_hosts', []);

        if ($allowed !== [] && ! in_array(strtolower($request->getHost()), array_map('strtolower', $allowed), true)) {
            abort(400, 'Untrusted host.');
        }

        return $next($request);
    }
}
