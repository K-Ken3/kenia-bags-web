<?php

namespace App\Payments;

use App\Actions\SettleXentriPayPayment;
use App\Contracts\PaymentProvider;
use App\Exceptions\PaymentProviderException;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class XentriPayProvider implements PaymentProvider
{
    public function __construct(
        private XentriPayClient $client,
        private SettleXentriPayPayment $settle,
    ) {}

    public function initiate(Payment $payment): array
    {
        if (! $this->client->enabled()) {
            throw new PaymentProviderException('XentriPay is not configured.');
        }

        $order = $payment->order;
        if (! $order->customer_email) {
            throw new PaymentProviderException('An email address is required to start a mobile money payment.');
        }
        if ((int) $order->total_rwf < 100) {
            throw new PaymentProviderException('XentriPay requires an order total of at least RWF 100.');
        }

        [$cnumber, $msisdn] = XentriPayClient::normalizePhone($order->customer_phone);
        $customerRef = $payment->reference ?: $order->public_reference.'-'.$payment->id;

        $data = $this->client->initiate([
            'email' => $order->customer_email,
            'cname' => $order->customer_name,
            'amount' => (int) $order->total_rwf,
            'cnumber' => $cnumber,
            'msisdn' => $msisdn,
            'currency' => 'RWF',
            'pmethod' => 'momo',
            'customerRef' => $customerRef,
            'chargesIncluded' => (bool) config('payments.xentripay.charges_included', true),
        ]);

        $payment->update([
            'reference' => $customerRef,
            'provider_reference' => $data['refid'] ?? $payment->provider_reference,
            'raw_status' => 'xentripay.initiated',
        ]);

        return [
            'customer_ref' => $customerRef,
            'refid' => $data['refid'] ?? null,
            'url' => $data['url'] ?? null,
        ];
    }

    public function verify(Payment $payment): array
    {
        if (! $this->client->enabled()) {
            throw new PaymentProviderException('XentriPay is not configured.');
        }
        if (! $payment->reference) {
            throw new PaymentProviderException('This payment has not been sent to XentriPay yet.');
        }

        $data = $this->client->status($payment->reference);
        $status = self::mapStatus((string) $data['status']);
        $result = $this->settle->handle($payment, $status, $data, 'status_check');

        return ['status' => $result['status'], 'applied' => $result['applied'], 'raw' => $data];
    }

    public function handleWebhook(Request $request): array
    {
        $payload = $request->json()->all() ?: $request->all();
        $customerRef = data_get($payload, 'data.customerRef') ?: data_get($payload, 'customerRef');
        // The documented payload exposes the provider reference, not always customerRef.
        $providerRef = data_get($payload, 'data.reference')
            ?? data_get($payload, 'data.refid')
            ?? data_get($payload, 'data.rid');

        $payment = null;
        if (is_string($customerRef) && $customerRef !== '') {
            $payment = Payment::with('order')
                ->where('provider', 'xentripay_momo')
                ->where('reference', $customerRef)
                ->first();
        }
        if (! $payment && is_string($providerRef) && $providerRef !== '') {
            $payment = Payment::with('order')
                ->where('provider', 'xentripay_momo')
                ->where(fn ($query) => $query
                    ->where('provider_reference', $providerRef)
                    ->orWhere('reference', $providerRef))
                ->first();
        }

        if (! $payment) {
            Log::warning('XentriPay webhook references an unknown payment.', [
                'customer_ref' => $customerRef,
                'provider_ref' => $providerRef,
            ]);

            return ['processed' => false, 'reason' => 'unknown_reference'];
        }

        $amount = data_get($payload, 'data.amount');
        if ($amount !== null && (int) $amount !== (int) $payment->amount_rwf) {
            throw ValidationException::withMessages(['amount' => 'The webhook amount does not match the payment.']);
        }
        $currency = data_get($payload, 'data.currency');
        if ($currency !== null && strtoupper((string) $currency) !== 'RWF') {
            throw ValidationException::withMessages(['currency' => 'The webhook currency does not match the payment.']);
        }

        $status = self::mapStatus(
            (string) (data_get($payload, 'data.status') ?? ''),
            (string) ($request->header('X-Xentripay-Event') ?: data_get($payload, 'event', '')),
        );
        $eventId = $request->header('X-Xentripay-Idempotency-Key')
            ?: data_get($payload, 'idempotencyKey')
            ?: 'xentripay:'.hash('sha256', $request->getContent());

        try {
            return DB::transaction(function () use ($payment, $status, $payload, $eventId) {
                $event = PaymentEvent::firstOrCreate(
                    ['event_id' => $eventId],
                    [
                        'provider' => 'xentripay',
                        'payment_id' => $payment->id,
                        'event_type' => 'xentripay.webhook',
                        'payload' => $payload,
                    ],
                );

                if (! $event->wasRecentlyCreated) {
                    return ['processed' => false, 'event_id' => $event->event_id];
                }

                $result = $this->settle->handle($payment, $status, $payload, 'webhook');
                $event->update(['processed_at' => now()]);

                return [
                    'processed' => true,
                    'event_id' => $event->event_id,
                    'status' => $result['status'],
                    'customer_ref' => $payment->reference,
                ];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $event = PaymentEvent::where('event_id', $eventId)->first();
            if ($event) {
                return ['processed' => false, 'event_id' => $event->event_id];
            }

            throw $exception;
        }
    }

    public static function mapStatus(string $status, string $event = ''): string
    {
        $status = strtoupper(trim($status));

        if ($status === '' && $event !== '') {
            $status = strtoupper($event);
            if (str_contains($status, 'SUCCESS')) {
                return 'paid';
            }
            if (str_contains($status, 'FAIL')) {
                return 'failed';
            }
        }

        return match ($status) {
            'SUCCESS', 'SUCCESSFUL', 'PAID', 'COLLECTION_SUCCESSFUL' => 'paid',
            'FAILED', 'FAIL', 'DECLINED', 'CANCELLED', 'CANCELED', 'EXPIRED', 'COLLECTION_FAILED' => 'failed',
            default => 'pending',
        };
    }
}
