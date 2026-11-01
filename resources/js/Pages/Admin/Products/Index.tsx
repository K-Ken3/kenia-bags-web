import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

export default function ProductsIndex({ products, flash, errors }: any) {
    return (
        <AdminLayout title="Products" flash={flash} errors={errors}>
            <a href="/admin/products/create" className="mb-5 inline-flex min-h-11 items-center bg-stone-900 px-5 text-white">Add product</a>
            <div className="overflow-x-auto border border-stone-300 bg-[var(--surface)]">
                <table className="w-full text-left text-sm">
                    <thead><tr className="border-b border-stone-300"><th className="p-3">Product</th><th className="p-3">Category</th><th className="p-3">Price</th><th className="p-3">Status</th><th className="p-3"></th></tr></thead>
                    <tbody>{products.data.map((product: any) => (
                        <tr key={product.id} className="border-b border-stone-200 last:border-0">
                            <td className="p-3">{product.name}</td><td className="p-3">{product.category?.name || '—'}</td>
                            <td className="p-3">RWF {product.price_rwf}</td><td className="p-3">{product.status}</td>
                            <td className="p-3"><a className="underline" href={`/admin/products/${product.id}/edit`}>Edit</a></td>
                        </tr>
                    ))}</tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
