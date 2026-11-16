import React from 'react';
import AdminAuthLayout from '../../Components/AdminAuthLayout';

export default function AdminMfaSetup({ email, secret, qrCode, flash, errors }: any) {
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    return (
        <AdminAuthLayout
            title="Set up two-factor authentication"
            subtitle={<>Administrator accounts require an authenticator app. Scan the code, then enter the 6-digit code for <strong className="font-semibold text-[var(--chocolate)]">{email}</strong>.</>}
            flash={flash}
            errors={errors}
            footer={
                <form action="/admin/logout" method="post" className="flex flex-wrap items-center justify-between gap-3">
                    <input type="hidden" name="_token" value={csrf} />
                    <span>Setting up on the wrong account?</span>
                    <button type="submit">Sign out</button>
                </form>
            }
        >
            <ol className="admin-mfa-steps">
                <li>Scan the QR code with Google Authenticator, Authy, or a similar app.</li>
                <li>Enter the 6-digit code shown in the app to activate MFA.</li>
                <li>Save the recovery codes shown afterwards in a safe place.</li>
            </ol>

            {qrCode ? (
                <img src={qrCode} alt="Authenticator QR code" width={240} height={240} className="admin-qr mt-6" />
            ) : null}

            <details className="store-details mt-4 rounded-2xl border border-[var(--hairline)] bg-[var(--paper)]">
                <summary className="cursor-pointer px-4 py-3 text-sm font-semibold">Cannot scan it? Show the setup key</summary>
                <div className="px-4 pb-4">
                    <code className="admin-code">{secret}</code>
                </div>
            </details>

            <form action="/admin/mfa/setup" method="post" className="mt-6 space-y-5">
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
                <button className="store-primary-button w-full">Activate MFA</button>
            </form>
        </AdminAuthLayout>
    );
}
