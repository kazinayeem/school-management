import { AnimatedPage } from '@/components/motion';
import { LanguageMenu, ThemeToggle } from '@/components/preferences';
import { useI18n } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, ChevronDown, GraduationCap, LayoutDashboard, LogIn, Menu, ShieldCheck, Users, X } from 'lucide-react';
import { PropsWithChildren, useEffect, useRef, useState } from 'react';

const logins = [
    { role: 'student', label: 'Student login', note: 'Results, homework, fees', icon: GraduationCap },
    { role: 'parent', label: 'Parent login', note: 'Follow your child', icon: Users },
    { role: 'teacher', label: 'Teacher & staff', note: 'Classes and HR', icon: BookOpen },
    { role: 'admin', label: 'Administrator', note: 'School management', icon: ShieldCheck },
];

function LoginMenu() {
    const { auth } = usePage<SharedData>().props;
    const { t } = useI18n();
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const close = (e: MouseEvent) => !box.current?.contains(e.target as Node) && setOpen(false);
        const esc = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', esc);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', esc);
        };
    }, []);

    if (auth.user) {
        const home = auth.user.role === 'student' ? '/student' : auth.user.role === 'parent' ? '/parent' : '/app';

        return (
            <Link href={home} className="inline-flex items-center gap-2 rounded-full bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark">
                <LayoutDashboard className="h-4 w-4" /> <span className="hidden sm:inline">{t('Dashboard')}</span>
            </Link>
        );
    }

    return (
        <div className="relative" ref={box}>
            <button type="button" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen(!open)} className="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-brand to-brand-2 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:shadow-lg focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:outline-none">
                <LogIn className="h-4 w-4" /> {t('Login')} <ChevronDown className={`h-4 w-4 transition ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div role="menu" className="absolute end-0 top-full z-50 mt-3 w-72 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl">
                    {logins.map(({ role, label, note, icon: Icon }) => (
                        <Link key={role} role="menuitem" href={`/login?role=${role}`} className="flex items-center gap-3 rounded-xl px-3 py-2.5 transition hover:bg-slate-100" onClick={() => setOpen(false)}>
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand"><Icon className="h-5 w-5" /></span>
                            <span className="min-w-0"><span className="block text-sm font-semibold">{t(label)}</span><span className="block truncate text-xs text-slate-500">{t(note)}</span></span>
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}

const academics = [
    ['Facilities', '/facilities'],
    ["Principal's message", '/principal-message'],
    ['Mission & vision', '/mission-and-vision'],
    ['School uniform', '/school-uniform'],
];

export function PublicShell({
    ticker,
    children,
}: PropsWithChildren<{ ticker?: { id: number; title: string } | null }>) {
    const { name, auth, site, branding } = usePage<SharedData & { site?: Record<string, string> | null }>().props;
    const [open, setOpen] = useState(false);
    const [academicsOpen, setAcademicsOpen] = useState(false);
    const { t } = useI18n();

    return (
        <div>
            <div className="themed min-h-screen bg-slate-50 text-slate-800">
                <div className="overflow-hidden bg-brand text-sm text-white">
                    <div className="mx-auto flex max-w-6xl items-center gap-4 px-4 py-2">
                        <span className="shrink-0 text-[11px] font-bold tracking-widest">{t('LATEST NOTICE')}</span>
                        {ticker && (
                            <Link href={`/notices/${ticker.id}`} className="truncate font-medium hover:underline">
                                • {ticker.title}
                            </Link>
                        )}
                    </div>
                </div>
                <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div className="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
                        <Link href="/" className="flex min-w-0 items-center gap-2 text-lg font-extrabold tracking-tight text-brand" aria-label={name}>
                            <img src={branding.logo} alt="" className="h-10 w-auto max-w-[140px] object-contain dark:hidden" />
                            <img src={branding.logoWhite} alt="" className="hidden h-10 w-auto max-w-[140px] object-contain dark:block" />
                            <span className="hidden truncate sm:inline lg:hidden 2xl:inline">{name}</span>
                        </Link>
                        <nav className="ms-auto hidden items-center gap-4 text-sm font-medium whitespace-nowrap xl:gap-5 lg:flex">
                            <Link href="/">{t('Home')}</Link>
                            <Link href="/about">{t('About us')}</Link>
                            <div className="relative">
                                <button type="button" className="inline-flex items-center gap-1" onClick={() => setAcademicsOpen(!academicsOpen)}>
                                    {t('Academics')} <ChevronDown className="h-4 w-4" />
                                </button>
                                {academicsOpen && (
                                    <div className="absolute top-8 end-0 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                                        {academics.map(([label, href]) => (
                                            <Link key={href} href={href} className="block rounded-lg px-3 py-2 hover:bg-fuchsia-50 hover:text-brand" onClick={() => setAcademicsOpen(false)}>
                                                {t(label)}
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <Link href="/teachers">{t('Teachers')}</Link>
                            <Link href="/fees">{t('Fees')}</Link>
                            <Link href="/gallery">{t('Gallery')}</Link>
                            <Link href="/notices">{t('Notices')}</Link>
                            <Link href="/contact">{t('Contact')}</Link>
                        </nav>
                        <div className="ms-auto flex items-center gap-2 lg:ms-0">
                            <LanguageMenu className="hidden sm:block" />
                            <ThemeToggle />
                            <LoginMenu />
                            <button type="button" aria-label={open ? 'Close menu' : 'Open menu'} className="rounded-lg p-2 lg:hidden" onClick={() => setOpen(!open)}>
                                {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                            </button>
                        </div>
                    </div>
                    {open && (
                        <div className="space-y-1 border-t border-slate-200 px-4 py-3 text-sm lg:hidden">
                            {[
                                ['Home', '/'],
                                ['About us', '/about'],
                                ['Teachers', '/teachers'],
                                ['Fees & policies', '/fees'],
                                ['Gallery', '/gallery'],
                                ['Notices', '/notices'],
                                ['Events', '/events'],
                                ['Results', '/results'],
                                ['Apply Online', '/admission'],
                                ['Contact', '/contact'],
                            ].map(([label, href]) => (
                                <Link key={href} href={href} className="block rounded-lg px-2 py-2" onClick={() => setOpen(false)}>
                                    {t(label)}
                                </Link>
                            ))}
                        </div>
                    )}
                </header>
                <main><AnimatedPage>{children}</AnimatedPage></main>
                <footer className="keep-scale bg-slate-950 text-slate-300">
                    <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 md:grid-cols-4">
                        <div>
                            <img src={branding.logoWhite} alt="" className="mb-2 h-10 w-auto max-w-[160px] object-contain" />
                            <p className="text-lg font-extrabold text-white">{name}</p>
                            <p className="mt-2 text-sm">{site?.footer ? site.footer : t('Education that builds character, not just results.')}</p>
                        </div>
                        <div>
                            <h4 className="font-semibold text-white">{t('Quick Links')}</h4>
                            <ul className="mt-3 space-y-2 text-sm">
                                <li><Link href="/notices">{t('Notices')}</Link></li>
                                <li><Link href="/events">{t('Events')}</Link></li>
                                <li><Link href="/gallery">{t('Gallery')}</Link></li>
                                <li><Link href="/fees">{t('Fees & policies')}</Link></li>
                                <li><Link href="/contact">{t('Contact Us')}</Link></li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-semibold text-white">{t('For Students & Parents')}</h4>
                            <ul className="mt-3 space-y-2 text-sm">
                                <li><Link href="/admission">{t('Online Admission')}</Link></li>
                                <li><Link href="/results">{t('Check Exam Result')}</Link></li>
                                <li><Link href="/login?role=student">{t('Student login')}</Link></li>
                                <li><Link href="/login?role=parent">{t('Parent login')}</Link></li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-semibold text-white">{t('Contact')}</h4>
                            <ul className="mt-3 space-y-2 text-sm">
                                <li>{site?.address ?? 'House 12, Road 5, Dhanmondi, Dhaka 1205'}</li>
                                <li><a href={`tel:${(site?.phone ?? '+8801711000000').replace(/[^+\d]/g, '')}`} dir="ltr">{site?.phone ?? '+880 1711-000000'}</a></li>
                                <li><a href={`mailto:${site?.email ?? 'office@borno.edu.bd'}`}>{site?.email ?? 'office@borno.edu.bd'}</a></li>
                            </ul>
                        </div>
                    </div>
                    <div className="border-t border-white/10">
                        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-xs">
                            <p>© {new Date().getFullYear()} {name}. All rights reserved. · Made by <a href="https://bornosoft.bd" target="_blank" rel="noreferrer" className="font-semibold text-white hover:underline">bornosoft.bd</a></p>
                            <div className="flex gap-4">
                                <Link href="/privacy-policy">{t('Privacy policy')}</Link>
                                <Link href="/terms-and-conditions">{t('Terms & conditions')}</Link>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    );
}
