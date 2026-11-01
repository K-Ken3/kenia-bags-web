import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

export default function CategoryForm({ category, flash, errors }: any) {
    return (
        <AdminLayout title={category ? 'Edit category' : 'Add category'} flash={flash} errors={errors}>
            <form action={category ? `/admin/categories/${category.id}` : '/admin/categories'} method="post" className="max-w-xl space-y-5 border border-stone-300 bg-[var(--surface)] p-6">
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                {category && <input type="hidden" name="_method" value="PUT" />}
                <label className="block">Name<input name="name" required defaultValue={category?.name || ''} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                <label className="block">Slug<input name="slug" required defaultValue={category?.slug || ''} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                <label className="block">Description<textarea name="description" defaultValue={category?.description || ''} className="mt-2 w-full border bg-white p-3" /></label>
                <label className="block">Position<input type="number" name="position" min={0} defaultValue={category?.position || 0} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                <label className="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" defaultChecked={category?.is_active ?? true} />Visible in store</label>
                <button className="min-h-12 bg-stone-900 px-6 text-white">Save category</button>
            </form>
        </AdminLayout>
    );
}
