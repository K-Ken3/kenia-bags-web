<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function show(Order $order)
    {
        if (request()->session()->get('verified_order_reference') !== $order->public_reference) {
            return redirect()->route('order.lookup')
                ->with('error', 'Verify your order reference and phone number to view payment instructions.');
        }

        return redirect()->route('order.show', $order);
    }

    public function uploadProof(Request $request, Order $order)
    {
        abort_unless($request->session()->get('verified_order_reference') === $order->public_reference, 403);
        $validated = $request->validate([
            'proof' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $payment = $order->payments()->where('provider', 'manual_momo')->first();
        if (! $payment || ! in_array($order->order_status, ['awaiting_payment', 'payment_review'], true)
            || ! $order->expires_at || $order->expires_at->isPast()
            || ! in_array($order->payment_status, ['pending', 'under_review'], true)) {
            throw ValidationException::withMessages(['proof' => 'Payment proof cannot be uploaded for this order.']);
        }

        $file = $request->file('proof');
        $dimensions = getimagesize($file->getPathname());
        if (! $dimensions || $dimensions[0] > 4000 || $dimensions[1] > 4000) {
            throw ValidationException::withMessages(['proof' => 'Choose an image no larger than 4000 by 4000 pixels.']);
        }
        $source = imagecreatefromstring($file->getContent());
        if (! $source) {
            throw ValidationException::withMessages(['proof' => 'The uploaded image could not be decoded.']);
        }
        ob_start();
        $encodedOk = imagewebp($source, null, 82);
        $encoded = ob_get_clean();
        imagedestroy($source);
        if (! $encodedOk || ! is_string($encoded) || $encoded === '') {
            throw ValidationException::withMessages(['proof' => 'The uploaded image could not be converted.']);
        }

        $path = 'payment-proofs/'.Str::uuid().'.webp';
        if (! Storage::disk('local')->put($path, $encoded)) {
            abort(500, 'The payment proof could not be saved.');
        }

        DB::transaction(function () use ($payment, $order, $path, $validated) {
            PaymentProof::create([
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'path' => $path,
                'reference' => $validated['reference'] ?? null,
                'status' => 'pending',
                'uploaded_at' => now(),
            ]);
            $payment->update(['status' => 'under_review', 'raw_status' => 'customer_uploaded_proof']);
            $order->update(['payment_status' => 'under_review', 'order_status' => 'payment_review', 'status' => 'payment_review']);
        });

        return back()->with('success', 'Proof received. The store will manually review it; your order is not yet paid.');
    }
}
