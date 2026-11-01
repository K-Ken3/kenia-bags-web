import React from 'react';
import StatusMessages from './StatusMessages';

type Props = {
    title: string;
    children: React.ReactNode;
    flash?: { success?: string | null; error?: string | null };
    errors?: Record<string, string[]>;
};

export default function AdminLayout({ title, children, flash, errors }: Props) {
    return (
        <div className="min-h-screen bg-[var(--ivory)]">
            <header className="border-b border-stone-300 bg-[var(--surface)]">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="/admin" className="font-serif text-xl">Store administration</a>
                    <nav aria-label="Admin navigation" className="flex flex-wrap gap-4 text-sm">
                        <a href="/admin/orders" className="underline underline-offset-2">Orders</a>
                        <a href="/admin/products" className="underline underline-offset-2">Products</a>
                        <a href="/admin/categories" className="underline underline-offset-2">Categories</a>
                        <a href="/admin/proofs" className="underline underline-offset-2">Payment proofs</a>
                        <a href="/admin/settings" className="underline underline-offset-2">Settings</a>
                        <a href="/admin/policies" className="underline underline-offset-2">Policies</a>
                    </nav>
                    <form action="/admin/logout" method="post">
                        <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                        <button className="min-h-11 underline underline-offset-2">Sign out</button>
                    </form>
                </div>
            </header>
            <StatusMessages flash={flash} errors={errors} />
            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 className="mb-7 font-serif text-3xl">{title}</h1>
                {children}
            </main>
        </div>
    );
}
