import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

export type Stat = { label: string; value: string; hint: string };
export type Notice = { title: string; date: string };

export function DashShell({ title, subtitle, children }: PropsWithChildren<{ title: string; subtitle: string }>) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold">{title}</h1>
                    <p className="text-muted-foreground text-sm">{subtitle}</p>
                </div>
                {children}
            </div>
        </AppLayout>
    );
}

export function StatGrid({ stats }: { stats: Stat[] }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {stats.map((s, i) => (
                <div
                    key={s.label}
                    className={`rounded-xl p-5 text-white shadow ${
                        ['bg-fuchsia-700', 'bg-violet-600', 'bg-pink-600', 'bg-purple-700'][i % 4]
                    }`}
                >
                    <div className="text-sm opacity-80">{s.label}</div>
                    <div className="mt-1 text-3xl font-bold">{s.value}</div>
                    <div className="mt-1 text-xs opacity-80">{s.hint}</div>
                </div>
            ))}
        </div>
    );
}

export function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="border-sidebar-border/70 rounded-xl border p-5">
            <h2 className="mb-3 font-semibold">{title}</h2>
            {children}
        </section>
    );
}

export function NoticeList({ notices }: { notices: Notice[] }) {
    return (
        <ul className="divide-y text-sm">
            {notices.map((n) => (
                <li key={n.title} className="flex justify-between gap-3 py-2">
                    <span>{n.title}</span>
                    <span className="text-muted-foreground shrink-0">{n.date}</span>
                </li>
            ))}
        </ul>
    );
}

export function Bar({ label, value, right }: { label: string; value: number; right?: string }) {
    return (
        <div className="mb-3 text-sm">
            <div className="mb-1 flex justify-between">
                <span>{label}</span>
                <span className="text-muted-foreground">{right ?? `${value}%`}</span>
            </div>
            <div className="h-2 rounded bg-fuchsia-100">
                <div className="h-2 rounded bg-fuchsia-600" style={{ width: `${value}%` }} />
            </div>
        </div>
    );
}
