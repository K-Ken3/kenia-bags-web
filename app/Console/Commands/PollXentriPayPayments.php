<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Payments\XentriPayClient;
use App\Payments\XentriPayProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:poll-xentripay')]
#[Description('Check pending XentriPay collections and settle confirmed payments')]
class PollXentriPayPayments extends Command
{
    public function handle(XentriPayClient $client, XentriPayProvider $provider): int
    {
        if (! $client->enabled()) {
            $this->info('XentriPay is not enabled.');

            return self::SUCCESS;
        }

        $payments = Payment::whereIn('provider', XentriPayProvider::METHODS)
            ->where('status', 'pending')
            ->whereNotNull('reference')
            ->where('created_at', '>=', now()->subDay())
            ->whereHas('order', fn ($query) => $query
                ->whereIn('order_status', ['awaiting_payment', 'payment_review'])
                ->whereIn('payment_status', ['pending', 'under_review', 'failed']))
            ->oldest('id')
            ->limit(50)
            ->get();

        $checked = 0;
        foreach ($payments as $payment) {
            try {
                $provider->verify($payment);
                $checked++;
            } catch (\Throwable $exception) {
                Log::warning('XentriPay status poll failed.', [
                    'payment_id' => $payment->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->info($checked.' pending XentriPay payment(s) checked.');

        return self::SUCCESS;
    }
}
