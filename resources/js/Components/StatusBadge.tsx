import React from 'react';

type Props = {
    status: string;
    label?: string;
};

const appearance: Record<string, string> = {
    paid: 'border-green-700 bg-green-50 text-green-900',
    failed: 'border-red-700 bg-red-50 text-red-900',
    expired: 'border-stone-500 bg-stone-100 text-stone-800',
    cancelled: 'border-stone-500 bg-stone-100 text-stone-800',
    pending: 'border-[#c98a6e] bg-[var(--primary-tint)] text-[#7e3316]',
};

export default function StatusBadge({ status, label }: Props) {
    return (
        <span data-status={status} className={`store-badge inline-flex min-h-7 items-center rounded-full border px-3 py-1 text-[0.66rem] font-semibold uppercase tracking-[0.08em] ${appearance[status] || 'border-[#c98a6e] bg-[var(--primary-tint)] text-[#7e3316]'}`}>
            {label || status.replaceAll('_', ' ')}
        </span>
    );
}
