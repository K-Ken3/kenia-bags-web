import React from 'react';

type Props = {
    flash?: { success?: string | null; error?: string | null };
    errors?: Record<string, string[]>;
};

export default function StatusMessages({ flash = {}, errors = {} }: Props) {
    const messages = Object.values(errors).flat();
    if (!flash.success && !flash.error && messages.length === 0) return null;

    return (
        <div className="store-status mx-auto max-w-7xl space-y-2 px-4 pt-4 sm:px-6 lg:px-8" role={flash.error || messages.length > 0 ? 'alert' : 'status'} aria-live={flash.error || messages.length > 0 ? 'assertive' : 'polite'}>
            {flash.success && <p className="rounded-2xl border border-[#b9d6c3] bg-[#eaf4ed] p-3.5 text-[#1f5138]">{flash.success}</p>}
            {flash.error && <p className="rounded-2xl border border-[#e3c4bc] bg-[#fbeeea] p-3.5 text-[#7d2f22]">{flash.error}</p>}
            {messages.map((message, index) => (
                <p key={index} className="rounded-2xl border border-[#e3c4bc] bg-[#fbeeea] p-3.5 text-[#7d2f22]">{message}</p>
            ))}
        </div>
    );
}
