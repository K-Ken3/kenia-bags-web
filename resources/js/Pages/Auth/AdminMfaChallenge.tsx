import React from 'react';
import AdminAuthLayout from '../../Components/AdminAuthLayout';

export default function AdminMfaChallenge({ email, flash, errors }: any) {
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    return (
        <AdminAuthLayout
            title="Two-factor verification"
            subtitle={<>Enter the current code from the authenticator app for <strong className="font-semibold text-[var(--chocolate)]">{email}</strong>.</>}
            flash={flash}
            errors={errors}
            footer={
                <form action="/admin/logout" method="post" className="flex flex-wrap items-center justify-between gap-3">
                    <input type="hidden" name="_token" value={csrf} />
                    <span>Having trouble signing in?</span>
                    <button type="submit">Sign out</button>
                </form>
            }
        >
            <form action="/admin/mfa/verify" method="post" className="space-y-5">
                <input type="hidden" name="_token" value={csrf} />
                <label className="admin-field">6-digit code
                    <input
                        id="code"
                        name="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        pattern="[0-9]{6}"
                        maxLength={6}
                        required
                        className="store-input text-center text-lg tracking-[0.4em]"
                    />
                </label>
                <button className="store-primary-button w-full">Verify</button>
            </form>

            <details className="store-details mt-5 rounded-2xl border border-[var(--hairline)] bg-[var(--paper)]">
                <summary className="cursor-pointer px-4 py-3 text-sm font-semibold">Use a recovery code instead</summary>
                <form action="/admin/mfa/verify" method="post" className="space-y-4 px-4 pb-4">
                    <input type="hidden" name="_token" value={csrf} />
                    <label className="admin-field">Recovery code
                        <input id="recovery_code" name="recovery_code" autoComplete="one-time-code" maxLength={40} required className="store-input" />
                    </label>
                    <button className="admin-secondary-button w-full">Use recovery code</button>
                </form>
            </details>
        </AdminAuthLayout>
    );
}
