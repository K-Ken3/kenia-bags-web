import React from 'react';
import StatusMessages from './StatusMessages';

type Props = {
    title: string;
    subtitle: React.ReactNode;
    children: React.ReactNode;
    footer?: React.ReactNode;
    flash?: { success?: string | null; error?: string | null };
    errors?: Record<string, string[]>;
};

const points = ['Two-factor protected administrator access', 'Private payment proof review', 'Session controls and audit history'];

export default function AdminAuthLayout({ title, subtitle, children, footer, flash, errors }: Props) {
    return (
        <div className="admin-auth-shell">
            <div className="admin-auth-grid">
                <aside className="admin-auth-brand">
                    <a href="/" aria-label="Isimbi Mart home">
                        <img src="/images/isimbi-mart-logo.png" alt="Isimbi Mart" width={479} height={298} />
                    </a>
                    <div>
                        <p className="admin-auth-eyebrow">Isimbi Mart administration</p>
                        <p className="admin-auth-headline">Care for every order, <em>beautifully</em>.</p>
                        <p className="admin-auth-lede">Products, payments, and fulfilment in one calm workspace.</p>
                    </div>
                    <ul className="admin-auth-points">
                        {points.map((point) => (
                            <li key={point}>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                    <path d="m5 12 4.5 4.5L19 7" />
                                </svg>
                                <span>{point}</span>
                            </li>
                        ))}
                    </ul>
                    <a href="/" className="admin-auth-back">Back to storefront</a>
                </aside>

                <main className="admin-auth-panel">
                    <div className="admin-auth-card">
                        <h1 className="admin-auth-title">{title}</h1>
                        <p className="admin-auth-subtitle">{subtitle}</p>
                        <StatusMessages flash={flash} errors={errors} />
                        <div className="admin-auth-body">{children}</div>
                        {footer ? <div className="admin-auth-footer">{footer}</div> : null}
                    </div>
                </main>
            </div>
        </div>
    );
}
