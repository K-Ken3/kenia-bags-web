import React from 'react';
import AdminAuthLayout from '../../Components/AdminAuthLayout';

export default function AdminLogin({ flash, errors }: any) {
    return (
        <AdminAuthLayout
            title="Administrator sign in"
            subtitle="Use your administrator email and password. You will be asked for a verification code next."
            flash={flash}
            errors={errors}
            footer={<span>Not an administrator? <a href="/">Return to the storefront</a>.</span>}
        >
            <form action="/admin/login" method="post" className="space-y-5">
                <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                <label className="admin-field">Email
                    <input id="email" name="email" type="email" autoComplete="username" required className="store-input" />
                </label>
                <label className="admin-field">Password
                    <input id="password" name="password" type="password" autoComplete="current-password" required className="store-input" />
                </label>
                <button className="store-primary-button w-full">Sign in</button>
            </form>
        </AdminAuthLayout>
    );
}
