import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

export default function PoliciesIndex({ policies, flash, errors }: any) {
    return (
        <AdminLayout title="Store policies" flash={flash} errors={errors}>
            <p className="mb-5 border border-amber-300 bg-amber-50 p-4">
                Draft policy text is not legal advice. Review each page with the appropriate owner or adviser before publishing.
            </p>
            <div className="space-y-5">
                {policies.map((policy: any) => (
                    <form key={policy.id} action={`/admin/policies/${policy.id}`} method="post" className="space-y-4 border border-stone-300 bg-[var(--surface)] p-5">
                        <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                        <input type="hidden" name="_method" value="PUT" />
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="font-serif text-2xl">{policy.title}</h2>
                            <label className="flex min-h-11 items-center gap-2">
                                <input type="checkbox" name="is_published" value="1" defaultChecked={policy.is_published} />
                                Publish this page
                            </label>
                        </div>
                        <label className="block">Page title
                            <input name="title" required maxLength={120} defaultValue={policy.title} className="mt-2 min-h-11 w-full border border-stone-300 bg-white px-3" />
                        </label>
                        <label className="block">Page content
                            <textarea name="body" required maxLength={30000} rows={8} defaultValue={policy.body} className="mt-2 w-full border border-stone-300 bg-white p-3" />
                        </label>
                        <button className="min-h-11 bg-stone-900 px-5 text-white">Save {policy.slug}</button>
                    </form>
                ))}
                {policies.length === 0 && <p className="border border-stone-300 bg-[var(--surface)] p-5">No policy pages have been seeded yet.</p>}
            </div>
        </AdminLayout>
    );
}
