import React from 'react';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';
import StatusMessages from '../../Components/StatusMessages';
import StoreProductCard from '../../Components/StoreProductCard';

export default function ShopIndex({ products, categories = [], filters = {}, brandName, cartCount, contactEmail, contactPhone, flash, errors }: any) {
    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />
            <StatusMessages flash={flash} errors={errors} />

            <main className="mx-auto max-w-[90rem] px-4 py-8 sm:px-6 sm:py-12 lg:px-10">
                <header className="mb-8 border-b border-[var(--hairline)] pb-7">
                    <p className="store-kicker">Browse all styles</p>
                    <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
                        <h1 className="store-section-title text-[var(--chocolate)]">The collection</h1>
                        <p className="text-sm text-[var(--muted)]">{products?.total ?? products?.data?.length ?? 0} pieces</p>
                    </div>
                </header>
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-10">
                    <aside className="border-b border-[var(--hairline)] pb-5 lg:border-b-0 lg:pb-0">
                        <h2 className="store-kicker mb-4">Categories</h2>
                        <div className="flex flex-wrap gap-2 lg:flex-col lg:items-start lg:gap-1">
                            <a href="/shop" aria-current={!filters.category ? 'page' : undefined} className={`min-h-10 content-center px-3 text-sm text-[var(--chocolate)] hover:bg-[var(--chocolate-wash)] lg:px-0 lg:hover:bg-transparent ${!filters.category ? 'font-semibold underline underline-offset-4' : ''}`}>All bags</a>
                            {categories?.map((cat: any) => (
                                <a key={cat.id} href={`/shop?category=${cat.slug}`} aria-current={filters.category === cat.slug ? 'page' : undefined} className={`min-h-10 content-center px-3 text-sm text-[var(--chocolate)] hover:bg-[var(--chocolate-wash)] lg:px-0 lg:hover:bg-transparent ${filters.category === cat.slug ? 'font-semibold underline underline-offset-4' : ''}`}>
                                    {cat.name}
                                </a>
                            ))}
                        </div>
                    </aside>
                    <div className="min-w-0">
                        <form action="/shop" method="get" className="mb-7 grid grid-cols-2 items-end gap-3 border border-[var(--hairline)] bg-white p-4 sm:grid-cols-3 xl:grid-cols-6">
                            {filters.category && <input type="hidden" name="category" value={filters.category} />}
                            <label className="col-span-2 min-w-0 text-xs font-semibold uppercase tracking-wider sm:col-span-3 xl:col-span-2">Search
                                <input type="search" name="search" defaultValue={filters.search || ''} placeholder="Find a bag" className="store-input mt-1 text-sm font-normal normal-case tracking-normal" />
                            </label>
                            <label className="text-xs font-semibold uppercase tracking-wider">Sort
                                <select id="sort" name="sort" defaultValue={filters.sort || 'featured'} className="store-input mt-1 px-2 text-sm font-normal normal-case tracking-normal">
                                    <option value="featured">Featured</option>
                                    <option value="newest">Newest</option>
                                    <option value="price_low">Price: low to high</option>
                                    <option value="price_high">Price: high to low</option>
                                </select>
                            </label>
                            <label htmlFor="price_min" className="text-xs font-semibold uppercase tracking-wider">Min RWF
                                <input id="price_min" type="number" min={0} name="price_min" defaultValue={filters.price_min || ''} className="store-input mt-1 px-2 text-sm font-normal tracking-normal" />
                            </label>
                            <label htmlFor="price_max" className="text-xs font-semibold uppercase tracking-wider">Max RWF
                                <input id="price_max" type="number" min={0} name="price_max" defaultValue={filters.price_max || ''} className="store-input mt-1 px-2 text-sm font-normal tracking-normal" />
                            </label>
                            <label className="col-span-2 flex min-h-11 items-center gap-2 text-sm sm:col-span-1">
                                <input type="checkbox" name="in_stock" value="1" defaultChecked={filters.in_stock === '1' || filters.in_stock === true} />
                                In stock only
                            </label>
                            <button type="submit" className="min-h-11 bg-[var(--chocolate)] px-4 text-xs font-semibold uppercase tracking-wider text-white hover:bg-[var(--chocolate-soft)]">Apply</button>
                            <a href={filters.category ? `/shop?category=${encodeURIComponent(filters.category)}` : '/shop'} className="col-span-2 min-h-11 content-center text-center text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)] underline underline-offset-4 sm:col-span-3 xl:col-span-6 xl:text-right">Clear filters</a>
                        </form>
                        <div className="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 xl:grid-cols-3">
                            {products?.data?.map((product: any) => (
                                <StoreProductCard key={product.id} product={product} />
                            ))}
                        </div>
                        {products?.data?.length === 0 && (
                            <p className="border border-[var(--hairline)] bg-white p-8 text-center text-[var(--muted)]">No products match your search yet.</p>
                        )}
                        {products?.links?.length > 3 && (
                            <nav aria-label="Pagination" className="mt-10 flex flex-wrap justify-center gap-2 border-t border-[var(--hairline)] pt-6">
                                {products.links.map((link: any, index: number) => (
                                    <a key={index} href={link.url || '#'} aria-current={link.active ? 'page' : undefined} className={`grid min-h-10 min-w-10 place-items-center border border-[var(--hairline)] px-3 text-sm ${link.active ? 'border-[var(--chocolate)] bg-[var(--chocolate)] text-white' : 'bg-white'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}>
                                        {link.label.replace(/<[^>]*>/g, '').replaceAll('&laquo;', '«').replaceAll('&raquo;', '»')}
                                    </a>
                                ))}
                            </nav>
                        )}
                    </div>
                </div>
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
