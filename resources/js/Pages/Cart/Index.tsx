import React from 'react';
import StatusMessages from '../../Components/StatusMessages';
import StoreBagIllustration from '../../Components/StoreBagIllustration';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';

export default function CartIndex({ items = [], subtotal = 0, deliveryLabel, flash, errors, brandName, cartCount, contactEmail, contactPhone }: any) {
    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />

            <StatusMessages flash={flash} errors={errors} />
            <main className="mx-auto max-w-[90rem] px-4 py-8 sm:px-6 sm:py-12 lg:px-10">
                <header className="mb-8 border-b border-[var(--hairline)] pb-6">
                    <p className="store-kicker">Your selection</p>
                    <h1 className="store-section-title mt-3 text-[var(--chocolate)]">Your bag</h1>
                </header>
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.7fr)] lg:gap-12">
                    <div className="min-w-0">
                        {items.length === 0 ? (
                            <div className="border border-[var(--hairline)] bg-white p-8 text-center">
                                <p className="text-lg">Your cart is empty.</p>
                                <a href="/shop" className="store-primary-button mt-4">Continue shopping</a>
                            </div>
                        ) : (
                            <div className="divide-y divide-[var(--hairline)] border-y border-[var(--hairline)]">
                                {items.map((item: any, index: number) => (
                                    <div key={item.key} className="flex flex-wrap gap-4 py-5 sm:flex-nowrap sm:gap-6">
                                        {item.image
                                            ? <img src={item.image} alt={item.image_alt} width={160} height={180} loading="lazy" className="aspect-[4/4.7] w-24 shrink-0 object-cover sm:w-32" />
                                            : <div className="store-category-art grid aspect-[4/4.7] w-24 shrink-0 place-items-center sm:w-32"><StoreBagIllustration className="h-[88%] w-[88%]" detail={index} /></div>}
                                        <div className="min-w-0 flex-1">
                                            <a href={`/products/${item.slug}`} className="font-bold hover:underline">{item.name}</a>
                                            {item.variant_name && <p className="mt-1 text-sm text-[var(--muted)]">{item.variant_name}</p>}
                                            <p className="mt-2 text-sm text-[var(--muted)]">RWF {new Intl.NumberFormat('en').format(Number(item.price))} each</p>
                                            <p className="mt-1 font-semibold text-[var(--chocolate)]">RWF {new Intl.NumberFormat('en').format(Number(item.line_total))}</p>
                                        </div>
                                        <form action={`/cart/${encodeURIComponent(item.key)}`} method="post" className="flex items-center gap-2 self-end sm:self-center">
                                            <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                                            <input type="hidden" name="_method" value="PATCH" />
                                            <label className="sr-only" htmlFor={`quantity-${item.key}`}>Quantity for {item.name}</label>
                                            <input id={`quantity-${item.key}`} type="number" name="quantity" min={1} max={Math.max(item.available, 1)} defaultValue={item.quantity} className="min-h-11 w-16 border border-[var(--hairline)] bg-[var(--surface)] px-2 py-2" />
                                            <button type="submit" className="min-h-11 border border-[var(--hairline)] px-3 text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)] hover:bg-[var(--chocolate-wash)]">Update</button>
                                        </form>
                                        <form action={`/cart/${encodeURIComponent(item.key)}`} method="post" className="self-end sm:self-center">
                                            <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                                            <input type="hidden" name="_method" value="DELETE" />
                                            <button type="submit" className="min-h-11 px-2 text-xs font-semibold uppercase tracking-wider text-[var(--sale)] underline">Remove</button>
                                        </form>
                                        {item.quantity > item.available && <p className="w-full border border-[var(--beige)] bg-[var(--sand)] p-3 text-sm text-[var(--chocolate)]">Only {item.available} currently available. Update quantity to continue.</p>}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="h-fit border border-[var(--hairline)] bg-white p-5 sm:p-6">
                        <h2 className="font-serif text-xl text-[var(--chocolate)]">Order summary</h2>
                        <p className="mb-4 mt-6 flex justify-between"><span>Subtotal</span><strong>RWF {new Intl.NumberFormat('en').format(Number(subtotal))}</strong></p>
                        <p className="mb-5 flex justify-between border-t border-[var(--hairline)] pt-4 text-sm text-[var(--muted)]"><span>Delivery</span><span>{deliveryLabel}</span></p>
                        {items.length > 0
                            ? <a href="/checkout" className="store-primary-button w-full">Proceed to checkout</a>
                            : <span aria-disabled="true" className="block min-h-14 w-full cursor-not-allowed bg-[var(--chocolate-wash)] px-6 py-4 text-center text-xs font-semibold uppercase tracking-widest text-[var(--muted)]">Proceed to checkout</span>}
                    </div>
                </div>
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
