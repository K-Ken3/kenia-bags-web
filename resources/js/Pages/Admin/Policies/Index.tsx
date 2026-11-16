import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

export default function PoliciesIndex({ policies, flash, errors }: any) {
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    return (
        <AdminLayout
            title="Store policies"
            description="Review and publish the policy pages customers see in the storefront footer."
            flash={flash}
            errors={errors}
        >
            <p className="admin-notice mb-6">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M12 3 2.5 20h19L12 3Zm0 6v5m0 3v.5" />
                </svg>
                <span>Draft policy text is not legal advice. Review each page with the appropriate owner or adviser before publishing.</span>
            </p>

            {policies.length > 0 ? (
                <div className="space-y-4">
                    {policies.map((policy: any, index: number) => (
                        <details key={policy.id} className="admin-card store-details overflow-hidden" open={index === 0}>
                            <summary className="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-5">
                                <div>
                                    <h2 className="font-serif text-xl">{policy.title}</h2>
                                    <p className="mt-0.5 text-xs text-[var(--muted)]">/policies/{policy.slug}</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className={`store-badge inline-flex min-h-7 items-center rounded-full border px-3 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.08em] ${policy.is_published ? 'border-green-700 bg-green-50 text-green-900' : 'border-stone-400 bg-stone-100 text-stone-700'}`}>
                                        {policy.is_published ? 'Published' : 'Draft'}
                                    </span>
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className="text-[var(--muted)]">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </summary>
                            <form action={`/admin/policies/${policy.id}`} method="post" className="space-y-5 border-t border-[var(--hairline)] p-5">
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="_method" value="PUT" />
                                <label className="flex items-center gap-3 rounded-2xl border border-[var(--hairline)] bg-[var(--paper)] px-4 py-3 text-sm">
                                    <input type="checkbox" name="is_published" value="1" defaultChecked={policy.is_published} className="h-4 w-4 accent-[var(--primary)]" />
                                    <span>Publish this page</span>
                                </label>
                                <label className="admin-field">Page title
                                    <input name="title" required maxLength={120} defaultValue={policy.title} className="store-input" />
                                </label>
                                <label className="admin-field">Page content
                                    <textarea name="body" required maxLength={30000} rows={10} defaultValue={policy.body} className="store-input" />
                                </label>
                                <div className="flex justify-end">
                                    <button className="store-primary-button">Save {policy.slug}</button>
                                </div>
                            </form>
                        </details>
                    ))}
                </div>
            ) : (
                <div className="admin-card admin-empty">
                    <strong>No policy pages yet</strong>
                    <span>Policy pages are created from the store seeder.</span>
                </div>
            )}
        </AdminLayout>
    );
}
