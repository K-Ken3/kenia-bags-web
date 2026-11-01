import { createContext } from 'react';

export type StoreCartItem = {
    name: string;
    slug: string;
    variant_name: string;
    quantity: number;
    price_rwf: number;
    image?: string | null;
    image_alt?: string;
};

export type StoreSharedProps = {
    brandLogo?: string;
    storeCategories?: { id: number; name: string; slug: string }[];
    storePolicies?: { slug: string; title: string }[];
    demoProductImages?: string[];
    cartCount?: number;
    setCartCount?: (cartCount: number) => void;
    cartPreview?: StoreCartItem[];
    setCartPreview?: (cartPreview: StoreCartItem[]) => void;
    deliveryInformation?: string;
    [key: string]: unknown;
};

export const StoreContext = createContext<StoreSharedProps>({});

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isStoreCartItem(value: unknown): value is StoreCartItem {
    if (!isRecord(value)) {
        return false;
    }

    return typeof value.name === 'string'
        && typeof value.slug === 'string'
        && typeof value.variant_name === 'string'
        && typeof value.quantity === 'number'
        && typeof value.price_rwf === 'number'
        && (value.image === undefined || value.image === null || typeof value.image === 'string')
        && (value.image_alt === undefined || typeof value.image_alt === 'string');
}

export async function addCartForm(form: HTMLFormElement): Promise<{
    success: string;
    cartCount: number;
    cartPreview: StoreCartItem[];
}> {
    const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    let payload: unknown;
    try {
        payload = await response.json();
    } catch {
        throw new Error(response.ok
            ? 'The server returned an invalid cart response.'
            : `Unable to update your bag (request failed with status ${response.status}).`);
    }

    if (!response.ok) {
        const data = payload as { message?: string; errors?: Record<string, string[]> };
        const firstError = Object.values(data.errors || {}).flat()[0];
        throw new Error(firstError || data.message || 'Unable to add this item to your bag.');
    }

    if (!isRecord(payload)
        || typeof payload.success !== 'string'
        || typeof payload.cartCount !== 'number'
        || !Array.isArray(payload.cartPreview)) {
        throw new Error('The server returned an invalid cart response.');
    }

    const cartPreview = payload.cartPreview.filter(isStoreCartItem);
    if (cartPreview.length !== payload.cartPreview.length) {
        throw new Error('The server returned invalid cart items.');
    }

    return {
        success: payload.success,
        cartCount: payload.cartCount,
        cartPreview,
    };
}

export function settingText(value: unknown, fallback: string): string {
    if (typeof value !== 'string') {
        return fallback;
    }

    const text = value.trim();
    return text && !/^\[[^\]]+\]$/.test(text) ? text : fallback;
}
