import React from 'react';
import { useContext } from 'react';
import StoreBagIllustration from './StoreBagIllustration';
import { settingText, StoreContext, type StoreCartItem } from './StoreContext';

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
    const [bagOpen, setBagOpen] = React.useState(false);
    const [collectionsOpen, setCollectionsOpen] = React.useState(false);
    const [scrolled, setScrolled] = React.useState(false);
    const [removingKey, setRemovingKey] = React.useState<number | null>(null);
    const [removeError, setRemoveError] = React.useState('');
    const bagTriggerRef = React.useRef<HTMLButtonElement>(null);
    const bagPanelRef = React.useRef<HTMLElement>(null);
    const collectionsRef = React.useRef<HTMLDivElement>(null);
    const collectionsButtonRef = React.useRef<HTMLButtonElement>(null);
    const restoreBagFocusRef = React.useRef(false);
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    React.useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    React.useEffect(() => {
        if (!bagOpen) return;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        bagPanelRef.current?.querySelector<HTMLElement>('[data-bag-close]')?.focus();

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setBagOpen(false);
                bagTriggerRef.current?.focus();
            }
        };

        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = previousOverflow;
        };
    }, [bagOpen]);

    React.useEffect(() => {
        if (!restoreBagFocusRef.current) return;

        restoreBagFocusRef.current = false;
        const panel = bagPanelRef.current;
        const target = panel?.querySelector<HTMLElement>('.store-bag-remove')
            ?? panel?.querySelector<HTMLElement>('[data-bag-close]');
        target?.focus();
    }, [cartPreview.length]);

    const closeBag = () => {
        setBagOpen(false);
        bagTriggerRef.current?.focus();
    };

    const removeItem = async (variantId: number) => {
        setRemovingKey(variantId);
        setRemoveError('');

        try {
            const response = await fetch(`/cart/${variantId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => null) as {
                cartCount?: number;
                cartPreview?: StoreCartItem[];
                message?: string;
            } | null;

            if (!response.ok) {
                throw new Error(payload?.message || 'Unable to remove this item.');
            }

            shared.setCartCount?.(payload?.cartCount ?? 0);
            shared.setCartPreview?.(Array.isArray(payload?.cartPreview) ? payload.cartPreview : []);
            restoreBagFocusRef.current = true;
        } catch (error) {
            setRemoveError(error instanceof Error ? error.message : 'Unable to remove this item.');
        } finally {
            setRemovingKey(null);
        }
    };

    return (
        <>
            <div className="store-announcement bg-[var(--announcement)] px-4 py-2.5 text-center text-[0.66rem] font-semibold uppercase tracking-[0.16em] text-[#f8f3eb]">
                {deliveryAnnouncement}
            </div>
            <header className={`store-header sticky top-0 z-40 border-b border-[var(--hairline)] bg-[var(--ivory)]${scrolled ? ' is-scrolled' : ''}`}>
                <div className="mx-auto grid max-w-[90rem] grid-cols-[1fr_auto] items-center gap-x-5 gap-y-3 px-4 py-3 sm:px-6 lg:grid-cols-[auto_1fr_minmax(12rem,18rem)_auto] lg:gap-x-8 lg:px-10">
                    <a href="/" aria-label={`${brandName} home`} className="font-serif text-2xl tracking-tight text-[var(--chocolate)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4">
                        {logo
                            ? <img src={logo} alt={brandName} width={240} height={80} className="max-h-14 w-auto max-w-[8rem] object-contain sm:max-w-[9rem]" />
                            : brandName}
                    </a>
                    <nav aria-label="Main navigation" className="order-3 col-span-2 flex flex-wrap items-center justify-center gap-x-5 gap-y-1 text-[0.68rem] font-semibold uppercase tracking-[0.1em] lg:order-none lg:col-span-1 lg:justify-center lg:gap-x-7">
                        <a href="/" className="store-nav-link min-h-10 content-center hover:text-[var(--chocolate-soft)] focus-visible:outline focus-visible:outline-2">Home</a>
                        <a href="/shop" className="store-nav-link min-h-10 content-center hover:text-[var(--chocolate-soft)] focus-visible:outline focus-visible:outline-2">Shop</a>
                        {shared.storeCategories && shared.storeCategories.length > 0 && (
                            <div
                                ref={collectionsRef}
                                className="relative"
                                onMouseEnter={() => setCollectionsOpen(true)}
                                onMouseLeave={() => {
                                    if (!collectionsRef.current?.contains(document.activeElement)) setCollectionsOpen(false);
                                }}
                                onFocus={() => setCollectionsOpen(true)}
                                onBlur={(event) => {
                                    if (!event.currentTarget.contains(event.relatedTarget as Node | null)) setCollectionsOpen(false);
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        setCollectionsOpen(false);
                                        collectionsButtonRef.current?.focus();
                                    }
                                }}
                            >
                                <button
                                    ref={collectionsButtonRef}
                                    type="button"
                                    aria-expanded={collectionsOpen}
                                    aria-controls="store-collections-menu"
                                    className="store-nav-link flex min-h-10 items-center gap-1.5 hover:text-[var(--chocolate-soft)] focus-visible:outline focus-visible:outline-2"
                                    onClick={() => setCollectionsOpen((open) => !open)}
                                >
                                    Collections
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" strokeWidth="2.1" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className={`text-[var(--muted)] transition-transform duration-300 ${collectionsOpen ? 'rotate-180' : ''}`}>
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                                {collectionsOpen && (
                                    <div id="store-collections-menu" className="store-nav-menu absolute left-1/2 top-full z-40 w-56 -translate-x-1/2 pt-2">
                                        <div className="rounded-2xl border border-[var(--hairline)] bg-[var(--surface)] p-2 shadow-[var(--shadow-soft)]">
                                            {shared.storeCategories.map((category) => (
                                                <a key={category.id} href={`/shop?category=${encodeURIComponent(category.slug)}`} className="block min-h-11 content-center rounded-xl px-3 text-xs tracking-normal hover:bg-[var(--primary-tint)] hover:text-[var(--primary-hover)]">
                                                    {category.name}
                                                </a>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                        <a href="/order/lookup" className="store-nav-link min-h-10 content-center whitespace-nowrap hover:text-[var(--chocolate-soft)] focus-visible:outline focus-visible:outline-2">Track order</a>
                    </nav>
                    <form action="/shop" method="get" role="search" className="store-search col-span-2 row-start-2 flex min-h-10 items-center rounded-full border border-[var(--hairline)] bg-[var(--paper)] pl-4 pr-1.5 transition-colors focus-within:border-[var(--primary)] lg:col-span-1 lg:col-start-3 lg:row-start-1">
                        <label htmlFor="store-search" className="sr-only">Search bags</label>
                        <input id="store-search" type="search" name="search" placeholder="Search bags" className="store-search-input min-w-0 flex-1 border-0 bg-transparent py-2 text-sm outline-none placeholder:text-[var(--muted)]" />
                        <button type="submit" aria-label="Search products" className="store-search-submit flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[var(--chocolate)] transition-colors hover:bg-[var(--primary-tint)] hover:text-[var(--primary)]">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" />
                                <path d="m16 16 4.5 4.5" />
                            </svg>
                        </button>
                    </form>
                    <button
                        ref={bagTriggerRef}
                        type="button"
                        className="store-bag-trigger flex min-h-10 cursor-pointer list-none items-center gap-2 justify-self-end rounded-full border border-[var(--hairline)] bg-[var(--paper)] px-4 text-[0.68rem] font-semibold uppercase tracking-wider text-[var(--chocolate)] hover:border-[var(--beige)] hover:bg-[var(--primary-tint)] focus-visible:outline focus-visible:outline-2"
                        aria-label={`Shopping bag, ${displayedCartCount} items`}
                        aria-expanded={bagOpen}
                        aria-controls="store-bag-panel"
                        onClick={() => {
                            setRemoveError('');
                            setBagOpen(true);
                        }}
                    >
                        Bag <span aria-hidden="true" key={displayedCartCount} className="store-cart-count">{displayedCartCount}</span>
                    </button>
                </div>
            </header>
            {bagOpen && (
                <>
                    <button type="button" aria-label="Close shopping bag" className="store-bag-backdrop" onClick={closeBag} tabIndex={-1} />
                    <aside
                        id="store-bag-panel"
                        ref={bagPanelRef}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Your shopping bag"
                        className="store-bag-panel fixed right-0 top-0 z-50 flex h-dvh w-full max-w-md flex-col overflow-hidden rounded-l-[24px] border-l border-[var(--hairline)] bg-[var(--ivory)] shadow-2xl"
                        onKeyDown={(event) => {
                            if (event.key !== 'Tab' || !bagPanelRef.current) return;
                            const focusable = Array.from(bagPanelRef.current.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), summary'));
                            if (focusable.length === 0) return;
                            const first = focusable[0];
                            const last = focusable[focusable.length - 1];
                            if (event.shiftKey && document.activeElement === first) {
                                event.preventDefault();
                                last.focus();
                            } else if (!event.shiftKey && document.activeElement === last) {
                                event.preventDefault();
                                first.focus();
                            }
                        }}
                    >
                        <div className="flex items-center justify-between border-b border-[var(--hairline)] bg-[var(--surface)] p-5">
                            <h2 className="font-serif text-2xl text-[var(--chocolate)]">Your bag ({displayedCartCount})</h2>
                            <button type="button" data-bag-close className="min-h-11 rounded-full px-4 text-sm underline underline-offset-4 hover:bg-[var(--chocolate-wash)]" onClick={closeBag}>Close</button>
                        </div>
                        {cartPreview.length > 0 ? (
                            <div className="min-h-0 flex-1 overflow-y-auto p-5">
                                {cartPreview.map((item, index) => (
                                    <div key={item.variant_id} className={`store-bag-item flex gap-3.5 border-b border-[var(--hairline)] py-4 first:pt-0 last:border-b-0${removingKey === item.variant_id ? ' is-removing' : ''}`} style={{ animationDelay: `${Math.min(index, 8) * 45}ms` }}>
                                        <div className="store-bag-thumb">
                                            {item.image
                                                ? <img src={item.image} alt={item.image_alt || ''} width={72} height={85} loading="lazy" />
                                                : <StoreBagIllustration className="h-[82%] w-[82%]" detail={item.variant_id} />}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-2">
                                                <a href={`/products/${item.slug}`} className="min-w-0 break-words font-semibold leading-snug underline underline-offset-2">{item.name}</a>
                                                <button
                                                    type="button"
                                                    className="store-bag-remove"
                                                    aria-label={`Remove ${item.name} from your bag`}
                                                    title="Remove item"
                                                    disabled={removingKey !== null}
                                                    onClick={() => removeItem(item.variant_id)}
                                                >
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" aria-hidden="true">
                                                        <path d="M6 6l12 12M18 6 6 18" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <p className="mt-1 text-sm text-[var(--muted)]">{item.variant_name} · Qty {item.quantity}</p>
                                            <p className="mt-1.5 font-semibold text-[var(--chocolate)]">RWF {new Intl.NumberFormat('en').format(item.price_rwf * item.quantity)}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 overflow-y-auto p-8 text-center">
                                <StoreBagIllustration className="h-20 w-24 opacity-80" detail={1} />
                                <p className="mt-2 text-[var(--muted)]">Your bag is empty.</p>
                                <a href="/shop" className="store-primary-button mt-3">Explore the collection</a>
                            </div>
                        )}
                        <div className="space-y-3 border-t border-[var(--hairline)] bg-[var(--surface)] p-5">
                            {removeError && <p role="alert" className="border border-red-200 bg-red-50 p-2.5 text-xs text-red-800">{removeError}</p>}
                            <p className="flex justify-between"><span>Subtotal</span><strong>RWF {new Intl.NumberFormat('en').format(subtotal)}</strong></p>
                            <p className="text-sm text-[var(--muted)]">Delivery details are shown at checkout.</p>
                            <a href="/cart" className="block min-h-12 rounded-full border border-[var(--hairline)] bg-[var(--paper)] px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)] hover:border-[var(--beige)] hover:bg-[var(--chocolate-wash)]">View bag</a>
                            {cartPreview.length > 0
                                ? <a href="/checkout" className="store-primary-button w-full">Checkout</a>
                                : <span aria-disabled="true" className="block min-h-14 cursor-not-allowed rounded-full bg-[var(--chocolate-wash)] px-5 py-4 text-center text-xs font-bold uppercase tracking-widest text-[var(--muted)]">Checkout</span>}
                        </div>
                    </aside>
                </>
            )}
        </>
    );
}
