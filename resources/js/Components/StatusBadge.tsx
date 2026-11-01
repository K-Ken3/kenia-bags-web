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
};

export default function StatusBadge({ status, label }: Props) {
    return (
        <span className={`inline-flex min-h-7 items-center border px-2 py-1 text-xs font-medium uppercase tracking-wide ${appearance[status] || 'border-amber-700 bg-amber-50 text-amber-950'}`}>
            {label || status.replaceAll('_', ' ')}
        </span>
    );
}
