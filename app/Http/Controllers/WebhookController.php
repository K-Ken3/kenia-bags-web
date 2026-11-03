<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Order;
use App\Actions\OrderInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // If no provider is configured, reject
        $provider = $request->header('X-Payment-Provider') ?: $request->input('provider', 'unknown');
        
        // Basic signature verification scaffold - to be configured with real provider
        $signature = $request->header('X-Signature') ?: $request->header('X-Webhook-Signature');
        $secret = config('payments.webhook_secret');
        
        if ($secret && $signature) {
            $payload = $request->getContent();
            $expected = hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expected, $signature)) {
                Log::warning('Webhook signature verification failed', ['provider' => $provider, 'ip' => $request->ip()]);
                abort(401, 'Invalid signature');
            }
        } elseif (config('payments.require_webhook_signature', false)) {
            abort(401, 'Signature required');
        }

        // Validate required fields for idempotency
        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:255'],
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'status' => ['required', 'in:paid,failed,pending,expired,under_review'],
            'amount_rwf' => ['required', 'integer', 'min:0'],
        ]);

        try {
            return DB::transaction(function () use ($validated, $provider, $request) {
                $event = PaymentEvent::firstOrCreate(
                    ['event_id' => $validated['event_id']],
                    [
                        'provider' => $provider,
                        'payment_id' => $validated['payment_id'],
                        'event_type' => $provider.'.webhook',
                        'payload' => $request->except(['_token']),
                    ],
                );

                if (! $event->wasRecentlyCreated) {
                    return response()->json(['processed' => false, 'event_id' => $event->event_id], 200);
                }

                $payment = Payment::with('order.items')->lockForUpdate()->findOrFail($validated['payment_id']);
                
                // Validate payment matches
                if ($payment->currency !== 'RWF' || $payment->amount_rwf !== $validated['amount_rwf']) {
                    throw ValidationException::withMessages(['payment' => 'Amount/currency mismatch']);
                }

                if ($payment->order->total_rwf !== $validated['amount_rwf']) {
                    throw ValidationException::withMessages(['order' => 'Order total mismatch']);
                }

                // State machine validation
                if (! in_array($payment->order->order_status, ['awaiting_payment', 'payment_review'], true)) {
                    Log::info('Webhook received for non-pending order', ['order_id' => $payment->order->id, 'status' => $payment->order->order_status]);
                    $event->update(['processed_at' => now()]);
                    return response()->json(['processed' => false, 'reason' => 'order_not_pending'], 200);
                }

                if (! in_array($payment->order->payment_status, ['pending', 'under_review'], true)) {
                    $event->update(['processed_at' => now()]);
                    return response()->json(['processed' => false, 'reason' => 'payment_not_pending'], 200);
                }

                // Check expiry
                if ($payment->order->expires_at && $payment->order->expires_at->isPast()) {
                    $event->update(['processed_at' => now()]);
                    return response()->json(['processed' => false, 'reason' => 'order_expired'], 200);
                }

                $payment->update(['status' => $validated['status'], 'raw_status' => $provider.'.'.$validated['status']]);
                
                if ($validated['status'] === 'paid') {
                    app(OrderInventory::class)->consume($payment->order);
                    $payment->update(['verified_at' => now()]);
                    $payment->order->update([
                        'payment_status' => 'paid',
                        'order_status' => 'confirmed',
                        'status' => 'confirmed',
                    ]);
                } elseif ($validated['status'] === 'expired') {
                    app(OrderInventory::class)->release($payment->order);
                    $payment->order->update([
                        'payment_status' => 'expired',
                        'order_status' => 'cancelled',
                        'status' => 'cancelled',
                    ]);
                } elseif ($validated['status'] === 'failed') {
                    $payment->order->update(['payment_status' => 'failed']);
                }

                $event->update(['processed_at' => now()]);
                return response()->json(['processed' => true, 'event_id' => $event->event_id], 200);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $event = PaymentEvent::where('event_id', $validated['event_id'])->first();
            if ($event) {
                return response()->json(['processed' => false, 'event_id' => $event->event_id], 200);
            }
            throw $exception;
        }
    }
}
