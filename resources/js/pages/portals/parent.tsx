import { StudentInsights, type StudentCharts } from '@/components/charts/student-insights';
import { AnimatedPage, Swap } from '@/components/motion';
import { useUrlTab } from '@/hooks/use-url-tab';
import { UserFiles, type UserFile } from '@/components/user-files';
import { useI18n } from '@/lib/i18n';
import { ProfileForm } from '@/components/portal-profile';
import { PortalShell } from '@/components/portal-shell';
import {
    AttendanceBoard,
    BusBoard,
    CalendarBoard,
    DocumentsBoard,
    FeesBoard,
    HomeworkBoard,
    LeaveBoard,
    LibraryBoard,
    ResultsBoard,
    AdmitBoard,
    type AdmitData,
    RoutineBoard,
    type Attendance,
    type Bus,
    type CalendarData,
    type DocumentRow,
    type Fees,
    type HomeworkItem,
    type LeaveRow,
    type Library,
    type Results,
    type Routine,
} from '@/components/portal-views';
import { Head, Link } from '@inertiajs/react';
import { CalendarCheck, GraduationCap, School, Wallet, type LucideIcon } from 'lucide-react';
import { useState } from 'react';

type Child = { id: number; name: string; photo?: string; class: string | null; section: string | null };
type Profile = {
    name: string;
    photo?: string;
    class: string;
    section: string | null;
    roll_no: string | null;
    attendance: { percent: number | null; present: number; working_days: number };
    latest_result: { exam: string; gpa: string } | null;
    dues: { total: number; count: number; next_due_date: string | null };
};

type ConductNote = { id: number; kind: string; severity: string | null; title: string; details: string | null; action_taken: string | null; occurred_on: string };

function ConductBoard({ notes }: { notes: ConductNote[] }) {
    const { t } = useI18n();

    if (notes.length === 0) {
        return <p className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">{t('No notes about this student.')}</p>;
    }

    return (
        <ul className="space-y-3">
            {notes.map((n) => (
                <li key={n.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${n.kind === 'violation' ? 'bg-red-50 text-red-700' : n.kind === 'commendation' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{n.kind}</span>
                        <h3 className="font-semibold">{n.title}</h3>
                        <span className="ms-auto text-xs text-slate-400">{n.occurred_on}</span>
                    </div>
                    {n.details && <p className="mt-2 text-sm text-slate-600">{n.details}</p>}
                    {n.action_taken && <p className="mt-1 text-xs text-slate-500">{t('Action taken')}: {n.action_taken}</p>}
                </li>
            ))}
        </ul>
    );
}

const titles: Record<string, string> = {
    home: 'My children',
    attendance: 'Attendance',
    routine: 'Class Routine',
    calendar: 'Calendar',
    documents: 'Documents',
    leave: 'Leave',
    homework: 'Homework',
    library: 'Library',
    transport: 'School Bus',
    conduct: 'Conduct',
    fees: 'Fees',
    'admit-card': 'Admit Card',
    profile: 'My Profile',
};

export default function ParentPortal({
    page,
    child,
    children,
    profile,
    account,
    notices,
    data,
    files = [],
    charts,
}: {
    charts?: StudentCharts;
    page: string;
    child: number;
    children: Child[];
    profile: Profile;
    account: { name: string; email: string; phone: string | null };
    notices: { title: string; body: string; date: string }[];
    files?: UserFile[];
    data: { results: Results; fees: Fees } | Routine | Attendance | HomeworkItem[] | Library | Bus | DocumentRow[] | LeaveRow[] | CalendarData | null;
}) {
    const title = titles[page] ?? 'My children';
    const query = `?child=${child}`;
    const selected = children.find((item) => item.id === child) ?? children[0];

    return (
        <PortalShell caption="Parent portal" roleLabel="Parent" title={title} notices={notices} query={query}>
            <Head title={title} />
            {page !== 'profile' && page !== 'home' && (
                <div className="mb-4 flex flex-wrap gap-2">
                    {children.map((item) => (
                        <Link
                            key={item.id}
                            href={`/parent/${page}?child=${item.id}`}
                            className={`rounded-xl border px-3 py-2 text-left text-sm ${item.id === child ? 'border-brand bg-pink-50' : 'border-slate-200 bg-white'}`}
                        >
                            <span className="block font-medium">{item.name}</span>
                            <span className="block text-xs text-slate-400">
                                {item.class}
                                {item.section ? ` · ${item.section}` : ''}
                            </span>
                        </Link>
                    ))}
                </div>
            )}
            {page === 'home' && <ParentHome charts={charts} account={account} profile={profile} child={selected} children={children} data={data as { results: Results; fees: Fees }} />}
            {page === 'routine' && <RoutineBoard routine={data as Routine} />}
            {page === 'calendar' && <CalendarBoard calendar={data as CalendarData} />}
            {page === 'attendance' && <AttendanceBoard attendance={data as Attendance} />}
            {page === 'homework' && <HomeworkBoard items={data as HomeworkItem[]} canSubmit={false} />}
            {page === 'library' && <LibraryBoard library={data as Library} />}
            {page === 'transport' && <BusBoard bus={data as Bus} />}
            {page === 'admit-card' && <AdmitBoard admit={data as unknown as AdmitData} child={child} />}
            {page === 'fees' && <FeesBoard fees={data as unknown as Fees} />}
            {page === 'conduct' && <ConductBoard notes={data as unknown as ConductNote[]} />}
            {page === 'documents' && (
                <div className="space-y-6">
                    <DocumentsBoard documents={data as DocumentRow[]} />
                    <section className="rounded-2xl border border-slate-200 bg-white p-5">
                        <h2 className="mb-3 font-semibold">Uploaded documents</h2>
                        <UserFiles files={files} />
                    </section>
                </div>
            )}
            {page === 'leave' && <LeaveBoard leaves={data as LeaveRow[]} action={route('parent.leave.store')} child={child} />}
            {page === 'profile' && (
                <ProfileForm profile={account} showClass={false} profileAction={route('parent.profile.update')} passwordAction={route('parent.password.update')} />
            )}
        </PortalShell>
    );
}

function ParentHome({
    account,
    profile,
    child,
    children,
    data,
    charts,
}: {
    charts?: StudentCharts;
    account: { name: string };
    profile: Profile;
    child: Child;
    children: Child[];
    data: { results: Results; fees: Fees };
}) {
    const [tab, setTab] = useUrlTab(['overview', 'results', 'fees'] as const, 'view');
    const first = account.name.split(' ')[0];

    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-2xl font-extrabold text-slate-900">Hello, {first} 👋</h1>
                <p className="mt-1 text-sm text-slate-500">Follow your children’s attendance, results and dues.</p>
            </div>

            <div className="flex flex-wrap gap-2">
                {children.map((item) => (
                    <Link
                        key={item.id}
                        href={`/parent?child=${item.id}`}
                        className={`flex items-center gap-2 rounded-xl border px-3 py-2 text-sm transition ${
                            item.id === child.id ? 'border-brand-light bg-white shadow-sm' : 'border-slate-200 bg-white/70 hover:border-pink-200'
                        }`}
                    >
                        {item.photo ? (
                            <img src={item.photo} alt={item.name} className="h-8 w-8 rounded-lg object-cover" />
                        ) : (
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-50 text-xs font-bold text-brand">{item.name.charAt(0)}</span>
                        )}
                        <span className="font-medium text-slate-800">{item.name}</span>
                        <span className="text-xs text-slate-400">{item.class}{item.section ? `-${item.section}` : ''}</span>
                    </Link>
                ))}
            </div>

            <div className="inline-flex rounded-xl bg-slate-100 p-1">
                {(['overview', 'results', 'fees'] as const).map((item) => (
                    <button
                        key={item}
                        type="button"
                        onClick={() => setTab(item)}
                        className={`rounded-lg px-4 py-2 text-sm font-medium capitalize transition ${
                            tab === item ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        {item}
                    </button>
                ))}
            </div>

            {tab === 'overview' && (
                <Swap id={child.id} className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <ParentCard
                        icon={CalendarCheck}
                        className="bg-[#eafaf1] text-emerald-900"
                        iconClass="bg-white/70 text-emerald-600"
                        label="Attendance"
                        value={profile.attendance.percent === null ? '—' : `${profile.attendance.percent}%`}
                        sub={`${profile.attendance.present}/${profile.attendance.working_days} days`}
                    />
                    <ParentCard
                        icon={GraduationCap}
                        className="bg-gradient-to-br from-brand-light to-brand-light text-white"
                        iconClass="bg-white/20 text-white"
                        label="Latest GPA"
                        value={profile.latest_result?.gpa ?? '—'}
                        sub={profile.latest_result?.exam ?? 'No published result'}
                    />
                    <ParentCard
                        icon={Wallet}
                        className="bg-[#fff9e8] text-amber-900"
                        iconClass="bg-white/70 text-amber-500"
                        label="Dues"
                        value={profile.dues.total > 0 ? `Tk ${profile.dues.total.toLocaleString()}` : 'Clear'}
                        sub={profile.dues.next_due_date ? `Due ${profile.dues.next_due_date}` : 'No dues'}
                    />
                    <ParentCard
                        icon={School}
                        className="bg-[#eef8ff] text-sky-900"
                        iconClass="bg-white/70 text-sky-600"
                        label="Class"
                        value={profile.section ? `${profile.class} · ${profile.section}` : profile.class}
                        sub={profile.roll_no ? `Roll ${profile.roll_no}` : ''}
                    />
                </div>
                <StudentInsights charts={charts} name={child.name} />
                </Swap>
            )}

            {tab === 'results' && (
                <>
                    <div className="mb-3 flex justify-end"><a href={`/students/${child.id}/transcript.pdf`} className="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white">Download transcript (PDF)</a></div>
                    <ResultsBoard results={data.results} />
                </>
            )}
            {tab === 'fees' && <FeesBoard fees={data.fees} />}
        </div>
    );
}

function ParentCard({
    icon: Icon,
    className,
    iconClass,
    label,
    value,
    sub,
}: {
    icon: LucideIcon;
    className: string;
    iconClass: string;
    label: string;
    value: string;
    sub: string;
}) {
    return (
        <article className={`rounded-2xl p-5 ${className}`}>
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] font-bold tracking-wider uppercase">{label}</p>
                <span className={`flex h-8 w-8 items-center justify-center rounded-xl ${iconClass}`}>
                    <Icon className="h-4 w-4" />
                </span>
            </div>
            <p className="mt-3 text-3xl font-bold tabular-nums">{value}</p>
            <p className="mt-1 text-sm opacity-75">{sub}</p>
        </article>
    );
}
