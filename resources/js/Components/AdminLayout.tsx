import React from 'react';
import StatusMessages from './StatusMessages';

type Props = {
    title: string;
    eyebrow?: string;
    description?: string;
    actions?: React.ReactNode;
    children: React.ReactNode;
    flash?: { success?: string | null; error?: string | null };
    errors?: Record<string, string[]>;
};

type NavItem = {
    href: string;
    label: string;
    icon: React.ReactNode;
};

type NavGroup = {
    label: string;
    items: NavItem[];
};

function Icon({ path, ...rest }: { path: string } & React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.7} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...rest}>
            <path d={path} />
        </svg>
    );
}

const navGroups: NavGroup[] = [
    {
        label: 'Store',
        items: [
            { href: '/admin', label: 'Overview', icon: <Icon path="M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z" /> },
            { href: '/admin/orders', label: 'Orders', icon: <Icon path="M6 8h12l1 12H5L6 8Zm3 0V6a3 3 0 0 1 6 0v2" /> },
            { href: '/admin/products', label: 'Products', icon: <Icon path="M4 7h16l-1.5 13h-13L4 7Zm4 0V5a4 4 0 0 1 8 0v2" /> },
            { href: '/admin/categories', label: 'Categories', icon: <Icon path="M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v6H4zM14 15h6v6h-6z" /> },
            { href: '/admin/proofs', label: 'Payment proofs', icon: <Icon path="M6 3h9l4 4v14H6V3Zm9 0v4h4M9 12h6M9 16h4" /> },
        ],
    },
    {
        label: 'Content',
        items: [
            { href: '/admin/policies', label: 'Policies', icon: <Icon path="M5 4h11l3 3v13H5V4Zm3 5h8M8 13h6M8 17h4" /> },
            { href: '/admin/settings', label: 'Store settings', icon: <Icon path="M4 7h10M18 7h2M4 17h4M12 17h8M16 4v6M8 14v6" /> },
        ],
    },
    {
        label: 'Account',
        items: [
            { href: '/admin/security', label: 'Security', icon: <Icon path="M12 3 5 6v6c0 4 3 7.2 7 9 4-1.8 7-5 7-9V6l-7-3Zm-2.5 9 1.8 1.8L15 10" /> },
        ],
    },
];

function csrfToken() {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
}

function SignOutButton({ className = 'admin-nav-link' }: { className?: string }) {
    return (
        <form action="/admin/logout" method="post">
            <input type="hidden" name="_token" value={csrfToken()} />
            <button type="submit" className={`${className} w-full`}>
                <Icon path="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10" />
                <span>Sign out</span>
            </button>
        </form>
    );
}

function Navigation({ path, onNavigate }: { path: string; onNavigate?: () => void }) {
    const isActive = (href: string) => (href === '/admin' ? path === '/admin' : path === href || path.startsWith(`${href}/`));

    return (
        <nav aria-label="Administration navigation" className="admin-nav">
            {navGroups.map((group) => (
                <div key={group.label}>
                    <p className="admin-nav-section">{group.label}</p>
                    <div className="admin-nav">
                        {group.items.map((item) => (
                            <a
                                key={item.href}
                                href={item.href}
                                onClick={onNavigate}
                                aria-current={isActive(item.href) ? 'page' : undefined}
                                className={`admin-nav-link${isActive(item.href) ? ' is-active' : ''}`}
                            >
                                {item.icon}
                                <span>{item.label}</span>
                            </a>
                        ))}
                    </div>
                </div>
            ))}
        </nav>
    );
}

function Brand() {
    return (
        <a href="/admin" className="admin-sidebar-brand" aria-label="Isimbi Mart administration">
            <img src="/images/isimbi-mart-logo.png" alt="Isimbi Mart" width={479} height={298} />
            <span>Store administration</span>
        </a>
    );
}

export default function AdminLayout({ title, eyebrow = 'Administration', description, actions, children, flash, errors }: Props) {
    const [menuOpen, setMenuOpen] = React.useState(false);
    const menuButtonRef = React.useRef<HTMLButtonElement>(null);
    const closeButtonRef = React.useRef<HTMLButtonElement>(null);
    const path = typeof window !== 'undefined' ? window.location.pathname.replace(/\/+$/, '') || '/' : '';

    React.useEffect(() => {
        if (!menuOpen) return;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        closeButtonRef.current?.focus();

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setMenuOpen(false);
                menuButtonRef.current?.focus();
            }
        };

        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = previousOverflow;
        };
    }, [menuOpen]);

    const closeMenu = () => {
        setMenuOpen(false);
        menuButtonRef.current?.focus();
    };

    return (
        <div className="admin-shell">
            <a href="#admin-main" className="admin-skip-link">Skip to content</a>

            <aside className="admin-sidebar" aria-label="Administration sidebar">
                <Brand />
                <div className="admin-sidebar-scroll">
                    <Navigation path={path} />
                </div>
                <div className="admin-sidebar-foot">
                    <a href="/" className="admin-nav-link">
                        <Icon path="M14 5h5v5M19 5l-8 8M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" />
                        <span>View storefront</span>
                    </a>
                    <SignOutButton />
                </div>
            </aside>

            <div className="admin-content">
                <header className="admin-topbar">
                    <button
                        ref={menuButtonRef}
                        type="button"
                        className="admin-menu-button"
                        aria-label="Open administration menu"
                        aria-expanded={menuOpen}
                        aria-controls="admin-mobile-navigation"
                        onClick={() => setMenuOpen(true)}
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h10" />
                        </svg>
                    </button>
                    <div className="admin-topbar-crumbs">
                        <a href="/admin">Administration</a>
                        <span aria-hidden="true">/</span>
                        <span className="admin-topbar-current">{title}</span>
                    </div>
                    <a href="/" className="admin-link-button ml-auto hidden sm:inline-flex">View store</a>
                </header>

                <main id="admin-main" className="admin-main">
                    <div className="admin-page-head">
                        <div>
                            <p className="store-kicker">{eyebrow}</p>
                            <h1 className="admin-page-title">{title}</h1>
                            {description ? <p className="admin-page-description">{description}</p> : null}
                        </div>
                        {actions ? <div className="admin-page-actions">{actions}</div> : null}
                    </div>

                    <StatusMessages flash={flash} errors={errors} />

                    <div className="store-admin-enter">
                        {children}
                    </div>
                </main>
            </div>

            {menuOpen && (
                <>
                    <button type="button" aria-label="Close administration menu" className="admin-drawer-backdrop" onClick={closeMenu} tabIndex={-1} />
                    <aside id="admin-mobile-navigation" className="admin-drawer" aria-label="Administration menu">
                        <div className="flex items-start justify-between gap-3">
                            <Brand />
                            <button
                                ref={closeButtonRef}
                                type="button"
                                className="admin-menu-button shrink-0"
                                aria-label="Close administration menu"
                                onClick={closeMenu}
                            >
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                        <div className="admin-sidebar-scroll">
                            <Navigation path={path} onNavigate={closeMenu} />
                        </div>
                        <div className="admin-sidebar-foot">
                            <a href="/" className="admin-nav-link">
                                <Icon path="M14 5h5v5M19 5l-8 8M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" />
                                <span>View storefront</span>
                            </a>
                            <SignOutButton />
                        </div>
                    </aside>
                </>
            )}
        </div>
    );
}
