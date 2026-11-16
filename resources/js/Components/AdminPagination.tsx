import React from 'react';

function pageLabel(label: string) {
    if (label.includes('Previous')) return 'Previous';
    if (label.includes('Next')) return 'Next';
    return label;
}

export default function AdminPagination({ links, label = 'Pagination' }: { links?: any[]; label?: string }) {
    if (!Array.isArray(links) || links.length <= 3) return null;

    return (
        <nav aria-label={label} className="admin-pagination">
            {links.map((link, index) => (
                link.url ? (
                    <a key={index} href={link.url} aria-current={link.active ? 'page' : undefined} className={link.active ? 'is-current' : undefined}>
                        {pageLabel(link.label)}
                    </a>
                ) : (
                    <span key={index} className="is-disabled">{pageLabel(link.label)}</span>
                )
            ))}
        </nav>
    );
}
