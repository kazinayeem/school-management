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
    GraduationCap,
    LayoutGrid,
    LogOut,
    PanelLeft,
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
    GraduationCap,
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
    const { auth, menu, name } = usePage<SharedData>().props;
    const path = usePage().url.split('?')[0];
    const [open, setOpen] = useState(true);
    const [bell, setBell] = useState(false);
    const home = menu[0]?.url ?? '/dashboard';

    return (
        <div className="flex min-h-screen bg-[#f4f6fb] text-slate-800">
            <aside className={`flex shrink-0 flex-col bg-[#0c1222] text-slate-300 transition-all ${open ? 'w-64' : 'w-[76px]'}`}>
                <Link href={home + query} className="flex items-center gap-2 px-4 py-5 text-white">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#890e75] to-[#ec46d1]">
                        <GraduationCap className="h-5 w-5" />
                    </span>
                    {open && <span className="text-lg font-extrabold tracking-tight">{name}</span>}
                </Link>
                {open && <p className="px-5 pb-2 text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase">{caption}</p>}
                <nav className="flex-1 space-y-1 overflow-y-auto px-3">
                    {menu.map((item) => {
                        const Icon = icons[item.icon] ?? LayoutGrid;
                        const href = item.url + query;
                        const active = item.url === home ? path === item.url : path.startsWith(item.url);

                        return (
                            <Link
                                key={item.url}
                                href={href}
                                className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium ${
                                    active ? 'bg-gradient-to-r from-[#7a1578] to-[#5b21b6] text-white shadow-lg shadow-fuchsia-900/30' : 'hover:bg-white/5'
                                }`}
                            >
                                <Icon className="h-4 w-4 shrink-0" />
                                {open && item.title}
                            </Link>
                        );
                    })}
                </nav>
                <button type="button" onClick={() => setOpen(!open)} className="m-3 flex items-center gap-2 rounded-xl px-3 py-2 text-xs text-slate-400 hover:bg-white/5">
                    <PanelLeft className="h-4 w-4" />
                    {open && 'Collapse sidebar'}
                </button>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-3">
                    <p className="text-sm text-slate-500">
                        <Link href={home + query} className="hover:text-slate-800">
                            Home
                        </Link>
                        <span className="px-1">/</span>
                        <span className="font-medium text-slate-800">{title}</span>
                    </p>
                    <div className="flex items-center gap-2">
                        <span className="hidden rounded-full bg-fuchsia-50 px-3 py-1 text-xs font-semibold text-fuchsia-800 sm:inline">Session 2026</span>
                        <div className="relative">
                            <button type="button" aria-label="Notifications" onClick={() => setBell(!bell)} className="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                                <Bell className="h-4 w-4" />
                                {notices.length > 0 && <span className="absolute top-1 right-1 h-2 w-2 rounded-full bg-fuchsia-600" />}
                            </button>
                            {bell && (
                                <div className="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3 text-sm shadow-xl">
                                    {notices.map((notice) => (
                                        <div key={notice.title} className="border-b border-slate-100 py-2 last:border-0">
                                            <div className="font-semibold">{notice.title}</div>
                                            <div className="text-xs text-slate-500">{notice.date}</div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="flex items-center gap-2 rounded-full border border-slate-200 py-1 pr-3 pl-1">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-fuchsia-100 text-sm font-bold text-fuchsia-800">
                                {auth.user.name.charAt(0)}
                            </span>
                            <span className="hidden leading-tight sm:block">
                                <span className="block text-sm font-semibold">{auth.user.name}</span>
                                <span className="block text-[11px] text-slate-400">{roleLabel}</span>
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post(route('logout'))}
                            className="inline-flex items-center gap-1 rounded-lg px-2 py-2 text-sm text-slate-500 hover:bg-slate-100"
                        >
                            <LogOut className="h-4 w-4" />
                            <span className="hidden md:inline">Log out</span>
                        </button>
                    </div>
                </header>
                <main className="flex-1 p-5">{children}</main>
            </div>
        </div>
    );
}
