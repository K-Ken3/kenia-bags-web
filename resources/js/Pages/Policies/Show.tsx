import React from 'react';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';
import { settingText } from '../../Components/StoreContext';

export default function PolicyShow({ slug, policy, contactEmail, contactPhone, brandName, cartCount }: any) {
    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />

            <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <p className="store-kicker">Isimbi Mart</p>
                <h1 className="store-section-title mt-3 text-[var(--chocolate)]">{policy.title}</h1>
                <div className="mt-5 whitespace-pre-wrap leading-7 text-[var(--muted)]">{policy.body}</div>
                {slug === 'contact' && (
                    <p className="mt-6">
                        Contact: {settingText(contactEmail, 'Email not provided')}
                        {settingText(contactPhone, '') && ` · ${settingText(contactPhone, '')}`}
                    </p>
                )}
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
