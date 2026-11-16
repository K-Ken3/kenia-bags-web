import React from 'react';
import AdminLayout from '../../Components/AdminLayout';
import StatusBadge from '../../Components/StatusBadge';

const currency = new Intl.NumberFormat('en');

export default function Dashboard({ summary, recentOrders, flash, errors }: any) {
    const tiles = [
        { label: 'Orders today', value: currency.format(summary.orders_today), icon: 'M6 8h12l1 12H5L6 8Zm3 0V6a3 3 0 0 1 6 0v2' },
        { label: 'Awaiting payment', value: currency.format(summary.awaiting_payment), icon: 'M12 7v5l3 2M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z' },
        { label: 'To dispatch', value: currency.format(summary.to_dispatch), icon: 'M3 7h11v8H3zM14 10h4l3 3v2h-7M6.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm11 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z' },
        { label: 'Paid revenue', value: `RWF ${currency.format(summary.paid_revenue_rwf)}`, icon: 'M4 6h16v12H4zM8 12h8M12 9v6' },
    ];

    return (
        <AdminLayout
            title="Overview"
            description="A calm snapshot of what needs attention across orders, payments, and fulfilment."
            actions={<a href="/admin/orders" className="store-primary-button">Review orders</a>}
            flash={flash}
            errors={errors}
        >
            <div className="admin-stats">
                {tiles.map((tile) => (
                    <section key={tile.label} className="admin-stat">
                        <span className="admin-stat-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.7} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d={tile.icon} />
                            </svg>
                        </span>
                        <p className="admin-stat-label">{tile.label}</p>
                        <p className="admin-stat-value">{tile.value}</p>
                    </section>
                ))}
            </div>

            <section className="admin-card mt-8 overflow-hidden">
                <div className="admin-card-head">
                    <div>
                        <h2 className="admin-card-title">Recent orders</h2>
                        <p className="mt-1 text-sm text-[var(--muted)]">The latest activity across your store.</p>
                    </div>
                    <a href="/admin/orders" className="admin-secondary-button">View all orders</a>
                </div>
                {recentOrders.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Order</th>
                                    <th>Payment</th>
                                    <th className="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentOrders.map((order: any) => (
                                    <tr key={order.id}>
                                        <td><a href={`/admin/orders/${order.public_reference}`}>{order.public_reference}</a></td>
                                        <td>{order.customer_name}</td>
                                        <td><StatusBadge status={order.order_status} /></td>
                                        <td><StatusBadge status={order.payment_status} /></td>
                                        <td className="whitespace-nowrap text-right font-semibold">RWF {currency.format(order.total_rwf)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="admin-empty">
                        <strong>No orders yet</strong>
                        <span>New orders will appear here as soon as customers check out.</span>
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
