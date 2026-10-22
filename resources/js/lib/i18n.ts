import { useSyncExternalStore } from 'react';
import { ar } from './locales/ar';
import { bn } from './locales/bn';

export type Locale = 'en' | 'bn' | 'ar';

export const locales: { code: Locale; name: string; flag: string }[] = [
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'bn', name: 'বাংলা', flag: '🇧🇩' },
    { code: 'ar', name: 'العربية', flag: '🇸🇦' },
];

const dictionaries: Record<Locale, Record<string, string>> = { en: {}, bn, ar };
const listeners = new Set<() => void>();

const read = (): Locale => {
    if (typeof window === 'undefined') return 'en';
    try {
        const value = localStorage.getItem('locale');
        if (value === 'bn' || value === 'ar' || value === 'en') return value;
    } catch {
        // storage can be blocked; fall back to English
    }
    return 'en';
};

let current: Locale = read();

export const applyLocale = (locale: Locale) => {
    if (typeof document === 'undefined') return;
    document.documentElement.lang = locale;
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
};

export const setLocale = (locale: Locale) => {
    current = locale;
    try {
        localStorage.setItem('locale', locale);
    } catch {
        // ignore
    }
    applyLocale(locale);
    listeners.forEach((listener) => listener());
};

export const translate = (text: string, vars?: Record<string, string | number>, locale: Locale = current): string => {
    let out = dictionaries[locale][text] ?? text;
    if (vars) {
        for (const [key, value] of Object.entries(vars)) out = out.replaceAll(`{${key}}`, String(value));
    }
    return out;
};

export function useI18n() {
    const locale = useSyncExternalStore(
        (listener) => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        () => current,
        () => 'en' as Locale,
    );

    return {
        locale,
        dir: locale === 'ar' ? 'rtl' : 'ltr',
        setLocale,
        t: (text: string, vars?: Record<string, string | number>) => translate(text, vars, locale),
    };
}
