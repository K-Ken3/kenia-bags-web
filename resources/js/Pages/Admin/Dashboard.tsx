import React from 'react';
import AdminLayout from '../../Components/AdminLayout';
import StatusBadge from '../../Components/StatusBadge';

export default function Dashboard({ summary, recentOrders, flash, errors }: any) {
    const tiles = [
        ['Orders today', summary.orders_today],
        ['Awaiting payment', summary.awaiting_payment],
        ['To dispatch', summary.to_dispatch],
        ['Paid revenue', `RWF ${summary.paid_revenue_rwf}`],
    ];
    return (
        <AdminLayout title="Overview" flash={flash} errors={errors}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {tiles.map(([label, value]) => (
                    <section key={String(label)} className="border border-stone-300 bg-[var(--surface)] p-5">
                        <p className="text-sm text-stone-600">{label}</p>
                        <p className="mt-2 text-2xl font-semibold">{value}</p>
                    </section>
                ))}
            </div>
            <section className="mt-10">
                <h2 className="mb-4 text-xl font-semibold">Recent orders</h2>
                <div className="overflow-x-auto border border-stone-300 bg-[var(--surface)]">
                    <table className="w-full text-left text-sm">
                        <thead><tr className="border-b border-stone-300"><th className="p-3">Reference</th><th className="p-3">Customer</th><th className="p-3">Order</th><th className="p-3">Payment</th><th className="p-3">Total</th></tr></thead>
                        <tbody>{recentOrders.map((order: any) => (
                            <tr key={order.id} className="border-b border-stone-200 last:border-0">
                                <td className="p-3"><a href={`/admin/orders/${order.public_reference}`} className="underline">{order.public_reference}</a></td>
                                <td className="p-3">{order.customer_name}</td>
                                <td className="p-3"><StatusBadge status={order.order_status} /></td>
                                <td className="p-3"><StatusBadge status={order.payment_status} /></td>
                                <td className="p-3">RWF {order.total_rwf}</td>
                            </tr>
                        ))}</tbody>
                    </table>
                </div>
            </section>
        </AdminLayout>
    );
}
