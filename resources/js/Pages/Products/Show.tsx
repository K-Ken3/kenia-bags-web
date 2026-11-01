import React from 'react';
import StatusMessages from '../../Components/StatusMessages';
import StoreBagIllustration from '../../Components/StoreBagIllustration';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';
import StoreProductCard from '../../Components/StoreProductCard';
import { addCartForm, settingText, StoreContext } from '../../Components/StoreContext';

export default function ProductShow({ product, related = [], flash, errors, brandName, cartCount, contactEmail, contactPhone, whatsappNumber, deliveryInformation, returnsInformation }: any) {
    const shared = React.useContext(StoreContext);
    const demoProductImages = shared.demoProductImages || [];
    const [selectedVariantId, setSelectedVariantId] = React.useState(String(product.variants?.[0]?.id || ''));
    const [quantity, setQuantity] = React.useState(1);
    const [selectedImageIndex, setSelectedImageIndex] = React.useState(0);
    const [isAdding, setIsAdding] = React.useState(false);
    const [cartMessage, setCartMessage] = React.useState('');
    const [cartError, setCartError] = React.useState('');
    const selectedVariant = product.variants?.find((variant: any) => String(variant.id) === selectedVariantId);
    const selectedImage = product.images?.[selectedImageIndex] || product.images?.[0];
    const available = selectedVariant ? Math.max(0, selectedVariant.stock_quantity - selectedVariant.reserved) : 0;
    const price = selectedVariant?.price_rwf ?? product.price_rwf;
    const whatsappDigits = String(whatsappNumber || '').replace(/\D/g, '');
    const whatsappLink = whatsappDigits.length >= 8
        ? `https://wa.me/${whatsappDigits}?text=${encodeURIComponent(`I am interested in ${product.name}`)}`
        : null;

    const handleAddToCart = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setIsAdding(true);
        setCartMessage('');
        setCartError('');

        try {
            const result = await addCartForm(event.currentTarget);
            shared.setCartCount?.(result.cartCount);
            shared.setCartPreview?.(result.cartPreview);
            setCartMessage(result.success);
        } catch (error) {
            setCartError(error instanceof Error ? error.message : 'Unable to add this item to your bag.');
        } finally {
            setIsAdding(false);
        }
    };

    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />

            <StatusMessages flash={flash} errors={errors} />
            <main className="mx-auto max-w-[90rem] px-4 py-8 sm:px-6 sm:py-12 lg:px-10">
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">
                    <div>
                        {selectedImage?.url || demoProductImages.length > 0
                            ? <img src={selectedImage?.url || demoProductImages[(Math.abs(product.id) - 1) % demoProductImages.length]} alt={selectedImage?.alt_text || (selectedImage?.url ? product.name : `Temporary demo bag photo for ${product.name}`)} width={1000} height={1100} fetchPriority="high" className="aspect-[4/4.7] w-full bg-[var(--sand)] object-cover" />
                            : <div className="store-category-art grid aspect-[4/4.7] place-items-center"><StoreBagIllustration className="h-[82%] w-[82%]" detail={product.id} /></div>}
                        {product.images?.length > 1 && (
                            <div className="mt-3 flex flex-wrap gap-3" aria-label="Product images">
                                {product.images.map((image: any, index: number) => (
                                    <button key={image.id} type="button" aria-label={`View image ${index + 1} of ${product.name}`} aria-pressed={selectedImageIndex === index} onClick={() => setSelectedImageIndex(index)} className={`border p-1 ${selectedImageIndex === index ? 'border-[var(--chocolate)]' : 'border-[var(--hairline)]'}`}>
                                        <img src={image.url} alt="" width={72} height={72} loading="lazy" className="h-16 w-16 object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                    <div>
                        {product.category && <p className="store-kicker mb-3 text-[var(--chocolate-soft)]">{product.category.name}</p>}
                        <h1 className="store-section-title text-4xl text-[var(--chocolate)] sm:text-5xl">{product.name}</h1>
                        <p className="mt-5 text-2xl font-medium text-[var(--chocolate)]">RWF {new Intl.NumberFormat('en').format(Number(price))}</p>
                        <p aria-live="polite" className="mt-2 text-sm text-[var(--muted)]">{available > 0 ? `${available} available` : 'Currently out of stock'}</p>
                        <p className="mb-6 mt-5 max-w-xl leading-7 text-[var(--muted)]">{product.description}</p>
                        <form action="/cart/add" method="post" onSubmit={handleAddToCart}>
                            <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                            {product.variants?.length > 0 ? (
                                <fieldset className="mb-6 border-y border-[var(--hairline)] py-5">
                                    <legend className="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Choose a colour or option</legend>
                                    <input type="hidden" name="variant_id" value={selectedVariantId} />
                                    <div className="flex flex-wrap gap-2" role="group" aria-label="Product options">
                                        {product.variants.map((variant: any) => {
                                            const inStock = variant.stock_quantity > variant.reserved;
                                            const selected = String(variant.id) === selectedVariantId;
                                            return (
                                                <button key={variant.id} type="button" aria-pressed={selected} disabled={!inStock} onClick={() => { setSelectedVariantId(String(variant.id)); setQuantity(1); }} className={`min-h-11 border px-4 text-sm ${selected ? 'border-[var(--chocolate)] bg-[var(--chocolate)] text-white' : 'border-[var(--hairline)] bg-white text-[var(--charcoal)]'} disabled:cursor-not-allowed disabled:opacity-50`}>
                                                    {variant.colour || variant.name}{variant.price_rwf !== product.price_rwf ? ` — RWF ${variant.price_rwf}` : ''}{!inStock ? ' (out of stock)' : ''}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </fieldset>
                            ) : <p className="text-sm text-red-700">No purchase options are available for this product.</p>}
                            <div className="mb-4">
                                <label htmlFor="quantity" className="store-kicker mb-2 block">Quantity</label>
                                <div className="flex items-center gap-2">
                                    <button type="button" aria-label="Decrease quantity" disabled={quantity <= 1} onClick={() => setQuantity((value) => Math.max(1, value - 1))} className="min-h-11 min-w-11 border border-[var(--hairline)] bg-white text-[var(--chocolate)] disabled:opacity-50">−</button>
                                    <input id="quantity" type="number" name="quantity" value={quantity} onChange={(event) => setQuantity(Math.min(available || 1, Math.max(1, Number(event.target.value) || 1)))} min={1} max={Math.min(99, available)} className="min-h-12 w-20 border border-[var(--hairline)] bg-white px-3 py-2 text-center" />
                                    <button type="button" aria-label="Increase quantity" disabled={quantity >= Math.min(99, available)} onClick={() => setQuantity((value) => Math.min(99, available, value + 1))} className="min-h-11 min-w-11 border border-[var(--hairline)] bg-white text-[var(--chocolate)] disabled:opacity-50">+</button>
                                </div>
                            </div>
                            <button type="submit" disabled={!available || isAdding} className="store-primary-button w-full disabled:cursor-not-allowed disabled:opacity-50">
                                {isAdding ? 'Adding...' : available ? 'Add to Cart' : 'Out of stock'}
                            </button>
                            {(cartMessage || cartError) && (
                                <p className="mt-3 text-sm" role={cartError ? 'alert' : 'status'} aria-live={cartError ? 'assertive' : 'polite'}>
                                    {cartError || cartMessage}
                                </p>
                            )}
                        </form>
                    </div>
                </div>
                <section className="mt-14 max-w-3xl space-y-3">
                    <details className="border-t border-[var(--hairline)] py-4">
                        <summary className="cursor-pointer font-medium">Details</summary>
                        <p className="mt-3 text-[var(--muted)]">{product.description || 'Product details will be added soon.'}</p>
                    </details>
                    <details className="border-t border-[var(--hairline)] py-4">
                        <summary className="cursor-pointer font-medium">Delivery and payment</summary>
                        <p className="mt-3 text-[var(--muted)]">{settingText(deliveryInformation, 'Delivery details are being confirmed by the store.')} Payment methods available at checkout are shown only when enabled by the store.</p>
                    </details>
                    <details className="border-y border-[var(--hairline)] py-4">
                        <summary className="cursor-pointer font-medium">Returns</summary>
                        <p className="mt-3 text-[var(--muted)]">{settingText(returnsInformation, 'Returns details have not been confirmed yet.')}</p>
                    </details>
                </section>
                {whatsappLink && <a href={whatsappLink} target="_blank" rel="noreferrer" className="mt-6 inline-flex min-h-11 items-center underline underline-offset-4">Ask about this bag on WhatsApp</a>}
                {related.length > 0 && (
                    <section className="mt-16">
                        <h2 className="store-section-title text-[var(--chocolate)]">You may also like</h2>
                        <div className="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-4">
                            {related.map((item: any) => (
                                <StoreProductCard key={item.id} product={item} />
                            ))}
                        </div>
                    </section>
                )}
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
