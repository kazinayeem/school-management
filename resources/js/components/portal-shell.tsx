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
    FileText,
    LayoutGrid,
    LogOut,
    User,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { PropsWithChildren, useState } from 'react';

const icons: Record<string, LucideIcon> = {
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarDays,
    CalendarOff,
    FileText,
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
    const [open, setOpen] = useState(true);
    const [bell, setBell] = useState(false);
    const [languages, setLanguages] = useState(false);
    const home = menu[0]?.url ?? '/dashboard';
    const sessionLabel = roleLabel === 'Student' ? 'Session 2026' : 'Session';

    return (
        <div className="flex min-h-screen bg-[#f4f6fb] text-slate-800">
            <aside className={`flex shrink-0 flex-col bg-[#0c1222] text-slate-300 transition-all ${open ? 'w-[260px]' : 'w-[76px]'}`}>
                <Link href={home + query} className="flex h-20 items-center px-5 text-white" aria-label={name}>
                    <img
                        src={branding.logoWhite}
                        alt={name}
                        className={`${open ? 'h-11 w-auto max-w-[200px]' : 'h-8 w-8'} object-contain object-left`}
                    />
                </Link>
                {open && <p className="px-5 pb-3 text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase">{caption}</p>}
                <nav className="flex-1 space-y-1 overflow-y-auto px-3">
                    {menu.map((item) => {
                        const Icon = icons[item.icon] ?? LayoutGrid;
                        const href = item.url + query;
                        const active = item.url === home ? path === item.url : path.startsWith(item.url);

                        return (
                            <Link
                                key={item.url}
                                href={href}
                                className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition ${
                                    active ? 'bg-gradient-to-r from-[#6d1167] to-[#4c1d95] text-white shadow-lg shadow-fuchsia-900/20' : 'hover:bg-white/5 hover:text-white'
                                }`}
                            >
                                <Icon className="h-4 w-4 shrink-0" />
                                {open && <span className="truncate">{item.title}</span>}
                            </Link>
                        );
                    })}
                </nav>
                <button type="button" aria-label="Collapse sidebar" onClick={() => setOpen(!open)} className="m-3 flex items-center justify-center rounded-xl py-2 text-lg text-slate-500 hover:bg-white/5 hover:text-white">
                    «
                </button>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-[66px] items-center justify-between gap-3 border-b border-slate-200 bg-white px-5">
                    <p className="text-sm text-slate-500">
                        <Link href={home + query} className="hover:text-slate-800">
                            Home
                        </Link>
                        <span className="px-1">/</span>
                        <span className="font-medium text-slate-800">{title}</span>
                    </p>
                    <div className="flex items-center gap-2">
                        <span className="hidden items-center gap-1.5 rounded-full bg-pink-50 px-3 py-1.5 text-xs font-medium text-[#b31299] sm:inline-flex">
                            <CalendarDays className="h-3.5 w-3.5" /> {sessionLabel}
                        </span>
                        <div className="relative">
                            <button type="button" aria-label="Notifications" onClick={() => setBell(!bell)} className="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                                <Bell className="h-4 w-4" />
                                {notices.length > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">
                                        {notices.length}
                                    </span>
                                )}
                            </button>
                            {bell && (
                                <div className="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3 text-sm shadow-xl">
                                    {notices.length === 0 ? (
                                        <p className="py-4 text-center text-sm text-slate-400">No notices yet.</p>
                                    ) : (
                                        notices.map((notice) => (
                                            <div key={notice.title} className="border-b border-slate-100 py-2 last:border-0">
                                                <div className="font-semibold">{notice.title}</div>
                                                <div className="text-xs text-slate-500">{notice.date}</div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setLanguages(!languages)}
                                className="hidden items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 sm:inline-flex"
                            >
                                <span>🇬🇧</span> English <ChevronDown className="h-3.5 w-3.5" />
                            </button>
                            {languages && (
                                <div className="absolute right-0 z-20 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                                    {branding.languages.map((language) => (
                                        <button
                                            key={language.code}
                                            type="button"
                                            onClick={() => setLanguages(false)}
                                            className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50"
                                        >
                                            <span>{language.flag}</span> {language.name}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="flex items-center gap-2 border-l border-slate-200 pl-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-pink-50 text-sm font-bold text-[#b31299]">
                                {auth.user.name.charAt(0)}
                            </span>
                            <span className="hidden leading-tight sm:block">
                                <span className="block text-sm font-semibold text-slate-800">{auth.user.name}</span>
                                <span className="block text-[11px] text-slate-400">{roleLabel}</span>
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post(route('logout'))}
                            aria-label="Log out"
                            className="inline-flex items-center rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                        >
                            <LogOut className="h-4 w-4" />
                        </button>
                    </div>
                </header>
                <main className="flex-1 p-5">{children}</main>
            </div>
        </div>
    );
}
