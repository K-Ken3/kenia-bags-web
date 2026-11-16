import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';
import AdminPagination from '../../../Components/AdminPagination';

function ProductStatus({ status }: { status: string }) {
    const tone = status === 'active'
        ? 'border-green-700 bg-green-50 text-green-900'
        : status === 'archived'
            ? 'border-stone-400 bg-stone-100 text-stone-700'
            : 'border-[#c98a6e] bg-[var(--primary-tint)] text-[#7e3316]';

    return <span className={`store-badge inline-flex min-h-7 items-center rounded-full border px-3 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.08em] ${tone}`}>{status}</span>;
}

export default function ProductsIndex({ products, flash, errors }: any) {
    return (
        <AdminLayout
            title="Products"
            description="Manage your catalogue, pricing, stock, and which pieces are visible in the store."
            actions={<a href="/admin/products/create" className="store-primary-button">Add product</a>}
            flash={flash}
            errors={errors}
        >
            <section className="admin-card overflow-hidden">
                <div className="admin-card-head">
                    <div>
                        <h2 className="admin-card-title">Catalogue</h2>
                        <p className="mt-1 text-sm text-[var(--muted)]">
                            {typeof products.total === 'number' ? `${products.total} product${products.total === 1 ? '' : 's'}` : 'Products'} in total.
                        </p>
                    </div>
                </div>
                {products.data.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="admin-table min-w-[42rem]">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th className="text-right">Price</th>
                                    <th className="text-right">Stock</th>
                                    <th>Status</th>
                                    <th className="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {products.data.map((product: any) => (
                                    <tr key={product.id}>
                                        <td>
                                            <div className="flex items-center gap-3">
                                                <span aria-hidden="true" className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--primary-tint)] font-serif text-lg text-[var(--primary-hover)]">
                                                    {product.name.slice(0, 1).toUpperCase()}
                                                </span>
                                                <div className="min-w-0">
                                                    <a href={`/admin/products/${product.id}/edit`} className="block truncate">{product.name}</a>
                                                    <span className="text-xs text-[var(--muted)]">/{product.slug}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{product.category?.name || '—'}</td>
                                        <td className="whitespace-nowrap text-right">RWF {new Intl.NumberFormat('en').format(product.price_rwf)}</td>
                                        <td className="text-right">{typeof product.stock_quantity === 'number' ? product.stock_quantity : '—'}</td>
                                        <td><ProductStatus status={product.status} /></td>
                                        <td className="text-right">
                                            <a href={`/admin/products/${product.id}/edit`} className="admin-link-button">Edit</a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="admin-empty">
                        <strong>No products yet</strong>
                        <span>Add your first product to start building the catalogue.</span>
                        <a href="/admin/products/create" className="store-primary-button mt-3">Add product</a>
                    </div>
                )}
            </section>

            <AdminPagination links={products.links} label="Products pagination" />
        </AdminLayout>
    );
}
