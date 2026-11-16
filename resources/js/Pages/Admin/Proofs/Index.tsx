import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';
import AdminPagination from '../../../Components/AdminPagination';

const currency = new Intl.NumberFormat('en');

export default function ProofsIndex({ proofs, flash, errors }: any) {
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    return (
        <AdminLayout
            title="Payment proofs"
            description="Verify manual transfers against your merchant account before approving."
            flash={flash}
            errors={errors}
        >
            <p className="admin-notice mb-6">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M12 3 2.5 20h19L12 3Zm0 6v5m0 3v.5" />
                </svg>
                <span>Manual verification: review the payment in your merchant account before approving. Approving marks the order paid.</span>
            </p>

            {proofs.data.length > 0 ? (
                <div className="grid gap-5 xl:grid-cols-2">
                    {proofs.data.map((proof: any) => (
                        <article key={proof.id} className="admin-card">
                            <div className="admin-card-head">
                                <div>
                                    <h2 className="admin-card-title">{proof.order.public_reference}</h2>
                                    <p className="mt-1 text-sm text-[var(--muted)]">{proof.order.customer_name} · {proof.order.customer_phone}</p>
                                </div>
                                <span className="text-sm font-semibold">RWF {currency.format(proof.payment.amount_rwf)}</span>
                            </div>
                            <div className="admin-card-body space-y-5">
                                <dl className="admin-kv">
                                    <div><dt>Reference</dt><dd>{proof.reference || 'Not supplied'}</dd></div>
                                    <div><dt>Submitted</dt><dd>{proof.created_at ? new Date(proof.created_at).toLocaleString() : '—'}</dd></div>
                                </dl>
                                <a href={`/admin/proofs/${proof.id}/file`} className="admin-secondary-button">View private proof</a>

                                <div className="grid gap-4 border-t border-[var(--hairline)] pt-4 sm:grid-cols-2">
                                    <form action={`/admin/proofs/${proof.id}/approve`} method="post" className="space-y-3">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <label className="admin-field">Review note
                                            <input name="review_note" className="store-input" />
                                        </label>
                                        <button className="store-primary-button w-full">Confirm payment</button>
                                    </form>
                                    <form action={`/admin/proofs/${proof.id}/reject`} method="post" className="space-y-3">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <label className="admin-field">Rejection reason
                                            <input name="review_note" required className="store-input" />
                                        </label>
                                        <button className="admin-danger-button w-full">Reject proof</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            ) : (
                <div className="admin-card admin-empty">
                    <strong>No proofs awaiting review</strong>
                    <span>New manual payment proofs will appear here for verification.</span>
                </div>
            )}

            <AdminPagination links={proofs.links} label="Payment proofs pagination" />
        </AdminLayout>
    );
}
