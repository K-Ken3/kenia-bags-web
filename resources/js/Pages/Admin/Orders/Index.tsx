import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';
import AdminPagination from '../../../Components/AdminPagination';
import StatusBadge from '../../../Components/StatusBadge';

export default function OrdersIndex({ orders, filters = {}, flash, errors }: any) {
    const hasFilters = Boolean(filters.search || filters.order_status || filters.payment_status);

    return (
        <AdminLayout
            title="Orders"
            description="Search every order, follow payment progress, and move paid orders through fulfilment."
            flash={flash}
            errors={errors}
        >
            <form action="/admin/orders" method="get" className="admin-filter-bar">
                <label className="admin-field" style={{ flexBasis: '16rem' }}>
                    Search
                    <input name="search" defaultValue={filters.search || ''} placeholder="Reference, name, or phone" className="store-input" type="search" />
                </label>
                <label className="admin-field">
                    Order status
                    <select name="order_status" defaultValue={filters.order_status || ''} className="store-input">
                        <option value="">All order statuses</option>
                        {['awaiting_payment', 'payment_review', 'confirmed', 'packing', 'dispatched', 'delivered', 'cancelled'].map((status) => <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>)}
                    </select>
                </label>
                <label className="admin-field">
                    Payment status
                    <select name="payment_status" defaultValue={filters.payment_status || ''} className="store-input">
                        <option value="">All payment statuses</option>
                        {['pending', 'under_review', 'paid', 'failed', 'cancelled', 'expired', 'unknown'].map((status) => <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>)}
                    </select>
                </label>
                <div className="flex items-center gap-2">
                    <button type="submit" className="store-primary-button">Filter</button>
                    {hasFilters ? <a href="/admin/orders" className="admin-link-button">Clear</a> : null}
                </div>
            </form>

            <section className="admin-card mt-6 overflow-hidden">
                <div className="admin-card-head">
                    <div>
                        <h2 className="admin-card-title">All orders</h2>
                        <p className="mt-1 text-sm text-[var(--muted)]">
                            {typeof orders.total === 'number' ? `${orders.total} order${orders.total === 1 ? '' : 's'}` : 'Orders'} found
                            {hasFilters ? ' for the current filters' : ''}.
                        </p>
                    </div>
                </div>
                {orders.data.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="admin-table min-w-[46rem]">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>Order status</th>
                                    <th>Payment status</th>
                                    <th className="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.map((order: any) => (
                                    <tr key={order.id}>
                                        <td><a href={`/admin/orders/${order.public_reference}`}>{order.public_reference}</a></td>
                                        <td>{order.customer_name}</td>
                                        <td className="whitespace-nowrap">{order.customer_phone}</td>
                                        <td><StatusBadge status={order.order_status} /></td>
                                        <td><StatusBadge status={order.payment_status} /></td>
                                        <td className="whitespace-nowrap text-right font-semibold">RWF {new Intl.NumberFormat('en').format(order.total_rwf)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="admin-empty">
                        <strong>No orders match these filters</strong>
                        <span>Try a different reference, status, or clear the filters.</span>
                    </div>
                )}
            </section>

            <AdminPagination links={orders.links} label="Orders pagination" />
        </AdminLayout>
    );
}
