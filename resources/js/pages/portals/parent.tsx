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
}: {
    page: string;
    child: number;
    children: Child[];
    profile: Profile;
    account: { name: string; email: string; phone: string | null };
    notices: { title: string; body: string; date: string }[];
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
                            className={`rounded-xl border px-3 py-2 text-left text-sm ${item.id === child ? 'border-[#b31299] bg-pink-50' : 'border-slate-200 bg-white'}`}
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
            {page === 'home' && <ParentHome account={account} profile={profile} child={selected} children={children} data={data as { results: Results; fees: Fees }} />}
            {page === 'routine' && <RoutineBoard routine={data as Routine} />}
            {page === 'calendar' && <CalendarBoard calendar={data as CalendarData} />}
            {page === 'attendance' && <AttendanceBoard attendance={data as Attendance} />}
            {page === 'homework' && <HomeworkBoard items={data as HomeworkItem[]} canSubmit={false} />}
            {page === 'library' && <LibraryBoard library={data as Library} />}
            {page === 'transport' && <BusBoard bus={data as Bus} />}
            {page === 'documents' && <DocumentsBoard documents={data as DocumentRow[]} />}
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
}: {
    account: { name: string };
    profile: Profile;
    child: Child;
    children: Child[];
    data: { results: Results; fees: Fees };
}) {
    const [tab, setTab] = useState<'overview' | 'results' | 'fees'>('overview');
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
                            item.id === child.id ? 'border-[#d11ab3] bg-white shadow-sm' : 'border-slate-200 bg-white/70 hover:border-pink-200'
                        }`}
                    >
                        {item.photo ? (
                            <img src={item.photo} alt={item.name} className="h-8 w-8 rounded-lg object-cover" />
                        ) : (
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-pink-50 text-xs font-bold text-[#b31299]">{item.name.charAt(0)}</span>
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
                            tab === item ? 'bg-white text-[#b31299] shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        {item}
                    </button>
                ))}
            </div>

            {tab === 'overview' && (
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
                        className="bg-gradient-to-br from-[#c2189b] to-[#df1fa9] text-white"
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
            )}

            {tab === 'results' && <ResultsBoard results={data.results} />}
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
