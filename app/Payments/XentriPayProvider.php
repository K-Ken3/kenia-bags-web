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
    public const METHODS = ['xentripay_momo', 'xentripay_airtel', 'xentripay_card'];

    public const LABELS = [
        'xentripay_momo' => 'MTN Mobile Money',
        'xentripay_airtel' => 'Airtel Money',
        'xentripay_card' => 'Visa / Mastercard',
    ];

    public function __construct(
        private XentriPayClient $client,
        private SettleXentriPayPayment $settle,
    ) {}

    public static function isXentri(string $method): bool
    {
        return in_array($method, self::METHODS, true);
    }

    public static function label(?string $method): string
    {
        return self::LABELS[$method] ?? 'Online payment';
    }

    public static function methodFor(Payment $payment): string
    {
        foreach ([$payment->method, $payment->provider] as $method) {
            if (is_string($method) && self::isXentri($method)) {
                return $method;
            }
        }

        return 'xentripay_momo';
    }

    public function initiate(Payment $payment): array
    {
        if (! $this->client->enabled()) {
            throw new PaymentProviderException('XentriPay is not configured.');
        }

        $order = $payment->order;
        $method = self::methodFor($payment);
        if (! $order->customer_email) {
            throw new PaymentProviderException('An email address is required to start an online payment.');
        }
        if ((int) $order->total_rwf < 100) {
            throw new PaymentProviderException('XentriPay requires an order total of at least RWF 100.');
        }

        $customerRef = $payment->reference ?: $order->public_reference.'-'.$payment->id;
        $payload = [
            'email' => $order->customer_email,
            'cname' => $order->customer_name,
            'amount' => (int) $order->total_rwf,
            'currency' => 'RWF',
            'customerRef' => $customerRef,
            'chargesIncluded' => (bool) config('payments.xentripay.charges_included', true),
        ];

        if ($method === 'xentripay_card') {
            $returnUrl = route('xentripay.return', $order);
            $payload['pmethod'] = 'cc';
            $payload['redirecturl'] = $returnUrl;
            $payload['returl'] = $returnUrl;
        } else {
            [$cnumber, $msisdn] = XentriPayClient::normalizePhone($order->customer_phone, $method);
            $payload['pmethod'] = 'momo';
            $payload['cnumber'] = $cnumber;
            $payload['msisdn'] = $msisdn;
        }

        $data = $this->client->initiate($payload);
        $checkoutUrl = $method === 'xentripay_card' ? self::gatewayUrl($data) : null;

        $payment->update([
            'reference' => $customerRef,
            'provider_reference' => $data['refid'] ?? $payment->provider_reference,
            'checkout_url' => $checkoutUrl,
            'raw_status' => 'xentripay.initiated',
        ]);

        return [
            'customer_ref' => $customerRef,
            'refid' => $data['refid'] ?? null,
            'checkout_url' => $checkoutUrl,
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
                ->whereIn('provider', self::METHODS)
                ->where('reference', $customerRef)
                ->first();
        }
        if (! $payment && is_string($providerRef) && $providerRef !== '') {
            $payment = Payment::with('order')
                ->whereIn('provider', self::METHODS)
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

    private static function gatewayUrl(array $data): string
    {
        $url = $data['url']
            ?? data_get($data, 'data.url')
            ?? $data['redirect_url']
            ?? $data['payment_url']
            ?? data_get($data, 'data.redirect_url');

        $scheme = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_SCHEME)) : '';
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new PaymentProviderException('XentriPay did not return a card payment page.');
        }

        return $url;
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
