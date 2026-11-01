import React from 'react';

type Props = {
    flash?: { success?: string | null; error?: string | null };
    errors?: Record<string, string[]>;
};

export default function StatusMessages({ flash = {}, errors = {} }: Props) {
    const messages = Object.values(errors).flat();
    if (!flash.success && !flash.error && messages.length === 0) return null;

    return (
        <div className="mx-auto max-w-7xl space-y-2 px-4 pt-4 sm:px-6 lg:px-8" role={flash.error || messages.length > 0 ? 'alert' : 'status'} aria-live={flash.error || messages.length > 0 ? 'assertive' : 'polite'}>
            {flash.success && <p className="rounded border border-green-200 bg-green-50 p-3 text-green-800">{flash.success}</p>}
            {flash.error && <p className="rounded border border-red-200 bg-red-50 p-3 text-red-800">{flash.error}</p>}
            {messages.map((message, index) => (
                <p key={index} className="rounded border border-red-200 bg-red-50 p-3 text-red-800">{message}</p>
            ))}
        </div>
    );
}
