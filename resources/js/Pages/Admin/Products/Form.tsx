import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

const emptyVariant = { name: '', colour: '', sku: '', price_rwf: '', stock_quantity: 0 };

export default function ProductForm({ categories, product, flash, errors }: any) {
    const [variants, setVariants] = React.useState(() => product?.variants?.length
        ? product.variants.map((variant: any) => ({
            id: variant.id,
            name: variant.name,
            colour: variant.colour || '',
            sku: variant.sku || '',
            price_rwf: variant.price_rwf ?? '',
            stock_quantity: variant.stock_quantity,
        }))
        : [{ name: 'Standard', colour: '', sku: '', price_rwf: '', stock_quantity: 0 }]);
    const [imagePreview, setImagePreview] = React.useState<string | null>(null);

    React.useEffect(() => () => {
        if (imagePreview) URL.revokeObjectURL(imagePreview);
    }, [imagePreview]);

    const updateVariant = (index: number, field: string, value: string) => {
        setVariants((current: any[]) => current.map((variant, variantIndex) =>
            variantIndex === index ? { ...variant, [field]: value } : variant,
        ));
    };

    const handleImageChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        setImagePreview(file ? URL.createObjectURL(file) : null);
    };

    return (
        <AdminLayout
            title={product ? `Edit ${product.name}` : 'Add product'}
            description={product ? 'Update pricing, options, imagery, and availability for this piece.' : 'Create a new piece for the Isimbi Mart catalogue.'}
            actions={<a href="/admin/products" className="admin-secondary-button">Back to products</a>}
            flash={flash}
            errors={errors}
        >
            <form action={product ? `/admin/products/${product.id}` : '/admin/products'} method="post" encType="multipart/form-data" className="space-y-6">
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                {product && <input type="hidden" name="_method" value="PUT" />}

                <section className="admin-card">
                    <div className="admin-card-head">
                        <h2 className="admin-card-title">Basics</h2>
                    </div>
                    <div className="admin-card-body">
                        <div className="admin-form-grid">
                            <label className="admin-field">Product name
                                <input name="name" required defaultValue={product?.name || ''} className="store-input" placeholder="e.g. Virunga Weekender" />
                            </label>
                            <label className="admin-field">URL slug
                                <input name="slug" required defaultValue={product?.slug || ''} className="store-input" placeholder="e.g. virunga-weekender" />
                            </label>
                            <label className="admin-field">Category
                                <select name="category_id" defaultValue={product?.category_id || ''} className="store-input">
                                    <option value="">No category</option>
                                    {categories.map((category: any) => <option key={category.id} value={category.id}>{category.name}</option>)}
                                </select>
                            </label>
                            <label className="admin-field">Price (RWF)
                                <input type="number" name="price_rwf" required min={0} defaultValue={product?.price_rwf || 0} className="store-input" />
                            </label>
                            <label className="admin-field">Status
                                <select name="status" defaultValue={product?.status || 'draft'} className="store-input">
                                    {['draft', 'active', 'archived'].map((status) => <option key={status} value={status}>{status}</option>)}
                                </select>
                            </label>
                            <label className="flex items-center gap-3 self-end rounded-2xl border border-[var(--hairline)] bg-[var(--paper)] px-4 py-3 text-sm">
                                <input type="checkbox" name="is_featured" value="1" defaultChecked={product?.is_featured} className="h-4 w-4 accent-[var(--primary)]" />
                                <span>Feature on the storefront</span>
                            </label>
                        </div>
                    </div>
                </section>

                <section className="admin-card">
                    <div className="admin-card-head">
                        <h2 className="admin-card-title">Description</h2>
                    </div>
                    <div className="admin-card-body">
                        <label className="admin-field">Product description
                            <textarea name="description" rows={4} defaultValue={product?.description || ''} className="store-input" placeholder="Materials, size, and the details that make this bag special." />
                        </label>
                    </div>
                </section>

                <section className="admin-card">
                    <div className="admin-card-head">
                        <div>
                            <h2 className="admin-card-title">Options and colours</h2>
                            <p className="mt-1 text-sm text-[var(--muted)]">Each option keeps its own price and stock.</p>
                        </div>
                        <button type="button" onClick={() => setVariants((current: any[]) => [...current, { ...emptyVariant }])} className="admin-secondary-button">Add option</button>
                    </div>
                    <div className="admin-card-body space-y-4">
                        {variants.map((variant: any, index: number) => (
                            <article key={variant.id || `new-${index}`} className="rounded-2xl border border-[var(--hairline)] bg-[var(--paper)] p-4">
                                {variant.id && <input type="hidden" name={`variants[${index}][id]`} value={variant.id} />}
                                <div className="mb-4 flex items-center justify-between gap-3">
                                    <h3 className="font-serif text-xl">Option {index + 1}</h3>
                                    {variants.length > 1 ? (
                                        <button type="button" onClick={() => setVariants((current: any[]) => current.filter((_: any, i: number) => i !== index))} className="admin-link-button text-red-800">
                                            Remove
                                        </button>
                                    ) : null}
                                </div>
                                <div className="admin-form-grid">
                                    <label className="admin-field">Option name
                                        <input name={`variants[${index}][name]`} required value={variant.name} onChange={(event) => updateVariant(index, 'name', event.target.value)} className="store-input" />
                                    </label>
                                    <label className="admin-field">Colour
                                        <input name={`variants[${index}][colour]`} value={variant.colour} onChange={(event) => updateVariant(index, 'colour', event.target.value)} className="store-input" />
                                    </label>
                                    <label className="admin-field">SKU
                                        <input name={`variants[${index}][sku]`} value={variant.sku} onChange={(event) => updateVariant(index, 'sku', event.target.value)} className="store-input" />
                                    </label>
                                    <label className="admin-field">Option price RWF
                                        <input type="number" name={`variants[${index}][price_rwf]`} min={0} value={variant.price_rwf} onChange={(event) => updateVariant(index, 'price_rwf', event.target.value)} className="store-input" placeholder="Blank uses product price" />
                                    </label>
                                    <label className="admin-field">Stock
                                        <input type="number" name={`variants[${index}][stock_quantity]`} required min={0} value={variant.stock_quantity} onChange={(event) => updateVariant(index, 'stock_quantity', event.target.value)} className="store-input" />
                                    </label>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="admin-card">
                    <div className="admin-card-head">
                        <h2 className="admin-card-title">Product image</h2>
                    </div>
                    <div className="admin-card-body space-y-5">
                        {product?.images?.length > 0 ? (
                            <div className="flex flex-wrap gap-3">
                                {product.images.map((image: any) => (
                                    <img key={image.id} src={image.url} alt={image.alt_text} width={180} height={180} loading="lazy" className="h-32 w-32 rounded-2xl border border-[var(--hairline)] object-cover" />
                                ))}
                            </div>
                        ) : null}
                        <label className="admin-field">Add image (JPEG, PNG or WebP, max 5 MB)
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" onChange={handleImageChange} className="store-input" />
                        </label>
                        {imagePreview ? (
                            <img src={imagePreview} alt="Selected product preview" width={180} height={180} className="h-32 w-32 rounded-2xl border border-[var(--hairline)] object-cover" />
                        ) : null}
                        <label className="admin-field">Image description
                            <input name="image_alt" maxLength={255} className="store-input" placeholder="Describe the image for screen readers" />
                        </label>
                    </div>
                </section>

                <div className="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--hairline)] bg-[var(--surface)] p-3 shadow-[var(--shadow-lift)]">
                    <p className="px-1 text-sm text-[var(--muted)]">Changes appear in the storefront as soon as the product is active.</p>
                    <button className="store-primary-button">Save product</button>
                </div>
            </form>
        </AdminLayout>
    );
}
