import React from 'react';
import { useContext } from 'react';
import { settingText, StoreContext } from './StoreContext';

type Props = {
    brandName?: string;
    brandLogo?: string;
    cartCount?: number;
    announcement?: string;
};

export default function StoreHeader({ brandName = 'Isimbi Mart', brandLogo = '', cartCount = 0, announcement }: Props) {
    const shared = useContext(StoreContext);
    const logo = brandLogo || shared.brandLogo || '';
    const displayedCartCount = shared.cartCount ?? cartCount;
    const deliveryAnnouncement = settingText(announcement || shared.deliveryInformation, 'Thoughtfully selected bags for everyday life');
    const cartPreview = shared.cartPreview || [];
    const subtotal = cartPreview.reduce((sum, item) => sum + item.price_rwf * item.quantity, 0);

    return (
        <>
            <div className="bg-[var(--charcoal)] px-4 py-2 text-center text-[0.68rem] font-semibold uppercase tracking-[0.13em] text-white">
                {deliveryAnnouncement}
            </div>
            <header className="sticky top-0 z-40 border-b border-[var(--hairline)] bg-[var(--surface)] shadow-[0_4px_16px_rgba(41,35,31,0.06)]">
                <div className="mx-auto grid max-w-[90rem] grid-cols-[1fr_auto] items-center gap-x-5 gap-y-3 px-4 py-3 sm:px-6 lg:grid-cols-[auto_1fr_minmax(12rem,18rem)_auto] lg:gap-x-8 lg:px-10">
                    <a href="/" aria-label={`${brandName} home`} className="text-[var(--chocolate)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4">
                        {logo
                            ? <img src={logo} alt={brandName} width={240} height={80} className="max-h-14 w-auto max-w-[8rem] object-contain sm:max-w-[9rem]" />
                            : brandName}
                    </a>
                    <nav aria-label="Main navigation" className="order-3 col-span-2 flex flex-wrap items-center justify-center gap-x-5 gap-y-1 text-[0.68rem] font-semibold uppercase tracking-[0.1em] lg:order-none lg:col-span-1 lg:justify-center lg:gap-x-7">
                        <a href="/" className="min-h-10 content-center hover:text-[var(--chocolate-soft)] focus-visible:outline focus-visible:outline-2">Home</a>
                        <a href="/shop" className="min-h-10 content-center hover:text-[var(--chocolate-soft)] focus-visible:outline focus-visible:outline-2">Shop</a>
                        {shared.storeCategories && shared.storeCategories.length > 0 && (
                            <details className="group relative">
                                <summary className="min-h-10 cursor-pointer list-none content-center hover:text-[var(--chocolate-soft)] focus-visible:outline focus-visible:outline-2">
                                    Collections <span aria-hidden="true" className="ml-1 text-[var(--muted)]">⌄</span>
                                </summary>
                                <div className="absolute left-1/2 top-full z-40 mt-1 min-w-52 -translate-x-1/2 border border-[var(--hairline)] bg-white p-2 shadow-lg">
                                    {shared.storeCategories.map((category) => (
                                        <a key={category.id} href={`/shop?category=${encodeURIComponent(category.slug)}`} className="block min-h-11 content-center px-3 text-xs tracking-normal hover:bg-[var(--chocolate-wash)]">
                                            {category.name}
                                        </a>
                                    ))}
                                </div>
                            </details>
                        )}
                        <a href="/order/lookup" className="min-h-10 content-center whitespace-nowrap hover:text-[var(--chocolate-soft)] focus-visible:outline focus-visible:outline-2">Track order</a>
                    </nav>
                    <form action="/shop" method="get" role="search" className="col-span-2 row-start-2 flex min-h-10 items-center border border-[var(--hairline)] bg-[var(--ivory)] px-3 lg:col-span-1 lg:col-start-3 lg:row-start-1">
                        <label htmlFor="store-search" className="sr-only">Search bags</label>
                        <input id="store-search" type="search" name="search" placeholder="Search bags" className="min-w-0 flex-1 border-0 bg-transparent text-sm outline-none placeholder:text-[var(--muted)] focus-visible:outline-none" />
                        <button type="submit" aria-label="Search products" className="min-h-9 px-2 text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)] hover:text-[var(--chocolate-soft)]">Search</button>
                    </form>
                    <details className="relative justify-self-end" onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            event.currentTarget.open = false;
                            event.currentTarget.querySelector('summary')?.focus();
                        }
                    }}>
                        <summary className="flex min-h-10 cursor-pointer list-none items-center gap-2 border border-[var(--chocolate)] px-3 text-[0.68rem] font-semibold uppercase tracking-wider text-[var(--chocolate)] hover:bg-[var(--chocolate-wash)] focus-visible:outline focus-visible:outline-2" aria-label={`Shopping bag, ${displayedCartCount} items`}>
                            Bag <span aria-hidden="true">({displayedCartCount})</span>
                        </summary>
                        <aside role="dialog" aria-label="Your shopping bag" className="fixed right-0 top-0 z-50 flex h-dvh w-full max-w-md flex-col border-l border-[var(--hairline)] bg-[var(--ivory)] shadow-xl">
                            <div className="flex items-center justify-between border-b border-[var(--hairline)] bg-[var(--surface)] p-5">
                                <h2 className="font-serif text-2xl text-[var(--chocolate)]">Your bag ({displayedCartCount})</h2>
                                <button type="button" className="min-h-11 px-3 text-sm underline" onClick={(event) => {
                                    const details = event.currentTarget.closest('details');
                                    if (details) {
                                        details.open = false;
                                        details.querySelector('summary')?.focus();
                                    }
                                }}>Close</button>
                            </div>
                            {cartPreview.length > 0 ? (
                                <div className="flex-1 overflow-y-auto p-5">
                                    {cartPreview.map((item, index) => (
                                    <div key={`${item.slug}-${index}`} className="flex gap-4 border-b border-[var(--hairline)] py-4">
                                        {item.image && <img src={item.image} alt={item.image_alt || ''} width={64} height={76} loading="lazy" className="aspect-[4/4.7] w-16 shrink-0 object-cover" />}
                                        <div className="min-w-0 flex-1">
                                            <a href={`/products/${item.slug}`} className="font-semibold underline underline-offset-2">{item.name}</a>
                                            <p className="mt-1 text-sm text-[var(--muted)]">{item.variant_name} · Qty {item.quantity}</p>
                                        </div>
                                        <strong className="shrink-0 text-[var(--chocolate)]">RWF {new Intl.NumberFormat('en').format(item.price_rwf * item.quantity)}</strong>
                                        </div>
                                    ))}
                                </div>
                            ) : <p className="flex-1 p-5 text-[var(--muted)]">Your bag is empty.</p>}
                            <div className="space-y-3 border-t border-[var(--hairline)] bg-[var(--surface)] p-5">
                                <p className="flex justify-between"><span>Subtotal</span><strong>RWF {new Intl.NumberFormat('en').format(subtotal)}</strong></p>
                                <p className="text-sm text-[var(--muted)]">Delivery details are shown at checkout.</p>
                                <a href="/cart" className="block min-h-12 border border-[var(--chocolate)] px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)] hover:bg-[var(--chocolate-wash)]">View bag</a>
                                {cartPreview.length > 0
                                    ? <a href="/checkout" className="store-primary-button w-full">Checkout</a>
                                    : <span aria-disabled="true" className="block min-h-14 cursor-not-allowed bg-[var(--chocolate-wash)] px-5 py-4 text-center text-xs font-bold uppercase tracking-widest text-[var(--muted)]">Checkout</span>}
                            </div>
                        </aside>
                    </details>
                </div>
            </header>
        </>
    );
}
