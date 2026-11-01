import React from 'react';
import AdminLayout from '../../../Components/AdminLayout';

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

    const updateVariant = (index: number, field: string, value: string) => {
        setVariants((current: any[]) => current.map((variant, variantIndex) =>
            variantIndex === index ? { ...variant, [field]: value } : variant,
        ));
    };

    return (
        <AdminLayout title={product ? `Edit ${product.name}` : 'Add product'} flash={flash} errors={errors}>
            <form action={product ? `/admin/products/${product.id}` : '/admin/products'} method="post" encType="multipart/form-data" className="max-w-3xl space-y-5 border border-stone-300 bg-[var(--surface)] p-6">
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                {product && <input type="hidden" name="_method" value="PUT" />}
                <div className="grid gap-5 sm:grid-cols-2">
                    <label>Product name<input name="name" required defaultValue={product?.name || ''} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                    <label>URL slug<input name="slug" required defaultValue={product?.slug || ''} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                    <label>Category<select name="category_id" defaultValue={product?.category_id || ''} className="mt-2 min-h-11 w-full border bg-white px-3"><option value="">No category</option>{categories.map((category: any) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
                    <label>Price (RWF)<input type="number" name="price_rwf" required min={0} defaultValue={product?.price_rwf || 0} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                    <label>Status<select name="status" defaultValue={product?.status || 'draft'} className="mt-2 min-h-11 w-full border bg-white px-3">{['draft', 'active', 'archived'].map((status) => <option key={status}>{status}</option>)}</select></label>
                    <label className="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" defaultChecked={product?.is_featured} />Featured</label>
                </div>
                <label className="block">Description<textarea name="description" rows={4} defaultValue={product?.description || ''} className="mt-2 w-full border bg-white p-3" /></label>
                <fieldset className="space-y-4 border-t border-stone-300 pt-5">
                    <legend className="font-semibold">Options and colours</legend>
                    {variants.map((variant: any, index: number) => (
                        <div key={variant.id || `new-${index}`} className="border border-stone-300 p-4">
                            {variant.id && <input type="hidden" name={`variants[${index}][id]`} value={variant.id} />}
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="font-medium">Option {index + 1}</h3>
                                {variants.length > 1 && <button type="button" onClick={() => setVariants((current: any[]) => current.filter((_: any, i: number) => i !== index))} className="min-h-11 px-3 text-red-800 underline">Remove option</button>}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label>Option name<input name={`variants[${index}][name]`} required value={variant.name} onChange={(event) => updateVariant(index, 'name', event.target.value)} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                                <label>Colour<input name={`variants[${index}][colour]`} value={variant.colour} onChange={(event) => updateVariant(index, 'colour', event.target.value)} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                                <label>SKU<input name={`variants[${index}][sku]`} value={variant.sku} onChange={(event) => updateVariant(index, 'sku', event.target.value)} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                                <label>Option price RWF (blank uses product price)<input type="number" name={`variants[${index}][price_rwf]`} min={0} value={variant.price_rwf} onChange={(event) => updateVariant(index, 'price_rwf', event.target.value)} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                                <label>Stock<input type="number" name={`variants[${index}][stock_quantity]`} required min={0} value={variant.stock_quantity} onChange={(event) => updateVariant(index, 'stock_quantity', event.target.value)} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                            </div>
                        </div>
                    ))}
                    <button type="button" onClick={() => setVariants((current: any[]) => [...current, { name: '', colour: '', sku: '', price_rwf: '', stock_quantity: 0 }])} className="min-h-11 border border-stone-400 px-4">Add option</button>
                </fieldset>
                <fieldset className="space-y-3 border-t border-stone-300 pt-5">
                    <legend className="font-semibold">Product image</legend>
                    {product?.images?.map((image: any) => <img key={image.id} src={image.url} alt={image.alt_text} width={180} height={180} loading="lazy" className="h-36 w-36 object-cover" />)}
                    <label className="block">Add image (JPEG, PNG or WebP, max 5 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" className="mt-2 block" /></label>
                    <label className="block">Image description<input name="image_alt" maxLength={255} className="mt-2 min-h-11 w-full border bg-white px-3" /></label>
                </fieldset>
                <button className="min-h-12 bg-stone-900 px-6 text-white">Save product</button>
            </form>
        </AdminLayout>
    );
}
