import { Avatar } from '@/components/avatar';
import { CommsButtons } from '@/components/comms-header';
import { MadeBy } from '@/components/made-by';
import { AnimatedPage } from '@/components/motion';
import { LanguageMenu, ThemeToggle } from '@/components/preferences';
import { useI18n } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'motion/react';
import {
    Bell,
    ChevronDown,
    LogOut,
    PanelLeft,
    Search,
        Award,
    Banknote,
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarClock,
    CalendarOff,
    ClipboardList,
    Contact,
    CreditCard,
    Globe,
    IdCard,
    LifeBuoy,
    Mail,
    GraduationCap,
    History,
    LayoutGrid,
    Library,
    Megaphone,
    MessageSquare,
    NotebookPen,
    School,
    Settings,
    UserCheck,
    UserCog,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { PropsWithChildren, useEffect, useMemo, useState } from 'react';

const icons: Record<string, LucideIcon> = {
    IdCard,
    LifeBuoy,
    Mail,
    Award,
    Banknote,
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarClock,
    CalendarOff,
    ClipboardList,
    Contact,
    CreditCard,
    Globe,
    GraduationCap,
    History,
    LayoutGrid,
    Library,
    Megaphone,
    MessageSquare,
    NotebookPen,
    School,
    Settings,
    UserCheck,
    UserCog,
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
    const [open, setOpen] = useState(false);
    const [narrow, setNarrow] = useState(false);
    const [search, setSearch] = useState(false);
    const [query, setQuery] = useState('');
    const { t } = useI18n();

    useEffect(() => router.on('navigate', () => setOpen(false)), []);
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
    const results = links.filter((link) => `${link.label} ${t(link.label)}`.toLowerCase().includes(query.toLowerCase())).slice(0, 8);

    return (
        <div className="admin-shell flex min-h-screen bg-slate-50">
            {open && <button aria-label="Close menu" className="fixed inset-0 z-30 bg-black/50 lg:hidden" onClick={() => setOpen(false)} />}
            <aside
                className={`admin-sidebar fixed inset-y-0 start-0 z-40 flex flex-col border-e border-slate-200 bg-white text-slate-600 transition-all duration-200 ${
                    narrow ? 'w-[72px]' : 'w-[260px]'
                } ${open ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full lg:translate-x-0'}`}
            >
                <div className="flex h-20 shrink-0 items-center px-5">
                    <Link href="/app" className="flex min-w-0 items-center" aria-label={name}>
                        <>
<img src={branding.logo} alt={name} className={`${narrow ? 'h-8 w-8 object-cover object-left' : 'h-11 w-auto max-w-[200px]'} object-contain object-left` + ' dark:hidden'} />
<img src={branding.logoWhite} alt={name} className={`${narrow ? 'h-8 w-8 object-cover object-left' : 'h-11 w-auto max-w-[200px]'} object-contain object-left` + ' hidden dark:block'} />
</>
                    </Link>
                </div>
                <nav className="sidebar-scroll flex-1 overflow-y-auto px-3 pt-3 pb-4">
                    {navigation.map((group) => (
                        <div key={group.caption ?? 'home'} className={group.caption ? 'pt-3' : undefined}>
                            {group.caption && !narrow && (
                                <p className="px-3 pb-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase">{t(group.caption)}</p>
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
                                                aria-expanded={Boolean(isOpen)}
                                                onClick={() => setExpanded(isOpen ? null : item.label)}
                                                className={`mt-0.5 flex w-full items-center gap-2.5 rounded-lg px-3 py-1.5 text-[13px] transition-colors hover:bg-slate-100 hover:text-slate-900 ${
                                                    active ? 'bg-gradient-to-r from-brand to-brand-2 shadow-sm font-medium text-white hover:text-white' : ''
                                                }`}
                                            >
                                                <Icon className="h-4 w-4 shrink-0" />
                                                {!narrow && (
                                                    <>
                                                        <span className="flex-1 truncate text-start">{t(item.label)}</span>
                                                        <ChevronDown className={`h-3.5 w-3.5 transition ${isOpen ? 'rotate-180' : ''}`} />
                                                    </>
                                                )}
                                            </button>
                                            <AnimatePresence initial={false}>
                                            {isOpen && !narrow && (
                                                <motion.div
                                                    initial={{ height: 0, opacity: 0 }}
                                                    animate={{ height: 'auto', opacity: 1 }}
                                                    exit={{ height: 0, opacity: 0 }}
                                                    transition={{ duration: 0.18, ease: 'easeOut' }}
                                                    className="mt-1 ms-4 space-y-0.5 overflow-hidden border-s border-slate-200 ps-3"
                                                >
                                                    {item.children.map((child) => (
                                                        <Link
                                                            key={child.href}
                                                            href={child.href}
                                                            className={`block truncate rounded-lg px-3 py-1.5 text-[13px] hover:bg-slate-100 hover:text-slate-900 ${
                                                                path === child.href ? 'bg-gradient-to-r from-brand to-brand-2 shadow-sm text-white hover:text-white' : ''
                                                            }`}
                                                        >
                                                            {t(child.label)}
                                                        </Link>
                                                    ))}
                                                </motion.div>
                                            )}
                                            </AnimatePresence>
                                        </div>
                                    );
                                }

                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href!}
                                        className={`mt-0.5 flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-[13px] transition-colors hover:bg-slate-100 hover:text-slate-900 ${
                                            path === item.href ? 'bg-gradient-to-r from-brand to-brand-2 shadow-sm text-white hover:text-white' : ''
                                        }`}
                                    >
                                        <Icon className="h-4 w-4 shrink-0" />
                                        {!narrow && t(item.label)}
                                    </Link>
                                );
                            })}
                        </div>
                    ))}
                </nav>
                <button type="button" aria-label="Collapse sidebar" onClick={() => setNarrow(!narrow)} className="m-3 hidden items-center justify-center rounded-lg py-2 text-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:flex">
                    <span className="rtl:rotate-180">«</span>
                </button>
            </aside>

            <div className={`flex min-w-0 flex-1 flex-col ${narrow ? 'lg:ps-[72px]' : 'lg:ps-[260px]'}`}>
                <header className="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-slate-200 bg-white/90 px-2 backdrop-blur sm:gap-4 sm:px-6">
                    <button aria-label="Open menu" onClick={() => setOpen(!open)} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                        <PanelLeft className="h-5 w-5" />
                    </button>
                    <div className="relative ms-auto flex shrink-0 items-center gap-1 sm:gap-3">
                        <LanguageMenu className="hidden sm:block" />
                        <button aria-label={t('Search admin pages')} onClick={() => setSearch(!search)} className="rounded-lg border border-slate-200 bg-white p-2 text-slate-500 shadow-sm hover:bg-slate-100">
                            <Search className="h-4 w-4" />
                        </button>
                        <ThemeToggle />
                        <CommsButtons />
                        <div className="flex items-center gap-1 border-s border-slate-200 ps-1 sm:gap-3 sm:ps-3">
                            <Avatar name={auth.user.name} src={auth.user.photo_url} className="h-9 w-9 text-sm" />
                            <span className="hidden leading-tight md:block">
                                <span className="block text-sm font-medium text-slate-900">{auth.user.name}</span>
                                <span className="block text-xs text-slate-400">{t(auth.user.role === 'admin' ? 'Super Admin' : auth.user.role === 'teacher' ? 'Teacher' : auth.user.role === 'staff' ? 'Staff' : roleLabel)}</span>
                            </span>
                            <button aria-label="Log out" onClick={() => router.post(route('logout'))} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                                <LogOut className="h-4 w-4" />
                            </button>
                        </div>
                        {search && (
                            <div className="absolute top-12 end-0 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                                <input
                                    autoFocus
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder={t('Search admin pages')}
                                    className="w-full border-b border-slate-200 px-4 py-3 text-sm outline-none"
                                />
                                <div className="max-h-[420px] overflow-y-auto p-2">
                                    {results.length === 0 ? (
                                        <p className="px-4 py-10 text-center text-sm text-slate-400">{t('No matching pages')}</p>
                                    ) : (
                                        results.map((link) => (
                                            <Link key={link.href} href={link.href} onClick={() => setSearch(false)} className="block rounded-lg px-3 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-700">
                                                {t(link.label)}
                                            </Link>
                                        ))
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </header>
                <main className="flex-1 p-4 text-slate-800 sm:p-6"><AnimatedPage>{children}</AnimatedPage></main>
                <MadeBy className="pb-4" />
            </div>
        </div>
    );
}
