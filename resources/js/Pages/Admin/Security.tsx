import React from 'react';
import AdminLayout from '../../Components/AdminLayout';

export default function Security({ mfaEnabled, confirmedAt, recoveryCodeCount, recoveryCodes, flash, errors }: any) {
    return (
        <AdminLayout
            title="Account security"
            description="Two-factor authentication and recovery controls for your administrator account."
            flash={flash}
            errors={errors}
        >
            <div className="grid max-w-4xl gap-5">
                <section className="admin-card">
                    <div className="admin-card-body flex flex-wrap items-start gap-4">
                        <span className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ${mfaEnabled ? 'bg-green-50 text-green-800' : 'bg-[var(--primary-tint)] text-[var(--primary-hover)]'}`}>
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M12 3 5 6v6c0 4 3 7.2 7 9 4-1.8 7-5 7-9V6l-7-3Zm-2.5 9 1.8 1.8L15 10" />
                            </svg>
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="admin-card-title">Two-factor authentication</h2>
                                <span className={`store-badge inline-flex min-h-7 items-center rounded-full border px-3 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.08em] ${mfaEnabled ? 'border-green-700 bg-green-50 text-green-900' : 'border-[#c98a6e] bg-[var(--primary-tint)] text-[#7e3316]'}`}>
                                    {mfaEnabled ? 'Enabled' : 'Not enabled'}
                                </span>
                            </div>
                            {confirmedAt ? <p className="mt-1 text-sm text-[var(--muted)]">Activated {new Date(confirmedAt).toLocaleString()}</p> : null}
                            <p className="mt-3 text-sm leading-relaxed text-[var(--muted)]">
                                MFA is mandatory for administrator accounts and cannot be disabled from this page.
                                {recoveryCodeCount > 0 ? ` ${recoveryCodeCount} unused recovery code(s) remain.` : ' No recovery codes remain; regenerate them now.'}
                            </p>
                        </div>
                    </div>
                </section>

                {Array.isArray(recoveryCodes) && recoveryCodes.length > 0 ? (
                    <section className="admin-card border-amber-300 bg-[#f8ecd9]">
                        <div className="admin-card-head border-[#e2c8a6]">
                            <div>
                                <h2 className="admin-card-title">Save these recovery codes now</h2>
                                <p className="mt-1 text-sm text-[#6b4a1f]">Each code works once. They will not be shown again.</p>
                            </div>
                        </div>
                        <div className="admin-card-body">
                            <ul className="grid gap-2 font-mono text-sm sm:grid-cols-2">
                                {recoveryCodes.map((code: string) => (
                                    <li key={code} className="rounded-xl border border-[#e2c8a6] bg-[var(--paper)] px-3 py-2.5">{code}</li>
                                ))}
                            </ul>
                        </div>
                    </section>
                ) : null}

                <form action="/admin/mfa/recovery-codes" method="post" className="admin-card">
                    <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                    <div className="admin-card-head">
                        <div>
                            <h2 className="admin-card-title">Regenerate recovery codes</h2>
                            <p className="mt-1 text-sm text-[var(--muted)]">This invalidates every existing recovery code and signs out your other sessions.</p>
                        </div>
                    </div>
                    <div className="admin-card-body space-y-5">
                        <label className="admin-field max-w-sm">Current password
                            <input name="current_password" type="password" autoComplete="current-password" required className="store-input" />
                        </label>
                        <button className="store-primary-button">Generate new codes</button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
