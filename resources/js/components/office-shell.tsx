import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarDays,
    CalendarOff,
    ChevronDown,
    GraduationCap,
    LayoutGrid,
    LogOut,
    Megaphone,
    Moon,
    PanelLeft,
    School,
    Search,
    Settings,
    Sun,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { PropsWithChildren, useMemo, useState } from 'react';

const icons: Record<string, LucideIcon> = {
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarDays,
    CalendarOff,
    GraduationCap,
    LayoutGrid,
    Megaphone,
    School,
    Settings,
    Users,
    Wallet,
};

type Child = { label: string; href: string };
type Item = { label: string; href?: string; icon?: string; children?: Child[] };
type Group = { caption: string | null; items: Item[] };

export function OfficeShell({
    navigation,
    roleLabel,
    children,
}: PropsWithChildren<{ navigation: Group[]; roleLabel: string }>) {
    const { auth, name, branding } = usePage<SharedData>().props;
    const path = usePage().url.split('?')[0];
    const [open, setOpen] = useState(true);
    const [narrow, setNarrow] = useState(false);
    const [search, setSearch] = useState(false);
    const [query, setQuery] = useState('');
    const [dark, setDark] = useState(false);
    const [languages, setLanguages] = useState(false);
    const [expanded, setExpanded] = useState<string | null>(null);

    const links = useMemo(
        () =>
            navigation.flatMap((group) =>
                group.items.flatMap((item) => [
                    ...(item.href ? [{ label: item.label, href: item.href }] : []),
                    ...(item.children ?? []),
                ]),
            ),
        [navigation],
    );
    const results = links.filter((link) => link.label.toLowerCase().includes(query.toLowerCase())).slice(0, 8);

    return (
        <div className={`admin-shell flex min-h-screen ${dark ? 'bg-slate-950' : 'bg-slate-50'}`}>
            {open && <button aria-label="Close menu" className="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" onClick={() => setOpen(false)} />}
            <aside
                className={`admin-sidebar fixed inset-y-0 left-0 z-40 flex flex-col bg-[#0c1222] text-slate-300 transition-all duration-200 ${
                    narrow ? 'w-[72px]' : 'w-[260px]'
                } ${open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}
            >
                <div className="flex h-20 shrink-0 items-center px-5">
                    <Link href="/app" className="flex min-w-0 items-center text-white" aria-label={name}>
                        <img src={branding.logoWhite} alt={name} className={`${narrow ? 'h-8 w-8 object-cover object-left' : 'h-11 w-auto max-w-[200px]'} object-contain object-left`} />
                    </Link>
                </div>
                <nav className="sidebar-scroll flex-1 overflow-y-auto px-3 pt-3 pb-4">
                    {navigation.map((group) => (
                        <div key={group.caption ?? 'home'} className={group.caption ? 'pt-3' : undefined}>
                            {group.caption && !narrow && (
                                <p className="px-3 pb-1 text-[10px] font-semibold tracking-widest text-slate-500 uppercase">{group.caption}</p>
                            )}
                            {group.items.map((item) => {
                                const Icon = icons[item.icon ?? ''] ?? LayoutGrid;
                                const active = item.href ? path === item.href : item.children?.some((child) => path === child.href);
                                const isOpen = expanded === item.label || active;

                                if (item.children) {
                                    return (
                                        <div key={item.label}>
                                            <button
                                                type="button"
                                                onClick={() => setExpanded(isOpen ? null : item.label)}
                                                className={`mt-0.5 flex w-full items-center gap-2.5 rounded-lg px-3 py-1.5 text-[13px] transition-colors hover:bg-slate-800 hover:text-white ${
                                                    active ? 'bg-gradient-to-r from-[#6d1167] to-[#4c1d95] font-medium text-white' : ''
                                                }`}
                                            >
                                                <Icon className="h-4 w-4 shrink-0" />
                                                {!narrow && (
                                                    <>
                                                        <span className="flex-1 truncate text-start">{item.label}</span>
                                                        <ChevronDown className={`h-3.5 w-3.5 transition ${isOpen ? 'rotate-180' : ''}`} />
                                                    </>
                                                )}
                                            </button>
                                            {isOpen && !narrow && (
                                                <div className="mt-1 ml-4 space-y-0.5 border-l border-slate-800 pl-3">
                                                    {item.children.map((child) => (
                                                        <Link
                                                            key={child.href}
                                                            href={child.href}
                                                            className={`block truncate rounded-lg px-3 py-1.5 text-[13px] hover:bg-slate-800 hover:text-white ${
                                                                path === child.href ? 'bg-gradient-to-r from-[#6d1167] to-[#4c1d95] text-white' : ''
                                                            }`}
                                                        >
                                                            {child.label}
                                                        </Link>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    );
                                }

                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href!}
                                        className={`mt-0.5 flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-[13px] transition-colors hover:bg-slate-800 hover:text-white ${
                                            path === item.href ? 'bg-gradient-to-r from-[#6d1167] to-[#4c1d95] text-white' : ''
                                        }`}
                                    >
                                        <Icon className="h-4 w-4 shrink-0" />
                                        {!narrow && item.label}
                                    </Link>
                                );
                            })}
                        </div>
                    ))}
                </nav>
                <button type="button" aria-label="Collapse sidebar" onClick={() => setNarrow(!narrow)} className="m-3 hidden items-center justify-center rounded-lg py-2 text-lg text-slate-500 hover:bg-slate-800 hover:text-white lg:flex">
                    «
                </button>
            </aside>

            <div className={`flex min-w-0 flex-1 flex-col ${narrow ? 'lg:pl-[72px]' : 'lg:pl-[260px]'}`}>
                <header className="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-slate-200 bg-white/90 px-2 backdrop-blur sm:gap-4 sm:px-6">
                    <button aria-label="Open menu" onClick={() => setOpen(!open)} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                        <PanelLeft className="h-5 w-5" />
                    </button>
                    <div className="relative ms-auto flex shrink-0 items-center gap-1 sm:gap-3">
                        <div className="relative hidden sm:block">
                            <button type="button" onClick={() => setLanguages(!languages)} className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-50">
                                <span>🇬🇧</span> English <ChevronDown className="h-3.5 w-3.5" />
                            </button>
                            {languages && (
                                <div className="absolute right-0 z-30 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                                    {branding.languages.map((language) => (
                                        <button key={language.code} type="button" onClick={() => setLanguages(false)} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50">
                                            <span>{language.flag}</span> {language.name}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <button aria-label="Search admin pages" onClick={() => setSearch(!search)} className="rounded-lg border border-slate-200 bg-white p-2 text-slate-500 shadow-sm hover:bg-slate-100">
                            <Search className="h-4 w-4" />
                        </button>
                        <button aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'} onClick={() => setDark(!dark)} className="admin-theme-toggle relative flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm hover:bg-slate-100">
                            {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                        </button>
                        <button aria-label="Notifications" className="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                            <Bell className="h-4 w-4" />
                        </button>
                        <div className="flex items-center gap-1 border-s border-slate-200 ps-1 sm:gap-3 sm:ps-3">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                                {auth.user.name.charAt(0)}
                            </span>
                            <span className="hidden leading-tight md:block">
                                <span className="block text-sm font-medium text-slate-900">{auth.user.name}</span>
                                <span className="block text-xs text-slate-400">{roleLabel}</span>
                            </span>
                            <button aria-label="Log out" onClick={() => router.post(route('logout'))} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                                <LogOut className="h-4 w-4" />
                            </button>
                        </div>
                        {search && (
                            <div className="absolute top-12 right-0 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                                <input
                                    autoFocus
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Search admin pages"
                                    className="w-full border-b border-slate-200 px-4 py-3 text-sm outline-none"
                                />
                                <div className="max-h-[420px] overflow-y-auto p-2">
                                    {results.length === 0 ? (
                                        <p className="px-4 py-10 text-center text-sm text-slate-400">No matching pages</p>
                                    ) : (
                                        results.map((link) => (
                                            <Link key={link.href} href={link.href} onClick={() => setSearch(false)} className="block rounded-lg px-3 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-700">
                                                {link.label}
                                            </Link>
                                        ))
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </header>
                <main className={`flex-1 p-4 sm:p-6 ${dark ? 'text-slate-100' : 'text-slate-800'}`}>{children}</main>
            </div>
        </div>
    );
}
