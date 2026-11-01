<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return null;
    }

    public function share(Request $request): array
    {
        return [
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
