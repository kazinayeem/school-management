import { router, usePage } from '@inertiajs/react';

/**
 * A tab that lives in the address bar (?tab=documents) instead of component state.
 * It is read from the current URL on every render, so a direct link, a refresh and the
 * browser's Back and Forward buttons all land on the right tab. Changing it adds one
 * history entry and makes no server request. Unknown values fall back to the first tab.
 */
export function useUrlTab<T extends string>(allowed: readonly T[], key = 'tab'): [T, (next: T) => void] {
    const url = usePage().url;
    const raw = new URL(url, 'http://local').searchParams.get(key);
    const tab = (allowed as readonly string[]).includes(raw ?? '') ? (raw as T) : allowed[0];

    const set = (next: T) => {
        if (next === tab) return;
        const target = new URL(url, window.location.origin);
        if (next === allowed[0]) target.searchParams.delete(key);
        else target.searchParams.set(key, next);
        router.push({ url: target.pathname + target.search + target.hash, preserveScroll: true, preserveState: true });
    };

    return [tab, set];
}
