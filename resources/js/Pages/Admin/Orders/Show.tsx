import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';
import StatusBadge from '../../../Components/StatusBadge';

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
    return (
        <AdminLayout title={`Order ${order.public_reference}`} flash={flash} errors={errors}>
            <div className="grid gap-8 lg:grid-cols-2">
                <section className="space-y-2 border border-stone-300 bg-[var(--surface)] p-5">
                    <h2 className="text-lg font-semibold">Customer details</h2>
                    <p>{order.customer_name}</p><p>{order.customer_phone}</p><p>{order.customer_email}</p><p>{order.delivery_location}</p>
                    <p>Order status: <StatusBadge status={order.order_status} /></p>
                    <p>Payment status: <StatusBadge status={order.payment_status} /></p>
                    <p>Delivery status: <StatusBadge status={order.delivery_status} /></p>
                    <p>Total: <strong>RWF {order.total_rwf}</strong></p>
                </section>
                <form action={`/admin/orders/${order.public_reference}`} method="post" className="space-y-4 border border-stone-300 bg-[var(--surface)] p-5">
                    <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                    <input type="hidden" name="_method" value="PUT" />
                    <h2 className="text-lg font-semibold">Update status</h2>
                    <label className="block">Order status
                        <select name="order_status" defaultValue={order.order_status} className="mt-2 min-h-11 w-full border border-stone-300 bg-white px-3">
                            {[order.order_status, ...(transitions[order.order_status] || [])].map((status) => <option key={status}>{status}</option>)}
                        </select>
                    </label>
                    <p className="text-sm text-stone-600">Delivery status updates automatically when an eligible paid order is dispatched or delivered.</p>
                    <button className="min-h-11 bg-stone-900 px-5 text-white">Save status</button>
                </form>
            </div>
            <section className="mt-8 border border-stone-300 bg-[var(--surface)] p-5">
                <h2 className="text-lg font-semibold">Items</h2>
                {order.items.map((item: any) => <div key={item.id} className="flex justify-between border-b border-stone-200 py-3 last:border-0"><span>{item.product_name} — {item.variant_name} × {item.quantity}</span><strong>RWF {item.unit_price_rwf * item.quantity}</strong></div>)}
            </section>
            {sandboxEnabled && order.payments.some((payment: any) => payment.provider === 'sandbox') && (
                <section className="mt-8 border border-amber-400 bg-amber-50 p-5">
                    <h2 className="font-semibold">SANDBOX — NOT REAL MONEY</h2>
                    <div className="mt-4 flex flex-wrap gap-3">
                        {['paid', 'failed', 'pending', 'expired'].map((status) => (
                            <form key={status} action={`/admin/orders/${order.public_reference}/sandbox`} method="post">
                                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                                <input type="hidden" name="status" value={status} />
                                <button className="min-h-11 border border-amber-800 px-4">{status}</button>
                            </form>
                        ))}
                    </div>
                </section>
            )}
            <section className="mt-8 border border-stone-300 bg-[var(--surface)] p-5">
                <h2 className="text-lg font-semibold">Payment history</h2>
                {order.payments.map((payment: any) => (
                    <div key={payment.id} className="mt-3 border-t border-stone-200 pt-3">
                        <p>{payment.provider} — {payment.status} — RWF {payment.amount_rwf}</p>
                        {payment.proofs.map((proof: any) => (
                            <p key={proof.id} className="mt-2">Proof: {proof.status} {proof.review_note ? `— ${proof.review_note}` : ''} <a className="underline" href={`/admin/proofs/${proof.id}/file`}>View file</a></p>
                        ))}
                    </div>
                ))}
            </section>
        </AdminLayout>
    );
}
