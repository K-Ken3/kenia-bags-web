import React from 'react';
import StatusMessages from '../../Components/StatusMessages';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';
import StatusBadge from '../../Components/StatusBadge';

export default function OrderShow({ order, momoPayCode, whatsappNumber, flash, errors, brandName, cartCount, contactEmail, contactPhone }: any) {
    const whatsappDigits = String(whatsappNumber || '').replace(/\D/g, '');
    const whatsappLink = whatsappDigits.length >= 8
        ? `https://wa.me/${whatsappDigits}?text=${encodeURIComponent(`Order ${order.public_reference} inquiry`)}`
        : null;

    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />
            <StatusMessages flash={flash} errors={errors} />
            <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <p className="store-kicker">Order confirmation</p>
                <h1 className="mt-2 font-serif text-3xl text-[var(--chocolate)]">{order.public_reference}</h1>
                <div className="my-5 space-y-2 border border-[var(--hairline)] bg-white p-5">
                    <p>Order status: <StatusBadge status={order.order_status} /></p>
                    <p>Payment status: <StatusBadge status={order.payment_status} label={order.payment_status === 'paid' ? 'Paid — verified' : undefined} /></p>
                    <p>Delivery status: <StatusBadge status={order.delivery_status} /></p>
                </div>
                {['pending', 'under_review'].includes(order.payment_status) && momoPayCode && (
                    <section className="mb-5 border border-[var(--beige)] bg-[var(--sand)] p-5">
                        <h2 className="font-semibold text-[var(--chocolate)]">Manual MoMo payment — not automatically verified</h2>
                        <p className="mt-2">Pay code: <strong>{momoPayCode}</strong></p>
                        <p>Amount: <strong>RWF {order.total_rwf}</strong></p>
                        <p>Use reference: <strong>{order.public_reference}</strong></p>
                        <p className="mt-2 text-sm">Your order stays unpaid until an administrator manually verifies proof of payment.</p>
                        {order.payment_status !== 'under_review' && (
                            <form action={`/order/${order.public_reference}/proof`} method="post" encType="multipart/form-data" className="mt-4 space-y-3">
                                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                                <label className="block">Upload payment proof (JPEG, PNG or WebP, max 5 MB)
                                    <input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required className="mt-2 block" />
                                </label>
                                <label className="block">Payment reference (optional)
                                    <input name="reference" maxLength={120} className="store-input mt-2" />
                                </label>
                                <button className="store-primary-button">Submit for manual review</button>
                            </form>
                        )}
                    </section>
                )}
                {whatsappLink && <a href={whatsappLink} target="_blank" rel="noreferrer" className="mb-5 inline-block underline">Contact store about this order</a>}
                <section className="mb-5 border border-[var(--hairline)] bg-white p-4">
                    <h2 className="font-semibold">Delivery details</h2>
                    <p className="mt-2">{order.customer_name}</p>
                    <p>{order.customer_phone}</p>
                    {order.customer_email && <p>{order.customer_email}</p>}
                    <p className="mt-2">{order.delivery_location}</p>
                </section>
                <section className="border border-[var(--hairline)] bg-white p-4">
                    <h2 className="mb-2 font-semibold">Order items</h2>
                    {order.items.map((item: any) => (
                        <div key={item.id} className="flex justify-between gap-4 border-b border-[var(--hairline)] py-3 last:border-0">
                            <span>{item.product_name}{item.variant_name ? ` — ${item.variant_name}` : ''} × {item.quantity}</span>
                            <strong>RWF {new Intl.NumberFormat('en').format(item.unit_price_rwf * item.quantity)}</strong>
                        </div>
                    ))}
                    <p className="mt-3 flex justify-between"><span>Subtotal</span><strong>RWF {new Intl.NumberFormat('en').format(order.subtotal_rwf)}</strong></p>
                    <p className="mt-2 flex justify-between"><span>Delivery</span><strong>RWF {new Intl.NumberFormat('en').format(order.delivery_fee_rwf)}</strong></p>
                    <p className="mt-3 flex justify-between border-t border-[var(--hairline)] pt-3 text-lg"><span>Total</span><strong className="text-[var(--chocolate)]">RWF {new Intl.NumberFormat('en').format(order.total_rwf)}</strong></p>
                </section>
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
