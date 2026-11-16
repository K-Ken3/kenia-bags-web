<?php

namespace App\Http\Controllers;

use App\Actions\OrderInventory;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Support\Monitoring;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WebhookController extends Controller
{
    /**
     * Generic provider webhook. This endpoint is disabled unless explicitly enabled
     * and configured with a shared secret; an unauthenticated caller must never be
     * able to confirm a payment.
     */
    public function handle(Request $request)
    {
        $secret = (string) config('payments.webhook_secret');

        if (! config('payments.webhook_enabled') || $secret === '') {
            Log::warning('Generic payment webhook called while disabled.', ['ip' => $request->ip()]);
            app(Monitoring::class)->record('webhook_failure', 'warning', 'Generic payment webhook called while disabled.', [
                'provider' => 'generic',
                'reason' => 'disabled',
                'ip_hash' => substr(hash('sha256', (string) $request->ip()), 0, 16),
            ]);
            abort(404);
        }

        $signature = (string) ($request->header('X-Signature') ?: $request->header('X-Webhook-Signature'));
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            Log::warning('Generic payment webhook signature verification failed.', ['ip' => $request->ip()]);
            app(Monitoring::class)->record('webhook_failure', 'warning', 'Generic payment webhook signature verification failed.', [
                'provider' => 'generic',
                'reason' => 'signature',
                'ip_hash' => substr(hash('sha256', (string) $request->ip()), 0, 16),
            ]);
            abort(401, 'Invalid signature');
        }

        $provider = (string) ($request->header('X-Payment-Provider') ?: $request->input('provider', 'unknown'));
        $provider = substr(preg_replace('/[^a-z0-9_.-]/i', '', $provider) ?: 'unknown', 0, 40);

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

                if ($provider !== 'unknown' && $payment->provider !== $provider) {
                    throw ValidationException::withMessages(['payment' => 'Payment provider mismatch']);
                }

                if ($payment->currency !== 'RWF' || (int) $payment->amount_rwf !== $validated['amount_rwf']) {
                    throw ValidationException::withMessages(['payment' => 'Amount/currency mismatch']);
                }

                if ((int) $payment->order->total_rwf !== $validated['amount_rwf']) {
                    throw ValidationException::withMessages(['order' => 'Order total mismatch']);
                }

                if (! in_array($payment->order->order_status, ['awaiting_payment', 'payment_review'], true)) {
                    Log::info('Webhook received for non-pending order', ['order_id' => $payment->order->id, 'status' => $payment->order->order_status]);
                    $event->update(['processed_at' => now()]);

                    return response()->json(['processed' => false, 'reason' => 'order_not_pending'], 200);
                }

                if (! in_array($payment->order->payment_status, ['pending', 'under_review'], true)) {
                    $event->update(['processed_at' => now()]);

                    return response()->json(['processed' => false, 'reason' => 'payment_not_pending'], 200);
                }

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
        } catch (ValidationException $exception) {
            app(Monitoring::class)->record('payment_reconciliation_failure', 'critical', 'Generic payment webhook failed reconciliation.', [
                'provider' => $provider,
                'payment_id' => $validated['payment_id'],
                'reason' => array_key_first($exception->errors()) ?: 'mismatch',
            ]);

            throw $exception;
        } catch (UniqueConstraintViolationException $exception) {
            $event = PaymentEvent::where('event_id', $validated['event_id'])->first();
            if ($event) {
                return response()->json(['processed' => false, 'event_id' => $event->event_id], 200);
            }
            throw $exception;
        }
    }
}
