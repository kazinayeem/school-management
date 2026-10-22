import { OfficeInsights, type OfficeCharts } from '@/components/charts/office-insights';
import { AnimatedNumber } from '@/components/motion';
import { OfficeShell } from '@/components/office-shell';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    CalendarCheck,
    CircleDollarSign,
    FilePlus2,
    Megaphone,
    MessageSquare,
    RefreshCw,
    UserPlus,
    UserRound,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';

type Metric = { label: string; value: string; note: string; tone: string; href: string };
type LinkCard = { label: string; value?: string; note: string; href: string };
type Dashboard = {
    firstName: string;
    roleLabel: string;
    session: string;
    today: string;
    metrics: Metric[];
    snapshot: LinkCard[];
    actions: LinkCard[];
    fees: { collected: string; best: string; empty: boolean; href: string };
    classes: { name: string; percent: number }[];
    pendingCount: number;
    pending: string[];
    activity: { actor: string; action: string; when: string }[];
    notices: { title: string; meta: string }[];
};

const metricTones: Record<string, { card: string; icon: string }> = {
    indigo: { card: 'bg-brand-soft text-brand-dark', icon: 'bg-brand-soft text-brand' },
    emerald: { card: 'bg-[#eefaf4] text-emerald-700', icon: 'bg-[#d9f5e6] text-emerald-600' },
    sky: { card: 'bg-[#eef7ff] text-sky-700', icon: 'bg-[#dcefff] text-sky-600' },
    amber: { card: 'bg-[#fffaf0] text-amber-700', icon: 'bg-[#ffefcc] text-amber-500' },
};

const metricIcons: LucideIcon[] = [Users, UserRound, CalendarCheck, CircleDollarSign];
const snapshotIcons: LucideIcon[] = [CircleDollarSign, FilePlus2, MessageSquare, BookOpen];
const actionIcons: Record<string, LucideIcon> = {
    'New admission': UserPlus,
    'Mark attendance': CalendarCheck,
    'Collect fee': Wallet,
    'Publish notice': Megaphone,
};

export default function OfficeHome({
    navigation,
    roleLabel,
    dashboard,
    charts,
}: {
    navigation: Parameters<typeof OfficeShell>[0]['navigation'];
    roleLabel: string;
    dashboard: Dashboard;
    charts?: OfficeCharts;
}) {
    return (
        <OfficeShell navigation={navigation} roleLabel={roleLabel}>
            <Head title="Dashboard" />
            <div className="mx-auto max-w-[1600px] space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900">Welcome back, {dashboard.firstName}</h1>
                        <p className="mt-1 text-sm text-slate-500">Here is what is happening at your school today.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <span className="rounded-full border border-pink-100 bg-pink-50 px-3 py-1.5 text-xs font-semibold text-pink-700">
                            {dashboard.session}
                        </span>
                        <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600">
                            {dashboard.today}
                        </span>
                    </div>
                </div>

                <section aria-label="Key metrics" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {dashboard.metrics.map((metric, index) => {
                        const Icon = metricIcons[index] ?? Users;
                        const tone = metricTones[metric.tone] ?? metricTones.indigo;

                        return (
                            <Link
                                key={metric.label}
                                href={metric.href}
                                className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <div className="flex items-start justify-between">
                                    <span className={`flex h-10 w-10 items-center justify-center rounded-2xl ${tone.icon}`}>
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <ArrowRight className="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-400" />
                                </div>
                                <AnimatedNumber value={metric.value} className="mt-4 block text-3xl font-bold tracking-tight text-slate-900" />
                                <div className="mt-1 flex items-end justify-between gap-3">
                                    <p className="font-medium whitespace-nowrap text-slate-800">{metric.label}</p>
                                    <p className="max-w-[44%] text-right text-[11px] leading-4 text-slate-400">{metric.note}</p>
                                </div>
                            </Link>
                        );
                    })}
                </section>

                <section aria-label="Operational snapshot">
                    <h2 className="font-semibold text-slate-900">Operational snapshot</h2>
                    <p className="mt-0.5 text-sm text-slate-500">Items that may need your attention today</p>
                    <div className="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {dashboard.snapshot.map((item, index) => {
                            const Icon = snapshotIcons[index] ?? CircleDollarSign;

                            return (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                >
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500">
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-baseline gap-2">
                                            <span className="text-xl font-bold text-slate-900">{item.value}</span>
                                            <span className="truncate text-sm font-medium text-slate-800">{item.label}</span>
                                        </span>
                                        <span className="block truncate text-xs text-slate-400">{item.note}</span>
                                    </span>
                                    <ArrowRight className="h-4 w-4 shrink-0 text-slate-300 group-hover:translate-x-0.5" />
                                </Link>
                            );
                        })}
                    </div>
                </section>

                <OfficeInsights charts={charts} teacher={roleLabel === 'Teacher'} />

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slate-900">Quick actions</h2>
                    <p className="mt-0.5 text-sm text-slate-500">Common tasks, one click away</p>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {dashboard.actions.map((action) => {
                            const Icon = actionIcons[action.label] ?? CalendarCheck;

                            return (
                                <Link
                                    key={action.label}
                                    href={action.href}
                                    className="group flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-pink-200 hover:bg-pink-50/50"
                                >
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-pink-50 text-brand">
                                        <Icon className="h-4 w-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-semibold text-slate-900">{action.label}</span>
                                        <span className="block truncate text-xs text-slate-500">{action.note}</span>
                                    </span>
                                    <ArrowRight className="h-4 w-4 shrink-0 text-slate-300 group-hover:translate-x-0.5" />
                                </Link>
                            );
                        })}
                    </div>
                </section>

                <div className="grid gap-4">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="font-semibold text-slate-900">Attendance by class</h2>
                                <p className="mt-0.5 text-sm text-slate-500">Present students today</p>
                            </div>
                            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                                <CalendarCheck className="h-4 w-4" />
                            </span>
                        </div>
                        <ul className="mt-4 space-y-3">
                            {dashboard.classes.map((item) => (
                                <li key={item.name}>
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span className="text-slate-600">{item.name}</span>
                                        <span className="font-medium text-slate-500 tabular-nums">{item.percent}%</span>
                                    </div>
                                    <div className="h-1.5 rounded-full bg-slate-100">
                                        <div className="h-1.5 rounded-full bg-brand-light" style={{ width: `${Math.max(item.percent, 0)}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="font-semibold text-slate-900">Attendance status</h2>
                                <p className="mt-0.5 text-sm text-slate-500">Sections that still need attention</p>
                            </div>
                            <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                {dashboard.pendingCount} pending
                            </span>
                        </div>
                        <ul className="mt-4 divide-y divide-slate-100">
                            {dashboard.pending.map((section) => (
                                <li key={section} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                                    <span className="font-medium text-slate-700">{section}</span>
                                    <Link
                                        href="/app/attendance"
                                        className="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-pink-50 hover:text-brand"
                                    >
                                        Mark now
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-start justify-between">
                            <div>
                                <h2 className="font-semibold text-slate-900">Recent activity</h2>
                                <p className="mt-0.5 text-sm text-slate-500">Latest changes across the system</p>
                            </div>
                            <button
                                type="button"
                                aria-label="Refresh activity"
                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            >
                                <RefreshCw className="h-4 w-4" />
                            </button>
                        </div>
                        <ul className="mt-4 space-y-3">
                            {dashboard.activity.map((item, index) => (
                                <li key={index} className="flex items-start gap-3 text-sm">
                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-pink-50 text-xs font-bold text-brand">
                                        {item.actor.charAt(0)}
                                    </span>
                                    <span className="min-w-0 flex-1 leading-5">
                                        <span className="block font-medium text-slate-800">{item.actor}</span>
                                        <span className="block text-slate-500">{item.action}</span>
                                        <span className="block text-xs text-slate-400">{item.when}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="font-semibold text-slate-900">Active notices</h2>
                                <p className="mt-0.5 text-sm text-slate-500">Currently visible announcements</p>
                            </div>
                            <Link href="/app/notices" className="text-sm font-semibold text-brand">
                                View all
                            </Link>
                        </div>
                        <ul className="mt-4 space-y-3">
                            {dashboard.notices.map((notice) => (
                                <li key={notice.title} className="flex items-start gap-3 rounded-xl px-1 py-2">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-2-soft text-brand-2">
                                        <Megaphone className="h-4 w-4" />
                                    </span>
                                    <span>
                                        <span className="block font-medium text-slate-800">{notice.title}</span>
                                        <span className="mt-0.5 block text-xs text-slate-400">{notice.meta}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </OfficeShell>
    );
}
