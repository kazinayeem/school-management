import { Avatar } from '@/components/avatar';
import { CommsButtons } from '@/components/comms-header';
import { MadeBy } from '@/components/made-by';
import { AnimatedPage } from '@/components/motion';
import { LanguageMenu, ThemeToggle } from '@/components/preferences';
import { useI18n } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarDays,
    CalendarOff,
    FileText,
    IdCard,
    LifeBuoy,
    Mail,
    LayoutGrid,
    PanelLeft,
    LogOut,
    User,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { PropsWithChildren, useEffect, useState } from 'react';

const icons: Record<string, LucideIcon> = {
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarDays,
    CalendarOff,
    FileText,
    IdCard,
    LifeBuoy,
    Mail,
    LayoutGrid,
    User,
    Users,
    Wallet,
};

type Notice = { title: string; body: string; date: string };

export function PortalShell({
    caption,
    roleLabel,
    title,
    notices,
    query = '',
    children,
}: PropsWithChildren<{ caption: string; roleLabel: string; title: string; notices: Notice[]; query?: string }>) {
    const { auth, menu, name, branding } = usePage<SharedData>().props;
    const path = usePage().url.split('?')[0];
    const [open, setOpen] = useState(false);
    const [narrow, setNarrow] = useState(false);
    const [bell, setBell] = useState(false);
    const { t } = useI18n();
    const home = menu[0]?.url ?? '/dashboard';
    const sessionLabel = roleLabel === 'Student' ? 'Session 2026' : 'Session';

    // On a phone the menu is a drawer that closes after you pick a page.
    useEffect(() => router.on('navigate', () => setOpen(false)), []);

    return (
        <div className="themed flex min-h-screen bg-[#f4f6fb] text-slate-800">
            {open && <button aria-label={t('Close menu')} className="fixed inset-0 z-30 bg-black/50 lg:hidden" onClick={() => setOpen(false)} />}
            <aside
                className={`admin-sidebar fixed inset-y-0 start-0 z-40 flex flex-col border-e border-slate-200 bg-white text-slate-600 transition-all duration-200 ${narrow ? 'w-[76px]' : 'w-[260px]'} ${
                    open ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full lg:translate-x-0'
                }`}
            >
                <Link href={home + query} className="flex h-20 shrink-0 items-center px-5" aria-label={name}>
                    <>
<img src={branding.logo} alt={name} className={`${narrow ? 'h-8 w-8' : 'h-11 w-auto max-w-[200px]'} object-contain object-left` + ' dark:hidden'} />
<img src={branding.logoWhite} alt={name} className={`${narrow ? 'h-8 w-8' : 'h-11 w-auto max-w-[200px]'} object-contain object-left` + ' hidden dark:block'} />
</>
                </Link>
                {!narrow && <p className="px-5 pb-3 text-[11px] font-semibold tracking-[0.16em] text-slate-400 uppercase">{t(caption)}</p>}
                <nav className="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                    {menu.map((item) => {
                        const Icon = icons[item.icon] ?? LayoutGrid;
                        const href = item.url + query;
                        const active = item.url === home ? path === item.url : path.startsWith(item.url);

                        return (
                            <Link
                                key={item.url}
                                href={href}
                                aria-current={active ? 'page' : undefined}
                                className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors hover:bg-slate-100 hover:text-slate-900 ${
                                    active ? 'bg-gradient-to-r from-brand to-brand-2 shadow-sm font-medium text-white hover:text-white' : ''
                                }`}
                            >
                                <Icon className="h-4 w-4 shrink-0" />
                                {!narrow && <span className="truncate">{t(item.title)}</span>}
                            </Link>
                        );
                    })}
                </nav>
                <button type="button" aria-label={t('Collapse sidebar')} onClick={() => setNarrow(!narrow)} className="m-3 hidden items-center justify-center rounded-lg py-2 text-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:flex">
                    <span className="rtl:rotate-180">«</span>
                </button>
            </aside>

            <div className={`flex min-w-0 flex-1 flex-col ${narrow ? 'lg:ps-[76px]' : 'lg:ps-[260px]'}`}>
                <header className="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-slate-200 bg-white/90 px-3 backdrop-blur sm:gap-4 sm:px-6">
                    <button aria-label={t('Open menu')} onClick={() => setOpen(!open)} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                        <PanelLeft className="h-5 w-5" />
                    </button>
                    <div className="min-w-0">
                        <h1 className="truncate text-base font-semibold text-slate-900">{t(title)}</h1>
                        <p className="hidden text-xs text-slate-400 sm:block">{t(sessionLabel)}</p>
                    </div>
                    <div className="relative ms-auto flex shrink-0 items-center gap-1 sm:gap-3">
                        <LanguageMenu className="hidden sm:block" />
                        <ThemeToggle />
                        <CommsButtons />
                        <div className="relative">
                            <button type="button" title={t('School notices')} aria-label={t('Notices')} aria-expanded={bell} onClick={() => setBell(!bell)} className="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                                <Bell className="h-4 w-4" />
                                {notices.length > 0 && <span className="absolute end-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500" />}
                            </button>
                            {bell && (
                                <div className="absolute end-0 z-30 mt-2 w-80 max-w-[85vw] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                                    <p className="border-b border-slate-100 px-4 py-3 text-sm font-semibold">{t('Notices')}</p>
                                    <ul className="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                                        {notices.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-400">{t('Nothing new.')}</li>}
                                        {notices.map((n, i) => (
                                            <li key={i} className="px-4 py-3">
                                                <p className="text-sm font-medium">{n.title}</p>
                                                <p className="mt-0.5 line-clamp-2 text-xs text-slate-500">{n.body}</p>
                                                <p className="mt-1 text-[11px] text-slate-400">{n.date}</p>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                        <div className="flex items-center gap-2 border-s border-slate-200 ps-2 sm:gap-3 sm:ps-3">
                            <Avatar name={auth.user.name} src={auth.user.photo_url} className="h-8 w-8 text-sm" />
                            <span className="hidden leading-tight md:block">
                                <span className="block text-sm font-medium text-slate-900">{auth.user.name}</span>
                                <span className="block text-xs text-slate-400">{t(roleLabel)}</span>
                            </span>
                            <button aria-label={t('Log out')} onClick={() => router.post(route('logout'))} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                                <LogOut className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </header>
                <main className="flex-1 p-5">
                    <AnimatedPage>{children}</AnimatedPage>
                </main>
                <MadeBy className="pb-4" />
            </div>
        </div>
    );
}
