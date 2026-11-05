<?php

namespace App\Payments;

use App\Exceptions\PaymentProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class XentriPayClient
{
    public function enabled(): bool
    {
        return (bool) config('payments.xentripay.enabled') && filled(config('payments.xentripay.api_key'));
    }

    public function sandbox(): bool
    {
        return str_contains((string) config('payments.xentripay.base_url'), 'test.');
    }

    public function initiate(array $payload): array
    {
        try {
            $response = $this->request()->post('/api/collections/initiate', $payload);
        } catch (ConnectionException $exception) {
            throw new PaymentProviderException('XentriPay did not respond. Please try again shortly.', previous: $exception);
        }

        $data = $this->json($response);

        if ((int) ($data['success'] ?? 0) !== 1 || (int) ($data['retcode'] ?? -1) !== 0) {
            throw new PaymentProviderException((string) ($data['reply'] ?? 'XentriPay rejected the payment request.'));
        }

        return $data;
    }

    public function status(string $customerRef): array
    {
        try {
            $response = $this->request()->get('/api/collections/status/'.rawurlencode($customerRef));
        } catch (ConnectionException $exception) {
            throw new PaymentProviderException('XentriPay did not respond. Please try again shortly.', previous: $exception);
        }

        $data = $this->json($response);

        if (! is_string($data['status'] ?? null)) {
            throw new PaymentProviderException((string) ($data['message'] ?? 'XentriPay returned no payment status.'));
        }

        return $data;
    }

    /**
     * @return array{0: string, 1: string} Local 07XXXXXXXX number and 250XXXXXXXXX MSISDN.
     */
    public static function normalizePhone(string $phone, ?string $method = null): array
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '250')) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) === 9) {
            $digits = '0'.$digits;
        }

        $pattern = match ($method) {
            'xentripay_momo' => '/^07[89]\d{7}$/',
            'xentripay_airtel' => '/^07[23]\d{7}$/',
            default => '/^07[2389]\d{7}$/',
        };

        if (! preg_match($pattern, $digits)) {
            throw ValidationException::withMessages([
                'customer_phone' => self::phoneErrorMessage($method),
            ]);
        }

        return [$digits, '250'.substr($digits, 1)];
    }

    public static function phoneErrorMessage(?string $method): string
    {
        return match ($method) {
            'xentripay_momo' => 'Enter a valid MTN Mobile Money number, for example 0780 000 000.',
            'xentripay_airtel' => 'Enter a valid Airtel Money number, for example 0730 000 000.',
            default => 'Enter a valid Rwandan mobile money number, for example 0780 000 000.',
        };
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('payments.xentripay.base_url'), '/'))
            ->withHeaders(['X-XENTRIPAY-KEY' => (string) config('payments.xentripay.api_key')])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(20);
    }

    private function json(Response $response): array
    {
        if (! $response->successful()) {
            $message = $response->json('message');

            throw new PaymentProviderException(
                is_string($message) && $message !== ''
                    ? $message
                    : 'XentriPay returned HTTP '.$response->status().'.',
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new PaymentProviderException('XentriPay returned an unexpected response.');
        }

        return $data;
    }
}
