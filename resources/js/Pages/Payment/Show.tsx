import React from 'react';
import StatusMessages from '../../Components/StatusMessages';

export default function PaymentShow({ order, flash, errors }: any) {
    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <header className="border-b border-[var(--hairline)] bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <h1 className="font-serif text-2xl text-[var(--chocolate)]">Payment</h1>
                </div>
            </header>

            <StatusMessages flash={flash} errors={errors} />
            <main className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <h2 className="text-xl font-semibold">Order {order?.order_number}</h2>
                    <p className="text-lg font-bold mt-2">Total: RWF {order?.total}</p>
                </div>

                <form action={`/order/${order?.public_reference}/proof`} method="post" encType="multipart/form-data" className="max-w-xl space-y-6 border border-[var(--hairline)] bg-white p-5 sm:p-7">
                    <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                    <div>
                        <label className="block mb-2">Payment Proof</label>
                        <input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required className="w-full" />
                    </div>
                    <div>
                        <label className="block mb-2">Reference (optional)</label>
                        <input type="text" name="reference" className="store-input" />
                    </div>
                    <button type="submit" className="store-primary-button w-full">
                        Upload Proof
                    </button>
                </form>
            </main>
        </div>
    );
}
