import { OfficeShell } from '@/components/office-shell';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CalendarCheck, Library, Megaphone, UserRound, Users } from 'lucide-react';

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

const tones: Record<string, string> = {
    indigo: 'bg-indigo-50 text-indigo-600 ring-indigo-100',
    emerald: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
    sky: 'bg-sky-50 text-sky-600 ring-sky-100',
    amber: 'bg-amber-50 text-amber-600 ring-amber-100',
};

const metricIcons = [Users, UserRound, CalendarCheck, Library];

export default function OfficeHome({
    navigation,
    roleLabel,
    dashboard,
}: {
    navigation: Parameters<typeof OfficeShell>[0]['navigation'];
    roleLabel: string;
    dashboard: Dashboard;
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
                        <span className="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">{dashboard.session}</span>
                        <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600">{dashboard.today}</span>
                    </div>
                </div>

                <section aria-label="Key metrics" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {dashboard.metrics.map((metric, index) => {
                        const Icon = metricIcons[index] ?? Users;

                        return (
                            <Link key={metric.label} href={metric.href} className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="flex items-start justify-between">
                                    <span className={`flex h-10 w-10 items-center justify-center rounded-xl ring-1 ${tones[metric.tone]}`}>
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <ArrowRight className="h-4 w-4 text-slate-300 group-hover:translate-x-0.5" />
                                </div>
                                <p className="mt-4 text-3xl font-bold tracking-tight text-slate-900 tabular-nums">{metric.value}</p>
                                <div className="mt-1 flex items-end justify-between gap-3">
                                    <p className="font-medium text-slate-800">{metric.label}</p>
                                    <p className="text-right text-xs text-slate-400">{metric.note}</p>
                                </div>
                            </Link>
                        );
                    })}
                </section>

                <div className="grid gap-4 xl:grid-cols-2">
                    <section aria-label="Operational snapshot" className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slate-900">Operational snapshot</h2>
                        <p className="mt-0.5 text-sm text-slate-500">Items that may need your attention today</p>
                        <div className="mt-4 space-y-3">
                            {dashboard.snapshot.map((item) => (
                                <Link key={item.label} href={item.href} className="flex items-center justify-between rounded-xl bg-rose-50 px-4 py-3">
                                    <span>
                                        <span className="text-2xl font-bold text-slate-900">{item.value}</span>
                                        <span className="mt-0.5 block text-sm font-medium">{item.label}</span>
                                        <span className="text-xs text-slate-500">{item.note}</span>
                                    </span>
                                    <ArrowRight className="h-4 w-4 text-slate-400" />
                                </Link>
                            ))}
                        </div>
                    </section>
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slate-900">Quick actions</h2>
                        <p className="mt-0.5 text-sm text-slate-500">Common tasks, one click away</p>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            {dashboard.actions.map((action) => (
                                <Link key={action.label} href={action.href} className="rounded-xl border border-slate-200 px-4 py-3 hover:border-indigo-200 hover:bg-indigo-50">
                                    <span className="block font-semibold text-slate-900">{action.label}</span>
                                    <span className="text-xs text-slate-500">{action.note}</span>
                                </Link>
                            ))}
                        </div>
                    </section>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slate-900">Fee collection</h2>
                            <p className="mt-0.5 text-sm text-slate-500">Net payments received during the last 30 days</p>
                        </div>
                        <Link href={dashboard.fees.href} className="text-sm font-semibold text-indigo-600">
                            View collection report
                        </Link>
                    </div>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-slate-400 uppercase">Collected</p>
                            <p className="text-2xl font-bold">{dashboard.fees.collected}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-slate-400 uppercase">Best day</p>
                            <p className="text-2xl font-bold">{dashboard.fees.best}</p>
                        </div>
                    </div>
                    {dashboard.fees.empty && (
                        <div className="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center">
                            <p className="font-medium text-slate-700">No payments recorded yet</p>
                            <p className="text-sm text-slate-400">Collections from the last 30 days will appear here.</p>
                        </div>
                    )}
                </section>

                <div className="grid gap-4 xl:grid-cols-2">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slate-900">Attendance by class</h2>
                        <p className="mt-0.5 text-sm text-slate-500">Present students today</p>
                        <ul className="mt-4 space-y-3">
                            {dashboard.classes.map((item) => (
                                <li key={item.name}>
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span>{item.name}</span>
                                        <span className="text-slate-400">{item.percent}%</span>
                                    </div>
                                    <div className="h-1.5 rounded-full bg-slate-100">
                                        <div className="h-1.5 rounded-full bg-indigo-500" style={{ width: `${item.percent}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="font-semibold text-slate-900">Attendance status</h2>
                                <p className="mt-0.5 text-sm text-slate-500">Sections that still need attention</p>
                            </div>
                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{dashboard.pendingCount} pending</span>
                        </div>
                        <ul className="mt-4 divide-y divide-slate-100">
                            {dashboard.pending.map((section) => (
                                <li key={section} className="flex items-center justify-between py-2.5 text-sm">
                                    <span className="font-medium">{section}</span>
                                    <Link href="/app/attendance" className="font-semibold text-indigo-600">
                                        Mark now
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slate-900">Recent activity</h2>
                        <p className="mt-0.5 text-sm text-slate-500">Latest changes across the system</p>
                        <ul className="mt-4 space-y-3">
                            {dashboard.activity.map((item, index) => (
                                <li key={index} className="flex items-center gap-3 text-sm">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-bold">{item.actor.charAt(0)}</span>
                                    <span className="min-w-0 flex-1">
                                        <span className="font-medium">{item.actor}</span> <span className="text-slate-500">{item.action}</span>
                                    </span>
                                    <span className="text-xs text-slate-400">{item.when}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="flex items-center gap-2 font-semibold text-slate-900">
                                    <Megaphone className="h-4 w-4 text-indigo-600" /> Active notices
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500">Currently visible announcements</p>
                            </div>
                            <Link href="/app/notices" className="text-sm font-semibold text-indigo-600">
                                View all
                            </Link>
                        </div>
                        <ul className="mt-4 space-y-3">
                            {dashboard.notices.map((notice) => (
                                <li key={notice.title} className="rounded-xl bg-slate-50 px-4 py-3">
                                    <p className="font-medium text-slate-800">{notice.title}</p>
                                    <p className="text-xs text-slate-400">{notice.meta}</p>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </OfficeShell>
    );
}
