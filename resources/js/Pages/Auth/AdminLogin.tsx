import React from 'react';
import StatusMessages from '../../Components/StatusMessages';

export default function AdminLogin({ flash, errors }: any) {
    return (
        <main className="mx-auto mt-16 max-w-md border border-stone-300 bg-[var(--surface)] p-6 sm:mt-24">
            <a href="/" className="text-sm underline">Back to store</a>
            <h1 className="mt-5 font-serif text-3xl">Administrator sign in</h1>
            <StatusMessages flash={flash} errors={errors} />
            <form action="/admin/login" method="post" className="mt-6 space-y-5">
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                <div>
                    <label htmlFor="email" className="mb-2 block">Email</label>
                    <input id="email" name="email" type="email" autoComplete="username" required className="min-h-12 w-full border border-stone-300 bg-white px-3" />
                </div>
                <div>
                    <label htmlFor="password" className="mb-2 block">Password</label>
                    <input id="password" name="password" type="password" autoComplete="current-password" required className="min-h-12 w-full border border-stone-300 bg-white px-3" />
                </div>
                <button className="min-h-12 w-full bg-stone-900 px-4 text-white">Sign in</button>
            </form>
        </main>
    );
}
