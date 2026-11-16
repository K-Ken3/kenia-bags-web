import React from 'react';
import AdminLayout from '../../Components/AdminLayout';
import { settingText } from '../../Components/StoreContext';

export default function SettingsPage({ settings, brandLogoUrl, paymentMode, flash, errors }: any) {
    return (
        <AdminLayout
            title="Store settings"
            description="Brand, contact details, delivery rules, and payment configuration for the storefront."
            flash={flash}
            errors={errors}
        >
            <form action="/admin/settings" method="post" className="space-y-6">
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                <input type="hidden" name="_method" value="PUT" />

                <section className="admin-card">
                    <div className="admin-card-head">
                        <h2 className="admin-card-title">Brand</h2>
                    </div>
                    <div className="admin-card-body space-y-5">
                        <label className="admin-field">Brand name
                            <input name="brand_name" required defaultValue={settings.brand_name || 'Isimbi Mart'} className="store-input" />
                        </label>
                        <div className="flex flex-wrap items-center gap-5 rounded-2xl border border-[var(--hairline)] bg-[var(--paper)] p-4">
                            {brandLogoUrl ? <img src={brandLogoUrl} alt="Current brand logo" width={240} height={80} loading="lazy" className="h-16 w-auto rounded-xl object-contain" /> : null}
                            <label className="admin-field min-w-[14rem] flex-1">Brand logo (JPEG, PNG or WebP, max 5 MB)
                                <input type="file" name="brand_logo" accept="image/jpeg,image/png,image/webp" className="store-input" />
                            </label>
                        </div>
                    </div>
                </section>

                <section className="admin-card">
                    <div className="admin-card-head">
                        <h2 className="admin-card-title">Contact</h2>
                    </div>
                    <div className="admin-card-body">
                        <div className="admin-form-grid">
                            <label className="admin-field">Contact email
                                <input name="contact_email" type="email" defaultValue={settingText(settings.contact_email, '')} className="store-input" />
                            </label>
                            <label className="admin-field">Contact phone
                                <input name="contact_phone" defaultValue={settingText(settings.contact_phone, '')} className="store-input" />
                            </label>
                            <label className="admin-field">WhatsApp number
                                <input name="whatsapp_number" defaultValue={settingText(settings.whatsapp_number, '')} className="store-input" />
                            </label>
                        </div>
                    </div>
                </section>

                <section className="admin-card">
                    <div className="admin-card-head">
                        <div>
                            <h2 className="admin-card-title">Delivery</h2>
                            <p className="mt-1 text-sm text-[var(--muted)]">Fees and timing shown to customers at checkout.</p>
                        </div>
                    </div>
                    <div className="admin-card-body space-y-5">
                        <div className="admin-form-grid">
                            <label className="admin-field">Delivery fee (RWF)
                                <input type="number" name="delivery_fee_rwf" min={0} required defaultValue={settings.delivery_fee_rwf || 0} className="store-input" />
                            </label>
                            <label className="admin-field">Unpaid order expiry (minutes)
                                <input type="number" name="unpaid_order_expiry_minutes" min={5} max={10080} required defaultValue={settings.unpaid_order_expiry_minutes || 60} className="store-input" />
                            </label>
                        </div>
                        <label className="admin-field">Delivery announcement (shown on the storefront)
                            <textarea name="delivery_information" maxLength={500} defaultValue={settingText(settings.delivery_information, '')} rows={3} className="store-input" />
                        </label>
                        <label className="flex items-center gap-3 rounded-2xl border border-[var(--hairline)] bg-[var(--paper)] px-4 py-3 text-sm">
                            <input type="checkbox" name="delivery_fee_confirmed" value="1" defaultChecked={settings.delivery_fee_confirmed === '1'} className="h-4 w-4 accent-[var(--primary)]" />
                            <span>Delivery fee confirmed by owner</span>
                        </label>
                    </div>
                </section>

                <section className="admin-card">
                    <div className="admin-card-head">
                        <h2 className="admin-card-title">Payments</h2>
                    </div>
                    <div className="admin-card-body space-y-5">
                        <p className="admin-notice">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M4 6h16v12H4zM8 12h8M12 9v6" />
                            </svg>
                            <span>Payment configuration: {paymentMode}. A pay code and owner confirmation are required before manual MoMo appears at checkout.</span>
                        </p>
                        <label className="admin-field">MoMo pay code
                            <input name="momo_pay_code" defaultValue={settings.momo_pay_code || ''} className="store-input" />
                        </label>
                        <label className="flex items-center gap-3 rounded-2xl border border-[var(--hairline)] bg-[var(--paper)] px-4 py-3 text-sm">
                            <input type="checkbox" name="manual_momo_enabled" value="1" defaultChecked={settings.manual_momo_enabled === '1'} className="h-4 w-4 accent-[var(--primary)]" />
                            <span>Enable manual MoMo (human verification only)</span>
                        </label>
                    </div>
                </section>

                <div className="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--hairline)] bg-[var(--surface)] p-3 shadow-[var(--shadow-lift)]">
                    <p className="px-1 text-sm text-[var(--muted)]">Storefront wording and fees update immediately after saving.</p>
                    <button className="store-primary-button">Save settings</button>
                </div>
            </form>
        </AdminLayout>
    );
}
