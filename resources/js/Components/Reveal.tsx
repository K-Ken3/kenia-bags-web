import React from 'react';

type Props = {
    children: React.ReactNode;
    className?: string;
    delay?: number;
};

export default function Reveal({ children, className = '', delay = 0 }: Props) {
    const ref = React.useRef<HTMLDivElement>(null);
    const [visible, setVisible] = React.useState(false);

    React.useEffect(() => {
        if (typeof IntersectionObserver === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setVisible(true);
            return;
        }

        const node = ref.current;
        if (!node) return;

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setVisible(true);
                        observer.disconnect();
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
        );

        observer.observe(node);
        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            className={`store-reveal${visible ? ' is-revealed' : ''}${className ? ` ${className}` : ''}`}
            style={delay ? { transitionDelay: `${delay}ms` } : undefined}
        >
            {children}
        </div>
    );
}
