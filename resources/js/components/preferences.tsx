import { useAppearance } from '@/hooks/use-appearance';
import { locales, useI18n } from '@/lib/i18n';
import { ChevronDown, Moon, Sun } from 'lucide-react';
import { useState } from 'react';

export function useIsDark() {
    const { appearance, updateAppearance } = useAppearance();
    const dark =
        appearance === 'dark' || (appearance === 'system' && typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    return { dark, toggle: () => updateAppearance(dark ? 'light' : 'dark') };
}

export function LanguageMenu({ className = '' }: { className?: string }) {
    const { locale, setLocale } = useI18n();
    const [open, setOpen] = useState(false);
    const current = locales.find((l) => l.code === locale)!;

    return (
        <div className={`relative ${className}`}>
            <button
                type="button"
                aria-label="Change language"
                onClick={() => setOpen(!open)}
                className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-50"
            >
                <span>{current.flag}</span> {current.name} <ChevronDown className="h-3.5 w-3.5" />
            </button>
            {open && (
                <div className="absolute end-0 z-50 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                    {locales.map((l) => (
                        <button
                            key={l.code}
                            type="button"
                            onClick={() => {
                                setLocale(l.code);
                                setOpen(false);
                            }}
                            className={`flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm hover:bg-slate-50 ${l.code === locale ? 'font-semibold text-fuchsia-700' : ''}`}
                        >
                            <span>{l.flag}</span> {l.name}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export function ThemeToggle() {
    const { dark, toggle } = useIsDark();

    return (
        <button
            type="button"
            aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
            onClick={toggle}
            className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm hover:bg-slate-100"
        >
            {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
        </button>
    );
}

export function PreferenceBar() {
    return (
        <div className="flex items-center gap-2">
            <LanguageMenu />
            <ThemeToggle />
        </div>
    );
}
