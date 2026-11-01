<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Actions\OrderInventory;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProofController extends Controller
{
    public function index()
    {
        return inertia('Admin/Proofs/Index', [
            'proofs' => PaymentProof::with(['order', 'payment'])
                ->where('status', 'pending')->latest()->paginate(20),
        ]);
    }

    public function show(PaymentProof $proof)
    {
        abort_unless(Storage::disk('local')->exists($proof->path), 404);
        return Storage::disk('local')->response($proof->path);
    }

    public function approve(Request $request, PaymentProof $proof, OrderInventory $inventory)
    {
        $validated = $request->validate(['review_note' => ['nullable', 'string', 'max:1000']]);

        DB::transaction(function () use ($proof, $validated, $inventory) {
            $proof = PaymentProof::with('order.items', 'payment')->lockForUpdate()->findOrFail($proof->id);
            if ($proof->status !== 'pending' || ! $proof->payment || $proof->payment->status !== 'under_review'
                || ! in_array($proof->order->order_status, ['awaiting_payment', 'payment_review'], true)
                || ! $proof->order->expires_at || $proof->order->expires_at->isPast()) {
                throw ValidationException::withMessages(['proof' => 'This payment proof is no longer awaiting review.']);
            }
            $inventory->consume($proof->order);
            $proof->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'review_note' => $validated['review_note'] ?? null,
            ]);
            $proof->payment->update([
                'status' => 'paid',
                'raw_status' => 'manually_verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);
            $proof->order->update([
                'payment_status' => 'paid',
                'order_status' => 'confirmed',
                'status' => 'confirmed',
            ]);
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'payment.manual_approved',
                'subject_type' => Order::class,
                'subject_id' => $proof->order_id,
                'metadata' => ['proof_id' => $proof->id, 'review_note' => $validated['review_note'] ?? null],
            ]);
        });

        return back()->with('success', 'Payment manually verified and order confirmed.');
    }

    public function reject(Request $request, PaymentProof $proof)
    {
        $validated = $request->validate(['review_note' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($proof, $validated) {
            $proof = PaymentProof::with('order', 'payment')->lockForUpdate()->findOrFail($proof->id);
            if ($proof->status !== 'pending' || ! $proof->payment || $proof->payment->status !== 'under_review'
                || ! in_array($proof->order->order_status, ['awaiting_payment', 'payment_review'], true)
                || ! $proof->order->expires_at || $proof->order->expires_at->isPast()) {
                throw ValidationException::withMessages(['proof' => 'This payment proof is no longer awaiting review.']);
            }
            $proof->update([
                'status' => 'rejected',
                'reviewed_by' => auth()->id(),
                'review_note' => $validated['review_note'],
            ]);
            $proof->payment->update(['status' => 'pending', 'raw_status' => 'proof_rejected']);
            $proof->order->update(['payment_status' => 'pending', 'order_status' => 'awaiting_payment', 'status' => 'awaiting_payment']);
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'payment.manual_rejected',
                'subject_type' => Order::class,
                'subject_id' => $proof->order_id,
                'metadata' => ['proof_id' => $proof->id, 'review_note' => $validated['review_note']],
            ]);
        });

        return back()->with('success', 'Payment proof rejected. The order remains unpaid.');
    }
}
