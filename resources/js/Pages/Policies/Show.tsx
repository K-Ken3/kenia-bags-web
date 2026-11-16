import React from 'react';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';
import { settingText } from '../../Components/StoreContext';

export default function PolicyShow({ slug, policy, contactEmail, contactPhone, brandName, cartCount }: any) {
    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />

            <main className="store-page mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
                <p className="store-kicker">Isimbi Mart</p>
                <h1 className="store-section-title mt-3 text-[var(--chocolate)]">{policy.title}</h1>
                <div className="mt-6 rounded-[20px] border border-[var(--hairline)] bg-[var(--surface)] p-6 shadow-[var(--shadow-soft)] sm:p-8">
                    <div className="whitespace-pre-wrap leading-7 text-[var(--muted)]">{policy.body}</div>
                    {slug === 'contact' && (
                        <p className="mt-6 border-t border-[var(--hairline)] pt-5">
                            Contact: {settingText(contactEmail, 'Email not provided')}
                            {settingText(contactPhone, '') && ` · ${settingText(contactPhone, '')}`}
                        </p>
                    )}
                </div>
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
