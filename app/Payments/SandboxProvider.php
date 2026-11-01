<?php

namespace App\Payments;

use App\Contracts\PaymentProvider;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Actions\OrderInventory;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;

class SandboxProvider implements PaymentProvider
{
    public function __construct()
    {
        if (! app()->environment(['local', 'testing']) || ! config('payments.sandbox_enabled')) {
            throw new \LogicException('The payment sandbox is available only when explicitly enabled in local or test environments.');
        }
    }

    public function initiate(Payment $payment): array
    {
        return ['label' => 'SANDBOX — NOT REAL MONEY', 'status' => $payment->status];
    }

    public function verify(Payment $payment): array
    {
        return ['status' => $payment->status, 'verified' => $payment->status === 'paid'];
    }

    public function handleWebhook(Request $request): array
    {
        $data = $request->validate([
            'event_id' => ['required', 'uuid'],
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'status' => ['required', 'in:paid,failed,pending,expired'],
            'amount_rwf' => ['required', 'integer', 'min:0'],
        ]);

        try {
            return DB::transaction(function () use ($data) {
            $event = PaymentEvent::firstOrCreate(
                ['event_id' => $data['event_id']],
                ['provider' => 'sandbox', 'payment_id' => $data['payment_id'], 'event_type' => 'sandbox.webhook', 'payload' => $data],
            );
            if ($event->wasRecentlyCreated) {
                $payment = Payment::with('order.items')->lockForUpdate()->findOrFail($data['payment_id']);
                if ($payment->provider !== 'sandbox'
                    || $payment->currency !== 'RWF'
                    || $payment->amount_rwf !== $data['amount_rwf']
                    || $payment->order->total_rwf !== $data['amount_rwf']
                    || ! in_array($payment->order->order_status, ['awaiting_payment', 'payment_review'], true)
                    || ! in_array($payment->order->payment_status, ['pending', 'under_review'], true)) {
                    throw ValidationException::withMessages(['payment' => 'The sandbox event does not match this payment.']);
                }
                $payment->update(['status' => $data['status'], 'raw_status' => $data['status']]);
                if ($data['status'] === 'paid') {
                    app(OrderInventory::class)->consume($payment->order);
                    $payment->update(['verified_at' => now()]);
                    $payment->order->update([
                        'payment_status' => 'paid',
                        'order_status' => 'confirmed',
                        'status' => 'confirmed',
                    ]);
                } elseif ($data['status'] === 'expired') {
                    app(OrderInventory::class)->release($payment->order);
                    $payment->order->update([
                        'payment_status' => 'expired',
                        'order_status' => 'cancelled',
                        'status' => 'cancelled',
                    ]);
                } elseif ($data['status'] === 'failed') {
                    $payment->order->update(['payment_status' => 'failed']);
                }
                $event->update(['processed_at' => now()]);
            }

                return ['processed' => $event->wasRecentlyCreated, 'event_id' => $event->event_id];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $event = PaymentEvent::where('event_id', $data['event_id'])->first();
            if ($event) {
                return ['processed' => false, 'event_id' => $event->event_id];
            }

            throw $exception;
        }
    }

    public function simulate(Payment $payment, string $status): void
    {
        if ($payment->provider !== 'sandbox' || ! in_array($status, ['paid', 'failed', 'pending', 'expired'], true)) {
            throw ValidationException::withMessages(['status' => 'This sandbox status cannot be simulated.']);
        }
        $this->handleWebhook(new Request([
            'event_id' => (string) Str::uuid(),
            'payment_id' => $payment->id,
            'status' => $status,
            'amount_rwf' => $payment->amount_rwf,
        ]));
    }
}
