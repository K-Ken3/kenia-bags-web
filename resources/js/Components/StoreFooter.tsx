import React from 'react';
import { useContext } from 'react';
import { settingText, StoreContext } from './StoreContext';

type Props = {
    brandName?: string;
    contactEmail?: string;
    contactPhone?: string;
};

export default function StoreFooter({ brandName = 'Isimbi Mart', contactEmail = '', contactPhone = '' }: Props) {
    const shared = useContext(StoreContext);
    const email = settingText(contactEmail, '');
    const phone = settingText(contactPhone, '');
    const policies = shared.storePolicies || [];

    return (
        <footer className="mt-12 border-t border-[var(--hairline)] bg-white">
            <div className="mx-auto grid max-w-[88rem] gap-8 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-10">
                <div className="sm:col-span-2 lg:col-span-1">
                    {shared.brandLogo
                        ? <img src={shared.brandLogo} alt={brandName} width={180} height={80} loading="lazy" className="max-h-14 w-auto max-w-36 object-contain object-left" />
                        : <p className="font-serif text-xl text-[var(--chocolate)]">{brandName}</p>}
                    <p className="mt-3 max-w-xs text-sm leading-6 text-[var(--muted)]">Thoughtfully selected bags and everyday essentials.</p>
                </div>
                <div>
                    <h2 className="store-kicker text-[var(--chocolate)]">Explore</h2>
                    <nav aria-label="Shop navigation" className="mt-3 flex flex-col items-start gap-2 text-sm text-[var(--muted)]">
                        <a href="/shop" className="hover:text-[var(--chocolate)]">Shop all</a>
                        <a href="/order/lookup" className="hover:text-[var(--chocolate)]">Track your order</a>
                    </nav>
                </div>
                {policies.length > 0 && (
                    <div>
                        <h2 className="store-kicker text-[var(--chocolate)]">Information</h2>
                        <nav aria-label="Store policies" className="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm">
                            {policies.map((policy) => (
                                <a key={policy.slug} href={`/policies/${policy.slug}`} className="text-[var(--muted)] hover:text-[var(--chocolate)]">{policy.title}</a>
                            ))}
                        </nav>
                    </div>
                )}
                {(email || phone) && (
                    <div>
                        <h2 className="store-kicker text-[var(--chocolate)]">Contact</h2>
                        {email && <a href={`mailto:${email}`} className="mt-3 block break-words text-sm text-[var(--muted)] hover:text-[var(--chocolate)]">{email}</a>}
                        {phone && <a href={`tel:${phone}`} className="mt-1 block text-sm text-[var(--muted)] hover:text-[var(--chocolate)]">{phone}</a>}
                    </div>
                )}
            </div>
            <div className="border-t border-[var(--hairline)] px-4 py-4 text-center text-[0.65rem] uppercase tracking-wider text-[var(--muted)]">
                © {new Date().getFullYear()} {brandName}
            </div>
        </footer>
    );
}
