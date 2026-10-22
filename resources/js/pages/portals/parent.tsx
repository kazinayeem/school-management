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
type Child = { id: number; name: string; class: string | null; section: string | null };
type Profile = {
    name: string;
    class: string;
    section: string | null;
    roll_no: string | null;
    attendance: { percent: number | null; present: number; working_days: number };
    latest_result: { exam: string; gpa: string } | null;
    dues: { total: number; count: number; next_due_date: string | null };
};

const titles: Record<string, string> = {
    home: 'My Children',
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
    const title = titles[page] ?? 'My Children';
    const query = `?child=${child}`;

    return (
        <PortalShell caption="Parent portal" roleLabel="Parent" title={title} notices={notices} query={query}>
            <Head title={title} />
            {page !== 'profile' && (
                <div className="mb-4 flex flex-wrap gap-2">
                    {children.map((item) => (
                        <Link
                            key={item.id}
                            href={`/parent${page === 'home' ? '' : `/${page}`}?child=${item.id}`}
                            className={`rounded-xl border px-3 py-2 text-left text-sm ${item.id === child ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 bg-white'}`}
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
            {page === 'home' && <ParentHome profile={profile} data={data as { results: Results; fees: Fees }} />}
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

function ParentHome({ profile, data }: { profile: Profile; data: { results: Results; fees: Fees } }) {
    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-extrabold">{profile.name}</h1>
                <p className="text-sm text-slate-500">
                    {profile.class}
                    {profile.section ? ` · Section ${profile.section}` : ''}
                    {profile.roll_no ? ` · Roll ${profile.roll_no}` : ''}
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article className="rounded-2xl bg-emerald-50 p-5 text-emerald-800">
                    <p className="text-xs font-semibold tracking-wide uppercase">Attendance</p>
                    <p className="mt-2 text-3xl font-bold">{profile.attendance.percent === null ? '—' : `${profile.attendance.percent}%`}</p>
                    <p className="text-sm">{profile.attendance.present}/{profile.attendance.working_days} working days</p>
                </article>
                <article className="rounded-2xl bg-white p-5 text-indigo-700 ring-1 ring-slate-200">
                    <p className="text-xs font-semibold tracking-wide uppercase">Latest GPA</p>
                    <p className="mt-2 text-3xl font-bold">{profile.latest_result?.gpa ?? '—'}</p>
                    <p className="text-sm">{profile.latest_result?.exam ?? 'No published result'}</p>
                </article>
                <article className={`rounded-2xl p-5 ${profile.dues.total > 0 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800'}`}>
                    <p className="text-xs font-semibold tracking-wide uppercase">Dues</p>
                    <p className="mt-2 text-3xl font-bold">{profile.dues.total > 0 ? `Tk ${profile.dues.total.toLocaleString()}` : 'Clear'}</p>
                    <p className="text-sm">{profile.dues.next_due_date ? `Due ${profile.dues.next_due_date}` : 'No dues'}</p>
                </article>
                <article className="rounded-2xl bg-sky-50 p-5 text-sky-800">
                    <p className="text-xs font-semibold tracking-wide uppercase">Class</p>
                    <p className="mt-2 text-3xl font-bold">{profile.section ? `${profile.class} · ${profile.section}` : profile.class}</p>
                    <p className="text-sm">{profile.roll_no ? `Roll ${profile.roll_no}` : ''}</p>
                </article>
            </div>
            <ResultsBoard results={data.results} />
            <FeesBoard fees={data.fees} />
        </div>
    );
}
