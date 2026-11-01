import React from 'react';
import StatusMessages from '../../Components/StatusMessages';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';

export default function OrderLookup({ flash, errors, brandName, cartCount, contactEmail, contactPhone }: any) {
    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />

            <StatusMessages flash={flash} errors={errors} />
            <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-10">
                <header className="mb-8 border-b border-[var(--hairline)] pb-6">
                    <p className="store-kicker">Order updates</p>
                    <h1 className="store-section-title mt-3 text-[var(--chocolate)]">Track your order</h1>
                </header>
                <form action="/order/lookup" method="post" className="max-w-xl space-y-5 border border-[var(--hairline)] bg-[var(--surface)] p-5 sm:p-7">
                    <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                    <div>
                        <label htmlFor="public_reference" className="mb-2 block text-xs font-bold uppercase tracking-wider">Order reference</label>
                        <input id="public_reference" type="text" name="public_reference" required maxLength={33} className="store-input" placeholder="KB-…" />
                    </div>
                    <div>
                        <label htmlFor="phone" className="mb-2 block text-xs font-bold uppercase tracking-wider">Phone number used at checkout</label>
                        <input id="phone" type="tel" name="phone" required maxLength={32} className="store-input" />
                    </div>
                    <button type="submit" className="store-primary-button w-full">
                        Find order
                    </button>
                </form>
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
