import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';
import StatusBadge from '../../../Components/StatusBadge';

export default function OrdersIndex({ orders, filters = {}, flash, errors }: any) {
    return (
        <AdminLayout title="Orders" flash={flash} errors={errors}>
            <form action="/admin/orders" method="get" className="mb-5 flex flex-wrap gap-3">
                <input name="search" defaultValue={filters.search || ''} placeholder="Reference, name, or phone" className="min-h-11 min-w-64 border border-stone-300 bg-white px-3" />
                <select name="order_status" defaultValue={filters.order_status || ''} className="min-h-11 border border-stone-300 bg-white px-3">
                    <option value="">All order statuses</option>
                    {['awaiting_payment', 'payment_review', 'confirmed', 'packing', 'dispatched', 'delivered', 'cancelled'].map((status) => <option key={status}>{status}</option>)}
                </select>
                <select name="payment_status" defaultValue={filters.payment_status || ''} className="min-h-11 border border-stone-300 bg-white px-3">
                    <option value="">All payment statuses</option>
                    {['pending', 'under_review', 'paid', 'failed', 'cancelled', 'expired', 'unknown'].map((status) => <option key={status}>{status}</option>)}
                </select>
                <button className="min-h-11 bg-stone-900 px-5 text-white">Filter</button>
            </form>
            <div className="overflow-x-auto border border-stone-300 bg-[var(--surface)]">
                <table className="w-full text-left text-sm">
                    <thead><tr className="border-b border-stone-300"><th className="p-3">Reference</th><th className="p-3">Customer</th><th className="p-3">Phone</th><th className="p-3">Order status</th><th className="p-3">Payment status</th><th className="p-3">Total</th></tr></thead>
                    <tbody>{orders.data.map((order: any) => (
                        <tr key={order.id} className="border-b border-stone-200 last:border-0">
                            <td className="p-3"><a className="underline" href={`/admin/orders/${order.public_reference}`}>{order.public_reference}</a></td>
                            <td className="p-3">{order.customer_name}</td>
                            <td className="p-3">{order.customer_phone}</td>
                            <td className="p-3"><StatusBadge status={order.order_status} /></td>
                            <td className="p-3"><StatusBadge status={order.payment_status} /></td>
                            <td className="p-3">RWF {order.total_rwf}</td>
                        </tr>
                    ))}</tbody>
                </table>
                {orders.data.length === 0 && <p className="p-6">No orders match these filters.</p>}
            </div>
        </AdminLayout>
    );
}
