<?php

namespace App\Http\Controllers;

use App\Payments\XentriPayProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XentriPayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = (string) config('payments.xentripay.webhook_secret');
        abort_if($secret === '', 404);

        $signature = (string) $request->header('X-Xentripay-Signature');
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            Log::warning('XentriPay webhook signature verification failed.', ['ip' => $request->ip()]);
            abort(401, 'Invalid signature');
        }

        return response()->json(app(XentriPayProvider::class)->handleWebhook($request));
    }
}
