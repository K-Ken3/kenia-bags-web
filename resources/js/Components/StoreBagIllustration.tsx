import React from 'react';

type Props = {
    className?: string;
    detail?: number;
};

export default function StoreBagIllustration({ className = '', detail = 0 }: Props) {
    const accent = detail % 2 === 0 ? 'var(--chocolate-soft)' : 'var(--chocolate)';

    return (
        <svg
            aria-hidden="true"
            className={`store-bag-art ${className}`}
            viewBox="0 0 360 340"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path d="M95 132h170l-13 157H108L95 132Z" fill="rgba(255,255,255,.54)" stroke={accent} strokeWidth="2.2" />
            <path d="M139 138v-26c0-29 18-51 41-51s41 22 41 51v26" stroke={accent} strokeWidth="2.2" />
            <path d="M111 153h138" stroke="rgba(73,55,47,.22)" strokeWidth="1.2" />
            <path d="M122 274h116" stroke="rgba(73,55,47,.22)" strokeWidth="1.2" />
            <path d="M164 184h32" stroke={accent} strokeWidth="2" strokeLinecap="round" />
            <circle cx="180" cy="210" r="3" fill={accent} />
            <path d="M84 294h192" stroke="rgba(73,55,47,.18)" strokeWidth="1.2" />
            <path d="M78 306h204" stroke="rgba(73,55,47,.11)" strokeWidth="1.2" />
        </svg>
    );
}
