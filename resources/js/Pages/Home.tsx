import React from 'react';
import StoreBagIllustration from '../Components/StoreBagIllustration';
import StoreFooter from '../Components/StoreFooter';
import StoreHeader from '../Components/StoreHeader';
import StoreProductCard from '../Components/StoreProductCard';
import StatusMessages from '../Components/StatusMessages';
import { StoreContext } from '../Components/StoreContext';

type Category = {
    id: number;
    name: string;
    slug: string;
    image_path?: string | null;
    image_url?: string | null;
};

type Product = {
    id: number;
    name: string;
    slug: string;
    price_rwf: number | string;
    images?: { url?: string; alt_text?: string | null }[];
};

type Props = {
    featured?: Product[];
    categories?: Category[];
    brandName?: string;
    cartCount?: number;
    contactEmail?: string;
    contactPhone?: string;
    deliveryInformation?: string;
    returnsInformation?: string;
    flash?: { success?: string | null; error?: string | null };
    errors?: Record<string, string[]>;
};

export default function Home({
    featured = [],
    categories = [],
    brandName,
    cartCount,
    contactEmail,
    contactPhone,
    deliveryInformation,
    returnsInformation,
    flash,
    errors,
}: Props) {
    const shared = React.useContext(StoreContext);
    const heroSlides = featured.slice(0, 4).map((product, index) => {
        const image = product.images?.[0];

        return {
            product,
            imageUrl: image?.url || shared.demoProductImages?.[index % (shared.demoProductImages.length || 1)],
            imageAlt: image?.url
                ? image.alt_text || product.name
                : `Temporary demo bag photo for ${product.name}`,
        };
    });
    const [activeHeroSlide, setActiveHeroSlide] = React.useState(0);
    const [heroPaused, setHeroPaused] = React.useState(false);
    const [prefersReducedMotion, setPrefersReducedMotion] = React.useState(false);
    const editorialCategories = categories.slice(0, 2);

    React.useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        const updateMotionPreference = () => setPrefersReducedMotion(mediaQuery.matches);

        updateMotionPreference();
        mediaQuery.addEventListener('change', updateMotionPreference);
        return () => mediaQuery.removeEventListener('change', updateMotionPreference);
    }, []);

    React.useEffect(() => {
        if (heroSlides.length < 2 || heroPaused || prefersReducedMotion) {
            return;
        }

        const interval = window.setInterval(() => {
            setActiveHeroSlide((current) => (current + 1) % heroSlides.length);
        }, 6000);

        return () => window.clearInterval(interval);
    }, [heroPaused, heroSlides.length, prefersReducedMotion]);

    const showHeroSlide = (index: number) => {
        setActiveHeroSlide((index + heroSlides.length) % heroSlides.length);
    };

    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} announcement={deliveryInformation} />
            <StatusMessages flash={flash} errors={errors} />

            <main>
                <section className="bg-white">
                    <div className="mx-auto grid max-w-[88rem] items-center gap-8 px-4 py-8 sm:px-6 sm:py-12 lg:grid-cols-[0.88fr_1.12fr] lg:gap-14 lg:px-10 lg:py-14">
                        <div className="order-2 py-3 lg:order-1 lg:py-8">
                            <p className="store-kicker text-[var(--chocolate-soft)]">A little more lovely, every day</p>
                            <h1 className="store-display mt-5 max-w-2xl text-[var(--chocolate)]">Carry your style, everywhere.</h1>
                            <p className="mt-5 max-w-lg text-base leading-7 text-[var(--muted)] sm:text-lg sm:leading-8">
                                Beautifully chosen bags and everyday essentials for the modern woman.
                            </p>
                            <div className="mt-7 flex flex-wrap gap-3">
                                <a href="/shop" className="store-primary-button">Shop the collection</a>
                                <a href="/shop?sort=newest" className="inline-flex min-h-[3.25rem] items-center border border-[var(--hairline)] px-5 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--chocolate)] hover:bg-[var(--chocolate-wash)]">
                                    Explore the shop
                                </a>
                            </div>
                            <p className="mt-8 border-t border-[var(--hairline)] pt-4 text-xs uppercase tracking-[0.14em] text-[var(--muted)]">
                                Thoughtfully selected · Prices shown in RWF
                            </p>
                        </div>
                        <div
                            className="store-hero-art group relative order-1 aspect-[1.12/1] min-h-64 overflow-hidden lg:order-2"
                            role="region"
                            aria-label="Featured products"
                            aria-roledescription="carousel"
                            onMouseEnter={() => setHeroPaused(true)}
                            onMouseLeave={() => setHeroPaused(false)}
                            onFocus={() => setHeroPaused(true)}
                            onBlur={(event) => {
                                if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
                                    setHeroPaused(false);
                                }
                            }}
                        >
                            {heroSlides.length > 0 ? heroSlides.map(({ product, imageUrl, imageAlt }, index) => {
                                const isActive = index === activeHeroSlide;

                                return (
                                    <a
                                        key={product.id}
                                        href={`/products/${product.slug}`}
                                        aria-label={`Discover ${product.name}`}
                                        aria-hidden={!isActive}
                                        tabIndex={isActive ? 0 : -1}
                                        className={`store-hero-slide group absolute inset-0 ${isActive ? 'is-active' : ''}`}
                                    >
                                        {imageUrl ? (
                                            <img
                                                src={imageUrl}
                                                alt={imageAlt}
                                                width={1000}
                                                height={900}
                                                loading="eager"
                                                fetchPriority={index === 0 ? 'high' : 'low'}
                                                className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.025]"
                                            />
                                        ) : (
                                            <div className="absolute inset-0 grid place-items-center">
                                                <StoreBagIllustration className="h-[76%] w-[76%]" detail={product.id} />
                                            </div>
                                        )}
                                        <div className="store-hero-caption absolute inset-x-0 bottom-0 flex min-h-28 items-end p-5 text-white sm:min-h-36 sm:p-7">
                                            <p className="store-hero-title font-serif text-2xl sm:text-3xl">{product.name}</p>
                                        </div>
                                    </a>
                                );
                            }) : (
                                <a href="/shop" aria-label="Explore the shop" className="absolute inset-0 grid place-items-center">
                                    <StoreBagIllustration className="h-[76%] w-[76%]" detail={1} />
                                </a>
                            )}
                            {heroSlides.length > 1 && (
                                <div className="absolute bottom-4 right-4 z-20 flex items-center gap-2 sm:bottom-6 sm:right-6" aria-label="Choose featured product">
                                    {heroSlides.map(({ product }, index) => (
                                        <button
                                            key={product.id}
                                            type="button"
                                            aria-label={`Show ${product.name}`}
                                            aria-current={index === activeHeroSlide ? 'true' : undefined}
                                            onClick={() => showHeroSlide(index)}
                                            className={`store-hero-dot ${index === activeHeroSlide ? 'is-active' : ''}`}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {categories.length > 0 && (
                    <section className="mx-auto max-w-[88rem] px-4 py-6 sm:px-6 sm:py-10 lg:px-10 lg:py-16">
                        <div className="mb-4 flex flex-wrap items-end justify-between gap-2 sm:mb-7 sm:gap-4">
                            <div>
                                <p className="store-kicker hidden sm:block">Find your favorite</p>
                                <h2 className="store-section-title mt-1 text-[var(--chocolate)] sm:mt-2">Shop by category</h2>
                            </div>
                            <a href="/shop" className="min-h-9 content-center text-[0.62rem] font-semibold uppercase tracking-wider text-[var(--chocolate)] underline underline-offset-4 sm:min-h-11 sm:text-xs">All collections</a>
                        </div>
                        <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-3 lg:gap-4">
                            {categories.map((category, index) => (
                                <a
                                    key={category.id}
                                    href={`/shop?category=${encodeURIComponent(category.slug)}`}
                                    className="group flex min-h-24 min-w-0 items-center gap-2 border border-[var(--hairline)] bg-white p-2 hover:border-[var(--beige)] hover:bg-[var(--chocolate-wash)] sm:min-h-32 sm:gap-4 sm:p-4 lg:min-h-40 lg:gap-6 lg:p-5"
                                >
                                    {shared.demoProductImages?.length ? (
                                        <img
                                            src={shared.demoProductImages[index % shared.demoProductImages.length]}
                                            alt={`Temporary demo bag photo for ${category.name}`}
                                            loading="lazy"
                                            className="aspect-square w-14 shrink-0 object-cover sm:w-20 lg:w-28"
                                        />
                                    ) : (
                                        <div className="store-category-art grid aspect-square w-14 shrink-0 place-items-center sm:w-20 lg:w-28">
                                            <StoreBagIllustration className="h-[88%] w-[88%] transition-transform duration-300 group-hover:scale-[1.04]" detail={index} />
                                        </div>
                                    )}
                                    <h3 className="min-w-0 font-serif text-[0.85rem] leading-tight text-[var(--chocolate)] sm:text-lg lg:text-2xl">{category.name}</h3>
                                </a>
                            ))}
                        </div>
                    </section>
                )}

                <section className="bg-white py-12 sm:py-16">
                    <div className="mx-auto max-w-[88rem] px-4 sm:px-6 lg:px-10">
                        <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="store-kicker">The Isimbi Mart edit</p>
                                <h2 className="store-section-title mt-2 text-[var(--chocolate)]">Chosen for everyday</h2>
                            </div>
                            <a href="/shop" className="min-h-11 content-center text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)] underline underline-offset-4">View all bags</a>
                        </div>
                        {featured.length > 0 ? (
                            <div className="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4 lg:gap-y-10">
                                {featured.slice(0, 4).map((product, index) => (
                                    <StoreProductCard key={product.id} product={product} priority={index < 2} />
                                ))}
                            </div>
                        ) : (
                            <div className="border border-[var(--hairline)] bg-[var(--ivory)] p-8 text-center">
                                <p className="text-[var(--muted)]">The collection is being prepared.</p>
                                <a href="/shop" className="mt-3 inline-block text-sm font-semibold text-[var(--chocolate)] underline underline-offset-4">Explore the shop</a>
                            </div>
                        )}
                    </div>
                </section>

                {editorialCategories.length > 0 && (
                    <section aria-label="Explore the collections" className="mx-auto grid max-w-[88rem] gap-4 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-2 lg:px-10">
                        {editorialCategories.map((category, index) => {
                            const imageUrl = category.image_url
                                || shared.demoProductImages?.[index % (shared.demoProductImages.length || 1)];

                            return (
                                <a
                                    key={category.id}
                                    href={`/shop?category=${encodeURIComponent(category.slug)}`}
                                    className={`store-promo-art group relative flex min-h-44 items-center justify-between gap-3 overflow-hidden border border-[var(--hairline)] p-4 sm:min-h-72 sm:p-8${imageUrl ? ' has-image' : ''}`}
                                >
                                    {imageUrl ? (
                                        <img
                                            src={imageUrl}
                                            alt=""
                                            aria-hidden="true"
                                            loading="lazy"
                                            className="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                        />
                                    ) : (
                                        <StoreBagIllustration className="absolute -bottom-3 -right-4 h-[92%] w-[58%] transition-transform duration-500 group-hover:scale-[1.03] sm:right-1 sm:w-[52%]" detail={index + 2} />
                                    )}
                                    <div className="relative z-10 max-w-[72%] sm:max-w-[58%]">
                                        <p className="store-kicker text-[var(--chocolate-soft)]">Explore the edit</p>
                                        <h2 className="store-editorial-title mt-2 text-[var(--chocolate)] sm:mt-3">{category.name}</h2>
                                        <p className="store-promo-description mt-2 text-sm leading-6 text-[var(--muted)] sm:mt-3">Find a piece that feels right for your everyday.</p>
                                        <span className="store-promo-link mt-3 inline-flex min-h-9 items-center border-b border-[var(--chocolate)] text-[0.62rem] font-semibold uppercase tracking-wider text-[var(--chocolate)] sm:mt-5 sm:min-h-10 sm:text-[0.65rem]">
                                            Discover the collection
                                        </span>
                                    </div>
                                </a>
                            );
                        })}
                    </section>
                )}
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
