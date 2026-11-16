import React from 'react';
import StoreBagIllustration from './StoreBagIllustration';
import { addCartForm, StoreContext } from './StoreContext';

type Product = {
    id: number;
    slug: string;
    name: string;
    price_rwf: number | string;
    is_featured?: boolean;
    category?: { name: string } | null;
    images?: { url?: string; path?: string; alt_text?: string | null }[];
    variants?: {
        id: number;
        name: string;
        colour?: string | null;
        price_rwf: number | string;
        stock_quantity: number;
        reserved: number;
    }[];
};

type Props = {
    product: Product;
    priority?: boolean;
};

export default function StoreProductCard({ product, priority = false }: Props) {
    const shared = React.useContext(StoreContext);
    const image = product.images?.[0];
    const availableVariants = product.variants || [];
    const [selectedVariantId, setSelectedVariantId] = React.useState(
        String(availableVariants.find((variant) => variant.stock_quantity > variant.reserved)?.id || availableVariants[0]?.id || ''),
    );
    const [isAdding, setIsAdding] = React.useState(false);
    const [justAdded, setJustAdded] = React.useState(false);
    const [cartMessage, setCartMessage] = React.useState('');
    const [cartError, setCartError] = React.useState('');
    const addedTimer = React.useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const selectedVariant = availableVariants.find((variant) => String(variant.id) === selectedVariantId);
    const available = selectedVariant ? selectedVariant.stock_quantity > selectedVariant.reserved : false;
    const demoImage = image?.url
        ? null
        : shared.demoProductImages?.[(Math.abs(product.id) - 1) % shared.demoProductImages.length];
    const price = new Intl.NumberFormat('en').format(Number(selectedVariant?.price_rwf ?? product.price_rwf));

    React.useEffect(() => () => clearTimeout(addedTimer.current), []);

    const handleAddToCart = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setIsAdding(true);
        setCartMessage('');
        setCartError('');
        setJustAdded(false);

        try {
            const result = await addCartForm(event.currentTarget);
            shared.setCartCount?.(result.cartCount);
            shared.setCartPreview?.(result.cartPreview);
            setCartMessage(result.success);
            setJustAdded(true);
            clearTimeout(addedTimer.current);
            addedTimer.current = setTimeout(() => setJustAdded(false), 1600);
        } catch (error) {
            setCartError(error instanceof Error ? error.message : 'Unable to add this item to your bag.');
        } finally {
            setIsAdding(false);
        }
    };

    return (
        <article className="store-product-card group min-w-0">
            <div className="store-product-media relative">
                <a href={`/products/${product.slug}`} className="block h-full" aria-label={`More info about ${product.name}`}>
                    {image?.url || demoImage ? (
                        <img
                            src={image?.url || demoImage || ''}
                            alt={image?.alt_text || (demoImage ? `Temporary demo bag photo for ${product.name}` : product.name)}
                            width={600}
                            height={700}
                            loading={priority ? 'eager' : 'lazy'}
                            fetchPriority={priority ? 'high' : 'auto'}
                        />
                    ) : (
                        <div className="store-category-art grid h-full place-items-center">
                            <StoreBagIllustration className="h-[78%] w-[78%] transition-transform duration-300 group-hover:scale-[1.025]" detail={product.id} />
                        </div>
                    )}
                </a>
                {product.is_featured && (
                    <span className="store-pill absolute left-3 top-3 border-[var(--beige)] bg-[var(--surface)] shadow-[var(--shadow-soft)]">
                        Selected
                    </span>
                )}
            </div>
            <div className="flex items-start justify-between gap-3 pt-3.5">
                <div className="min-w-0">
                    {product.category?.name && <p className="mb-1.5 text-[0.62rem] uppercase tracking-[0.14em] text-[var(--muted)]">{product.category.name}</p>}
                    <h3 className="text-[0.95rem] font-semibold leading-snug tracking-tight text-[var(--charcoal)] group-hover:text-[var(--chocolate-soft)]">
                        <a href={`/products/${product.slug}`}>{product.name}</a>
                    </h3>
                </div>
                <p className="shrink-0 text-sm font-semibold text-[var(--chocolate)]">RWF {price}</p>
            </div>
            <form action="/cart/add" method="post" className="mt-3" onSubmit={handleAddToCart}>
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                {availableVariants.length > 1 && (
                    <label className="mb-2 block">
                        <span className="sr-only">Choose an option for {product.name}</span>
                        <select
                            name="variant_id"
                            value={selectedVariantId}
                            onChange={(event) => setSelectedVariantId(event.target.value)}
                            className="store-input min-h-10 py-2 text-xs"
                        >
                            {availableVariants.map((variant) => (
                                <option key={variant.id} value={variant.id} disabled={variant.stock_quantity <= variant.reserved}>
                                    {variant.colour || variant.name}{variant.stock_quantity <= variant.reserved ? ' (out of stock)' : ''}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
                {availableVariants.length === 1 && <input type="hidden" name="variant_id" value={availableVariants[0].id} />}
                <input type="hidden" name="quantity" value="1" />
                <div className="store-product-actions">
                    <button type="submit" disabled={!available || isAdding} className={`store-product-action store-product-add disabled:cursor-not-allowed disabled:opacity-50${justAdded ? ' is-added' : ''}`}>
                        {isAdding ? 'Adding...' : justAdded ? 'Added ✓' : available ? 'Add to cart' : 'Out of stock'}
                    </button>
                    <a href={`/products/${product.slug}`} className="store-product-action store-product-info">More info</a>
                </div>
                {(cartMessage || cartError) && (
                    <p className="mt-2 text-xs" role={cartError ? 'alert' : 'status'} aria-live={cartError ? 'assertive' : 'polite'}>
                        {cartError || cartMessage}
                    </p>
                )}
            </form>
        </article>
    );
}
