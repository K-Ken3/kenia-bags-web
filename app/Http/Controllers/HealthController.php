<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Public liveness/readiness probe. Returns only a status flag so it can be
     * used by Railway health checks or an external uptime monitor without
     * leaking infrastructure details.
     */
    public function index()
    {
        $database = true;
        $cache = true;

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $database = false;
        }

        try {
            Cache::put('ops:health:probe', '1', 10);
            $cache = Cache::get('ops:health:probe') === '1';
        } catch (Throwable) {
            $cache = false;
        }

        $healthy = $database && $cache;

        return response()
            ->json(['status' => $healthy ? 'ok' : 'degraded'], $healthy ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
