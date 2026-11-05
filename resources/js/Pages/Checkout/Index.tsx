import React from 'react';
import StatusMessages from '../../Components/StatusMessages';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';

export default function CheckoutIndex({ items = [], subtotal = 0, deliveryFee = 0, deliveryLabel, paymentMethods = [], idempotencyKey, canPlaceOrder = false, flash, errors, brandName, cartCount, contactEmail, contactPhone }: any) {
    const [submitting, setSubmitting] = React.useState(false);
    const [paymentMethod, setPaymentMethod] = React.useState<string>(paymentMethods[0]?.value || '');
    const emailRequired = paymentMethod === 'xentripay_momo';

    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />

            <StatusMessages flash={flash} errors={errors} />
            <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-10">
                <header className="mb-8 border-b border-[var(--hairline)] pb-6">
                    <p className="store-kicker">Almost yours</p>
                    <h1 className="store-section-title mt-3 text-[var(--chocolate)]">Checkout</h1>
                </header>
                {items.length === 0 ? (
                    <div className="border border-[var(--hairline)] bg-white p-8 text-center">
                        <p>Your cart is empty.</p>
                        <a href="/shop" className="mt-4 inline-block underline">Return to the shop</a>
                    </div>
                ) : (
                    <div className="grid items-start gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-12">
                        <details className="order-1 border border-[var(--hairline)] bg-white p-5 lg:sticky lg:top-6" open>
                            <summary className="cursor-pointer text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Order summary</summary>
                            {items.map((item: any, index: number) => (
                                <div key={index} className="flex justify-between gap-4 border-b border-[var(--hairline)] py-3 last:border-0">
                                    <span>{item.name}{item.variant_name ? ` — ${item.variant_name}` : ''} × {item.quantity}</span>
                                    <strong className="shrink-0 text-[var(--chocolate)]">RWF {new Intl.NumberFormat('en').format(Number(item.line_total))}</strong>
                                </div>
                            ))}
                            <p className="mt-4 flex justify-between"><span>Subtotal</span><strong>RWF {new Intl.NumberFormat('en').format(Number(subtotal))}</strong></p>
                            <p className="mt-3 flex justify-between border-t border-[var(--hairline)] pt-3 text-sm text-[var(--muted)]"><span>Delivery</span><span>{deliveryLabel || `RWF ${new Intl.NumberFormat('en').format(Number(deliveryFee))}`}</span></p>
                            <p className="mt-4 flex justify-between border-t border-[var(--hairline)] pt-4 text-lg"><span>Total</span><strong className="text-[var(--chocolate)]">RWF {new Intl.NumberFormat('en').format(Number(subtotal) + Number(deliveryFee))}</strong></p>
                        </details>
                <form action="/checkout" method="post" className="order-2 space-y-5" onSubmit={() => setSubmitting(true)}>
                    <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                    <input type="hidden" name="idempotency_key" value={idempotencyKey} />
                    <div>
                        <label htmlFor="customer_name" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Full name</label>
                        <input id="customer_name" type="text" name="customer_name" autoComplete="name" required maxLength={120} className="store-input" />
                    </div>
                    <div>
                        <label htmlFor="customer_email" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Email {emailRequired ? '(required for mobile money)' : '(optional)'}</label>
                        <input id="customer_email" type="email" name="customer_email" autoComplete="email" required={emailRequired} className="store-input" />
                    </div>
                    <div>
                        <label htmlFor="customer_phone" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Phone number</label>
                        <input id="customer_phone" type="tel" name="customer_phone" autoComplete="tel" required maxLength={32} className="store-input" />
                    </div>
                    <div>
                        <label htmlFor="delivery_location" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Delivery location</label>
                        <textarea id="delivery_location" name="delivery_location" autoComplete="street-address" required maxLength={500} className="store-input" rows={3}></textarea>
                    </div>
                    <div>
                        <label htmlFor="notes" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Order notes (optional)</label>
                        <textarea id="notes" name="notes" maxLength={1000} className="store-input" rows={2}></textarea>
                    </div>
                    <fieldset className="space-y-3">
                        <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Payment method</legend>
                        {paymentMethods.length > 0 ? paymentMethods.map((method: any, index: number) => (
                            <label key={method.value} className="flex min-h-12 items-center gap-3 border border-[var(--hairline)] bg-white px-4">
                                <input type="radio" name="payment_method" value={method.value} required defaultChecked={index === 0} onChange={() => setPaymentMethod(method.value)} />
                                <span>{method.label}</span>
                            </label>
                        )) : (
                            <p className="border border-[var(--beige)] bg-[var(--sand)] p-4 text-sm text-[var(--chocolate)]">Checkout is temporarily unavailable while the store owner configures a payment method. No payment details or service have been invented.</p>
                        )}
                    </fieldset>
                    {paymentMethod === 'xentripay_momo' && (
                        <p className="border border-[var(--beige)] bg-[var(--sand)] p-4 text-sm text-[var(--chocolate)]">After placing the order, approve the mobile money prompt on your phone. The order is confirmed only after XentriPay reports the payment as successful.</p>
                    )}
                    {deliveryLabel && <p className="border border-[var(--beige)] bg-[var(--sand)] p-4 text-sm text-[var(--chocolate)]">Checkout is unavailable until the store confirms its delivery fee. The amount above is not a final order total.</p>}
                    <button type="submit" disabled={submitting || !canPlaceOrder} className="store-primary-button w-full disabled:cursor-not-allowed disabled:opacity-50">
                        {submitting ? 'Placing order…' : 'Place order'}
                    </button>
                </form>
                    </div>
                )}
            </main>
            <StoreFooter brandName={brandName} contactEmail={contactEmail} contactPhone={contactPhone} />
        </div>
    );
}
