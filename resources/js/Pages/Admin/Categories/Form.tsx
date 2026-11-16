import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

export default function CategoryForm({ category, flash, errors }: any) {
    return (
        <AdminLayout
            title={category ? `Edit ${category.name}` : 'Add category'}
            description="Collections appear in the storefront navigation and category browser."
            actions={<a href="/admin/categories" className="admin-secondary-button">Back to categories</a>}
            flash={flash}
            errors={errors}
        >
            <form action={category ? `/admin/categories/${category.id}` : '/admin/categories'} method="post" className="admin-card max-w-2xl">
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                {category && <input type="hidden" name="_method" value="PUT" />}
                <div className="admin-card-head">
                    <h2 className="admin-card-title">Category details</h2>
                </div>
                <div className="admin-card-body space-y-5">
                    <div className="admin-form-grid">
                        <label className="admin-field">Name
                            <input name="name" required defaultValue={category?.name || ''} className="store-input" placeholder="e.g. Everyday Bags" />
                        </label>
                        <label className="admin-field">Slug
                            <input name="slug" required defaultValue={category?.slug || ''} className="store-input" placeholder="e.g. everyday-bags" />
                        </label>
                    </div>
                    <label className="admin-field">Description
                        <textarea name="description" rows={3} defaultValue={category?.description || ''} className="store-input" />
                    </label>
                    <label className="admin-field">Position
                        <input type="number" name="position" min={0} defaultValue={category?.position || 0} className="store-input" />
                    </label>
                    <label className="flex items-center gap-3 rounded-2xl border border-[var(--hairline)] bg-[var(--paper)] px-4 py-3 text-sm">
                        <input type="checkbox" name="is_active" value="1" defaultChecked={category?.is_active ?? true} className="h-4 w-4 accent-[var(--primary)]" />
                        <span>Visible in store</span>
                    </label>
                </div>
                <div className="flex justify-end border-t border-[var(--hairline)] p-4">
                    <button className="store-primary-button">Save category</button>
                </div>
            </form>
        </AdminLayout>
    );
}
