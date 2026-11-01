import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

export default function CategoriesIndex({ categories, flash, errors }: any) {
    return (
        <AdminLayout title="Categories" flash={flash} errors={errors}>
            <a href="/admin/categories/create" className="mb-5 inline-flex min-h-11 items-center bg-stone-900 px-5 text-white">Add category</a>
            <div className="border border-stone-300 bg-[var(--surface)]">
                {categories.data.map((category: any) => <div key={category.id} className="flex flex-wrap justify-between gap-3 border-b border-stone-200 p-4 last:border-0"><span>{category.name} <small className="text-stone-600">{category.products_count} products · {category.is_active ? 'active' : 'hidden'}</small></span><a className="underline" href={`/admin/categories/${category.id}/edit`}>Edit</a></div>)}
            </div>
        </AdminLayout>
    );
}
