import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

export default function ProofsIndex({ proofs, flash, errors }: any) {
    return (
        <AdminLayout title="Payment proof review" flash={flash} errors={errors}>
            <p className="mb-5 border border-amber-300 bg-amber-50 p-4">Manual verification: review the payment in your merchant account before approving. Approving marks the order paid.</p>
            <div className="space-y-5">
                {proofs.data.map((proof: any) => (
                    <section key={proof.id} className="grid gap-5 border border-stone-300 bg-[var(--surface)] p-5 md:grid-cols-2">
                        <div>
                            <p className="font-semibold">{proof.order.public_reference} — RWF {proof.payment.amount_rwf}</p>
                            <p>{proof.order.customer_name} · {proof.order.customer_phone}</p>
                            <p>Reference: {proof.reference || 'Not supplied'}</p>
                            <a className="mt-3 inline-block underline" href={`/admin/proofs/${proof.id}/file`}>View private proof</a>
                        </div>
                        <div className="space-y-3">
                            <form action={`/admin/proofs/${proof.id}/approve`} method="post">
                                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                                <label className="block">Review note<input name="review_note" className="mt-1 min-h-11 w-full border bg-white px-3" /></label>
                                <button className="mt-3 min-h-11 bg-stone-900 px-5 text-white">Confirm payment</button>
                            </form>
                            <form action={`/admin/proofs/${proof.id}/reject`} method="post">
                                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                                <label className="block">Rejection reason<input name="review_note" required className="mt-1 min-h-11 w-full border bg-white px-3" /></label>
                                <button className="mt-3 min-h-11 border border-red-800 px-5 text-red-800">Reject proof</button>
                            </form>
                        </div>
                    </section>
                ))}
                {proofs.data.length === 0 && <p className="border border-stone-300 bg-[var(--surface)] p-5">No proofs are waiting for review.</p>}
            </div>
        </AdminLayout>
    );
}
