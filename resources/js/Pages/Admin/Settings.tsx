import React from 'react';
import AdminLayout from '../../Components/AdminLayout';
import { settingText } from '../../Components/StoreContext';

export default function SettingsPage({ settings, brandLogoUrl, paymentMode, flash, errors }: any) {
    return (
        <AdminLayout title="Store settings" flash={flash} errors={errors}>
            <p className="mb-5 border border-amber-300 bg-amber-50 p-4">Payment configuration: {paymentMode}. A pay code and owner confirmation are required before manual MoMo appears at checkout.</p>
            <form action="/admin/settings" method="post" className="max-w-3xl space-y-5 border border-stone-300 bg-[var(--surface)] p-6">
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                <input type="hidden" name="_method" value="PUT" />
                <label className="block">Brand name<input name="brand_name" required defaultValue={settings.brand_name || 'Isimbi Mart'} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                <label className="block">Brand logo (JPEG, PNG or WebP, max 5 MB)
                    <input type="file" name="brand_logo" accept="image/jpeg,image/png,image/webp" className="mt-2 block" />
                    {brandLogoUrl && <img src={brandLogoUrl} alt="Current brand logo" width={240} height={80} loading="lazy" className="mt-3 max-h-16 w-auto" />}
                </label>
                <div className="grid gap-5 sm:grid-cols-2">
                    <label>Contact email<input name="contact_email" type="email" defaultValue={settingText(settings.contact_email, '')} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                    <label>Contact phone<input name="contact_phone" defaultValue={settingText(settings.contact_phone, '')} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                    <label>WhatsApp number<input name="whatsapp_number" defaultValue={settingText(settings.whatsapp_number, '')} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                    <label>Delivery fee (RWF)<input type="number" name="delivery_fee_rwf" min={0} required defaultValue={settings.delivery_fee_rwf || 0} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                    <label>Unpaid order expiry (minutes)<input type="number" name="unpaid_order_expiry_minutes" min={5} max={10080} required defaultValue={settings.unpaid_order_expiry_minutes || 60} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                    <label>MoMo pay code<input name="momo_pay_code" defaultValue={settings.momo_pay_code || ''} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                </div>
                <label className="block">Delivery announcement (shown on the storefront)<textarea name="delivery_information" maxLength={500} defaultValue={settingText(settings.delivery_information, '')} rows={3} className="mt-2 w-full border bg-white p-3" /></label>
                <label className="flex items-center gap-2"><input type="checkbox" name="delivery_fee_confirmed" value="1" defaultChecked={settings.delivery_fee_confirmed === '1'} />Delivery fee confirmed by owner</label>
                <label className="flex items-center gap-2"><input type="checkbox" name="manual_momo_enabled" value="1" defaultChecked={settings.manual_momo_enabled === '1'} />Enable manual MoMo (human verification only)</label>
                <button className="min-h-12 bg-stone-900 px-6 text-white">Save settings</button>
            </form>
        </AdminLayout>
    );
}
