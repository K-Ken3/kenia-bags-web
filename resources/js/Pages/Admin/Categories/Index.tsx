import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';
import AdminPagination from '../../../Components/AdminPagination';

export default function CategoriesIndex({ categories, flash, errors }: any) {
    return (
        <AdminLayout
            title="Categories"
            description="Organise the catalogue into collections customers can browse."
            actions={<a href="/admin/categories/create" className="store-primary-button">Add category</a>}
            flash={flash}
            errors={errors}
        >
            {categories.data.length > 0 ? (
                <div className="grid gap-4 md:grid-cols-2">
                    {categories.data.map((category: any) => (
                        <article key={category.id} className="admin-card p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="font-serif text-xl">{category.name}</h2>
                                    <p className="mt-0.5 text-xs text-[var(--muted)]">/{category.slug}</p>
                                </div>
                                <span className={`store-badge inline-flex min-h-7 items-center rounded-full border px-3 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.08em] ${category.is_active ? 'border-green-700 bg-green-50 text-green-900' : 'border-stone-400 bg-stone-100 text-stone-700'}`}>
                                    {category.is_active ? 'Visible' : 'Hidden'}
                                </span>
                            </div>
                            {category.description ? <p className="mt-3 line-clamp-2 text-sm text-[var(--muted)]">{category.description}</p> : null}
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--hairline)] pt-4">
                                <span className="text-sm text-[var(--muted)]">{category.products_count} product{category.products_count === 1 ? '' : 's'}</span>
                                <a href={`/admin/categories/${category.id}/edit`} className="admin-secondary-button">Edit</a>
                            </div>
                        </article>
                    ))}
                </div>
            ) : (
                <div className="admin-card admin-empty">
                    <strong>No categories yet</strong>
                    <span>Create a collection to group products on the storefront.</span>
                    <a href="/admin/categories/create" className="store-primary-button mt-3">Add category</a>
                </div>
            )}

            <AdminPagination links={categories.links} label="Categories pagination" />
        </AdminLayout>
    );
}
