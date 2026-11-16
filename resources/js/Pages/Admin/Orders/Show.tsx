import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';
import StatusBadge from '../../../Components/StatusBadge';

const currency = new Intl.NumberFormat('en');

export default function OrderDetail({ order, sandboxEnabled = false, flash, errors }: any) {
    const transitions: Record<string, string[]> = {
        awaiting_payment: ['payment_review', 'cancelled'],
        payment_review: ['awaiting_payment', 'cancelled'],
        confirmed: ['packing', 'cancelled'],
        packing: ['dispatched', 'cancelled'],
        dispatched: ['delivered'],
        delivered: [],
        cancelled: [],
    };
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    return (
        <AdminLayout
            title={`Order ${order.public_reference}`}
            description="Review the customer, payment trails, and fulfilment state for this order."
            actions={<a href="/admin/orders" className="admin-secondary-button">Back to orders</a>}
            flash={flash}
            errors={errors}
        >
            <div className="admin-detail-grid">
                <section className="admin-card">
                    <div className="admin-card-head">
                        <h2 className="admin-card-title">Customer details</h2>
                        <span className="text-xs text-[var(--muted)]">
                            {order.created_at ? new Date(order.created_at).toLocaleString() : ''}
                        </span>
                    </div>
                    <div className="admin-card-body">
                        <dl className="admin-kv">
                            <div><dt>Name</dt><dd>{order.customer_name}</dd></div>
                            <div><dt>Phone</dt><dd>{order.customer_phone}</dd></div>
                            <div><dt>Email</dt><dd className="break-all">{order.customer_email}</dd></div>
                            <div><dt>Delivery</dt><dd>{order.delivery_location}</dd></div>
                            {order.notes ? <div><dt>Notes</dt><dd>{order.notes}</dd></div> : null}
                            <div><dt>Order status</dt><dd><StatusBadge status={order.order_status} /></dd></div>
                            <div><dt>Payment status</dt><dd><StatusBadge status={order.payment_status} /></dd></div>
                            <div><dt>Delivery status</dt><dd><StatusBadge status={order.delivery_status} /></dd></div>
                            <div><dt>Total</dt><dd className="text-lg font-semibold">RWF {currency.format(order.total_rwf)}</dd></div>
                        </dl>
                    </div>
                </section>

                <form action={`/admin/orders/${order.public_reference}`} method="post" className="admin-card">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="PUT" />
                    <div className="admin-card-head">
                        <h2 className="admin-card-title">Update status</h2>
                    </div>
                    <div className="admin-card-body space-y-5">
                        <label className="admin-field">
                            Order status
                            <select name="order_status" defaultValue={order.order_status} className="store-input">
                                {[order.order_status, ...(transitions[order.order_status] || [])].map((status) => (
                                    <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>
                                ))}
                            </select>
                        </label>
                        <p className="text-sm leading-relaxed text-[var(--muted)]">
                            Delivery status updates automatically when an eligible paid order is dispatched or delivered.
                        </p>
                        <button className="store-primary-button">Save status</button>
                    </div>
                </form>
            </div>

            <section className="admin-card mt-6 overflow-hidden">
                <div className="admin-card-head">
                    <h2 className="admin-card-title">Items</h2>
                    <span className="text-xs text-[var(--muted)]">{order.items.length} item{order.items.length === 1 ? '' : 's'}</span>
                </div>
                <div className="overflow-x-auto">
                    <table className="admin-table min-w-[34rem]">
                        <thead>
                            <tr><th>Product</th><th>Option</th><th className="text-right">Qty</th><th className="text-right">Line total</th></tr>
                        </thead>
                        <tbody>
                            {order.items.map((item: any) => (
                                <tr key={item.id}>
                                    <td>{item.product_name}</td>
                                    <td>{item.variant_name}</td>
                                    <td className="text-right">{item.quantity}</td>
                                    <td className="whitespace-nowrap text-right font-semibold">RWF {currency.format(item.unit_price_rwf * item.quantity)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            {sandboxEnabled && order.payments.some((payment: any) => payment.provider === 'sandbox') && (
                <section className="admin-card mt-6 border-amber-300 bg-[#f8ecd9]">
                    <div className="admin-card-head border-[#e2c8a6]">
                        <div>
                            <h2 className="admin-card-title">Sandbox payments</h2>
                            <p className="mt-1 text-sm text-[#6b4a1f]">Test-only controls — no real money moves.</p>
                        </div>
                    </div>
                    <div className="admin-card-body space-y-4">
                        <p className="admin-notice">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M12 3 2.5 20h19L12 3Zm0 6v5m0 3v.5" />
                            </svg>
                            <span>Simulating a result changes the payment state for this test order only.</span>
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {['paid', 'failed', 'pending', 'expired'].map((status) => (
                                <form key={status} action={`/admin/orders/${order.public_reference}/sandbox`} method="post">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <input type="hidden" name="status" value={status} />
                                    <button className="admin-secondary-button">{status}</button>
                                </form>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            <section className="admin-card mt-6">
                <div className="admin-card-head">
                    <h2 className="admin-card-title">Payment history</h2>
                </div>
                <div className="admin-card-body">
                    {order.payments.length > 0 ? (
                        <div className="space-y-4">
                            {order.payments.map((payment: any) => (
                                <div key={payment.id} className="rounded-2xl border border-[var(--hairline)] bg-[var(--paper)] p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-semibold capitalize">{payment.provider.replaceAll('_', ' ')}</p>
                                        <StatusBadge status={payment.status} />
                                    </div>
                                    <p className="mt-1 text-sm text-[var(--muted)]">RWF {currency.format(payment.amount_rwf)}</p>
                                    {payment.proofs.length > 0 ? (
                                        <ul className="mt-3 space-y-2 border-t border-[var(--hairline)] pt-3 text-sm">
                                            {payment.proofs.map((proof: any) => (
                                                <li key={proof.id} className="flex flex-wrap items-center justify-between gap-2">
                                                    <span>
                                                        Proof: <strong className="font-semibold capitalize">{proof.status}</strong>
                                                        {proof.review_note ? ` — ${proof.review_note}` : ''}
                                                    </span>
                                                    <a href={`/admin/proofs/${proof.id}/file`} className="admin-link-button">View file</a>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="admin-empty">
                            <strong>No payments recorded</strong>
                            <span>Payment attempts and proofs will appear here.</span>
                        </div>
                    )}
                </div>
            </section>
        </AdminLayout>
    );
}
