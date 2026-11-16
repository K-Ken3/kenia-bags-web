import React from 'react';
import StatusMessages from '../../Components/StatusMessages';
import StoreFooter from '../../Components/StoreFooter';
import StoreHeader from '../../Components/StoreHeader';

function PaymentMethodCard({ method, selected, onSelect }: { method: any; selected: boolean; onSelect: () => void }) {
    const brand = getPaymentBrand(method.value);
    return (
        <label
            className={`store-payment-option flex cursor-pointer items-center gap-4 border p-4 transition ${
                selected
                    ? 'is-selected border-[var(--primary)] bg-[var(--primary-tint)] ring-1 ring-[var(--primary)]'
                    : 'border-[var(--hairline)] bg-[var(--paper)] hover:border-[var(--beige)]'
            }`}
        >
            <input type="radio" name="payment_method" value={method.value} required checked={selected} onChange={onSelect} className="sr-only" />
            <div className="flex h-11 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white" aria-hidden="true">
                {brand.image ? (
                    <img src={brand.image} alt="" className="h-full w-full object-contain" />
                ) : (
                    <span className={`flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold ${brand.badgeClass}`}>{brand.icon}</span>
                )}
            </div>
            <div className="min-w-0 flex-1">
                <p className="font-medium text-[var(--chocolate)]">{brand.name || method.label}</p>
                {brand.subtitle && <p className="text-xs text-[var(--muted)]">{brand.subtitle}</p>}
            </div>
            <div className={`h-5 w-5 shrink-0 rounded-full border-2 ${selected ? 'border-[var(--primary)] bg-[var(--primary)]' : 'border-[var(--hairline)]'}`}>
                {selected && <span className="block h-full w-full scale-[0.45] rounded-full bg-white" />}
            </div>
        </label>
    );
}

function getPaymentBrand(value: string): { name: string | null; subtitle: string | null; icon: string; badgeClass: string; image?: string } {
    switch (value) {
        case 'xentripay_momo':
        case 'manual_momo':
            return { name: 'MTN MoMo', subtitle: 'Mobile Money', icon: 'M', badgeClass: 'bg-[#ffcc00] text-[#000]', image: '/images/payment-methods/mtn-momo.jpeg' };
        case 'xentripay_airtel':
            return { name: 'Airtel Money', subtitle: 'Mobile Money', icon: 'A', badgeClass: 'bg-[#e40000] text-white', image: '/images/payment-methods/airtel-money.jpeg' };
        case 'xentripay_card':
            return { name: 'Visa / Mastercard', subtitle: 'Debit or Credit Card', icon: '💳', badgeClass: 'bg-[#1a1f71] text-white', image: '/images/payment-methods/visa-mastercard.jpeg' };
        case 'sandbox':
            return { name: 'Sandbox', subtitle: 'Test payment — not real money', icon: 'S', badgeClass: 'bg-gray-200 text-gray-700' };
        default:
            return { name: null, subtitle: null, icon: '₣', badgeClass: 'bg-[var(--beige)] text-[var(--chocolate)]' };
    }
}

export default function CheckoutIndex({ items = [], subtotal = 0, deliveryFee = 0, deliveryLabel, paymentMethods = [], idempotencyKey, canPlaceOrder = false, flash, errors, brandName, cartCount, contactEmail, contactPhone }: any) {
    const [submitting, setSubmitting] = React.useState(false);
    const [paymentMethod, setPaymentMethod] = React.useState<string>(paymentMethods[0]?.value || '');
    const isMobileMoney = ['xentripay_momo', 'xentripay_airtel', 'manual_momo'].includes(paymentMethod);
    const isCard = paymentMethod === 'xentripay_card';
    const emailRequired = isMobileMoney || isCard;

    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <StoreHeader brandName={brandName} cartCount={cartCount} />

            <StatusMessages flash={flash} errors={errors} />
            <main className="store-page mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-10">
                <header className="mb-8 border-b border-[var(--hairline)] pb-6">
                    <p className="store-kicker">Almost yours</p>
                    <h1 className="store-section-title mt-3 text-[var(--chocolate)]">Checkout</h1>
                </header>
                {items.length === 0 ? (
                    <div className="rounded-[20px] border border-[var(--hairline)] bg-[var(--surface)] p-8 text-center shadow-[var(--shadow-soft)]">
                        <p>Your cart is empty.</p>
                        <a href="/shop" className="mt-4 inline-block underline">Return to the shop</a>
                    </div>
                ) : (
                    <div className="grid items-start gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-12">
                        <details className="store-details order-1 rounded-[20px] border border-[var(--hairline)] bg-[var(--surface)] p-5 shadow-[var(--shadow-soft)] lg:sticky lg:top-6" open>
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
                        <label htmlFor="customer_email" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-[var(--chocolate)]">Email {emailRequired ? '(required for online payment)' : '(optional)'}</label>
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
                        {paymentMethods.length > 0 ? (
                            <div className="space-y-2">
                                {paymentMethods.map((method: any) => (
                                    <PaymentMethodCard
                                        key={method.value}
                                        method={method}
                                        selected={paymentMethod === method.value}
                                        onSelect={() => setPaymentMethod(method.value)}
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="rounded-xl border border-[var(--beige)] bg-[var(--sand)] p-4 text-sm text-[var(--chocolate)]">Checkout is temporarily unavailable while the store owner configures a payment method. No payment details or service have been invented.</p>
                        )}
                    </fieldset>
                    {isMobileMoney && (
                        <p className="rounded-xl border border-[var(--beige)] bg-[var(--sand)] p-4 text-sm text-[var(--chocolate)]">After placing the order, approve the {paymentMethod === 'xentripay_airtel' ? 'Airtel Money' : 'mobile money'} prompt on your phone. The order is confirmed only after XentriPay reports the payment as successful.</p>
                    )}
                    {isCard && (
                        <p className="rounded-xl border border-[var(--beige)] bg-[var(--sand)] p-4 text-sm text-[var(--chocolate)]">After placing the order, you will be redirected to XentriPay's secure card page to pay with Visa or Mastercard. The order is confirmed only after XentriPay reports the payment as successful.</p>
                    )}
                    {deliveryLabel && <p className="rounded-xl border border-[var(--beige)] bg-[var(--sand)] p-4 text-sm text-[var(--chocolate)]">Checkout is unavailable until the store confirms its delivery fee. The amount above is not a final order total.</p>}
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
