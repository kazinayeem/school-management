import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, Moon, Sun, X } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';

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
    const { name, auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [academicsOpen, setAcademicsOpen] = useState(false);
    const [dark, setDark] = useState(false);

    return (
        <div className={dark ? 'dark' : undefined}>
            <div className="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
                <div className="overflow-hidden bg-[#b31299] text-sm text-white">
                    <div className="mx-auto flex max-w-6xl items-center gap-4 px-4 py-2">
                        <span className="shrink-0 text-[11px] font-bold tracking-widest">LATEST NOTICE</span>
                        {ticker && (
                            <Link href={`/notices/${ticker.id}`} className="truncate font-medium hover:underline">
                                • {ticker.title}
                            </Link>
                        )}
                    </div>
                </div>
                <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                    <div className="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
                        <Link href="/" className="text-lg font-extrabold tracking-tight text-[#b31299]">
                            {name}
                        </Link>
                        <nav className="ms-auto hidden items-center gap-5 text-sm font-medium lg:flex">
                            <Link href="/">Home</Link>
                            <Link href="/about">About us</Link>
                            <div className="relative">
                                <button type="button" className="inline-flex items-center gap-1" onClick={() => setAcademicsOpen(!academicsOpen)}>
                                    Academics <ChevronDown className="h-4 w-4" />
                                </button>
                                {academicsOpen && (
                                    <div className="absolute top-8 right-0 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                                        {academics.map(([label, href]) => (
                                            <Link key={href} href={href} className="block rounded-lg px-3 py-2 hover:bg-fuchsia-50 hover:text-[#b31299]" onClick={() => setAcademicsOpen(false)}>
                                                {label}
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <Link href="/teachers">Teachers</Link>
                            <Link href="/gallery">Gallery</Link>
                            <Link href="/notices">Notices</Link>
                            <Link href="/contact">Contact</Link>
                        </nav>
                        <div className="ms-auto flex items-center gap-2 lg:ms-0">
                            <button type="button" aria-label={dark ? 'Switch to light theme' : 'Switch to dark theme'} onClick={() => setDark(!dark)} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                            </button>
                            <Link href={auth.user ? '/app' : '/login'} className="hidden rounded-[10px] bg-[#b31299] px-3 py-2 text-sm font-semibold text-white sm:inline">
                                {auth.user ? 'Dashboard' : 'Parent / Student Login'}
                            </Link>
                            <button type="button" aria-label={open ? 'Close menu' : 'Open menu'} className="rounded-lg p-2 lg:hidden" onClick={() => setOpen(!open)}>
                                {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                            </button>
                        </div>
                    </div>
                    {open && (
                        <div className="space-y-1 border-t border-slate-200 px-4 py-3 text-sm lg:hidden dark:border-slate-800">
                            {[
                                ['Home', '/'],
                                ['About us', '/about'],
                                ['Teachers', '/teachers'],
                                ['Gallery', '/gallery'],
                                ['Notices', '/notices'],
                                ['Events', '/events'],
                                ['Results', '/results'],
                                ['Apply Online', '/admission'],
                                ['Contact', '/contact'],
                            ].map(([label, href]) => (
                                <Link key={href} href={href} className="block rounded-lg px-2 py-2" onClick={() => setOpen(false)}>
                                    {label}
                                </Link>
                            ))}
                        </div>
                    )}
                </header>
                <main>{children}</main>
                <footer className="bg-slate-950 text-slate-300">
                    <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 md:grid-cols-4">
                        <div>
                            <p className="text-lg font-extrabold text-white">{name}</p>
                            <p className="mt-2 text-sm">Education that builds character, not just results.</p>
                        </div>
                        <div>
                            <h4 className="font-semibold text-white">Quick Links</h4>
                            <ul className="mt-3 space-y-2 text-sm">
                                <li><Link href="/notices">Notices</Link></li>
                                <li><Link href="/events">Events</Link></li>
                                <li><Link href="/gallery">Gallery</Link></li>
                                <li><Link href="/contact">Contact Us</Link></li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-semibold text-white">For Students & Parents</h4>
                            <ul className="mt-3 space-y-2 text-sm">
                                <li><Link href="/admission">Online Admission</Link></li>
                                <li><Link href="/results">Check Exam Result</Link></li>
                                <li><Link href="/login">Parent / Student Login</Link></li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-semibold text-white">Contact</h4>
                            <ul className="mt-3 space-y-2 text-sm">
                                <li>House 12, Road 5, Dhanmondi, Dhaka 1205</li>
                                <li><a href="tel:+8801711000000">+880 1711-000000</a></li>
                                <li><a href="mailto:office@demoschool.edu.bd">office@demoschool.edu.bd</a></li>
                            </ul>
                        </div>
                    </div>
                    <div className="border-t border-white/10">
                        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-xs">
                            <p>© {new Date().getFullYear()} {name}. All rights reserved.</p>
                            <div className="flex gap-4">
                                <Link href="/privacy-policy">Privacy policy</Link>
                                <Link href="/terms-and-conditions">Terms & conditions</Link>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    );
}
