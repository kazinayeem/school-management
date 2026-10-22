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

type Profile = {
    name: string;
    email: string;
    phone: string | null;
    class: string;
    section: string | null;
    roll_no: string | null;
    session: string;
    attendance: { percent: number | null; present: number; working_days: number };
    latest_result: { exam: string; gpa: string } | null;
    dues: { total: number; count: number; next_due_date: string | null };
};

const titles: Record<string, string> = {
    home: 'Home',
    routine: 'Class Routine',
    calendar: 'My Calendar',
    attendance: 'My Attendance',
    homework: 'My Homework',
    results: 'My Results',
    fees: 'My Fees',
    library: 'My Library',
    transport: 'My Bus',
    documents: 'My Documents',
    leave: 'My Leave',
    profile: 'My Profile',
};

export default function StudentPortal({
    page,
    profile,
    notices,
    data,
}: {
    page: string;
    profile: Profile;
    notices: { title: string; body: string; date: string }[];
    data: Routine | Attendance | HomeworkItem[] | Results | Fees | Library | Bus | DocumentRow[] | LeaveRow[] | CalendarData | null;
}) {
    const title = titles[page] ?? 'Home';

    return (
        <PortalShell caption="Student portal" roleLabel="Student" title={title} notices={notices}>
            <Head title={title} />
            {page === 'home' && <Home profile={profile} />}
            {page === 'routine' && <RoutineBoard routine={data as Routine} />}
            {page === 'calendar' && <CalendarBoard calendar={data as CalendarData} />}
            {page === 'attendance' && <AttendanceBoard attendance={data as Attendance} />}
            {page === 'homework' && <HomeworkBoard items={data as HomeworkItem[]} canSubmit />}
            {page === 'results' && <ResultsBoard results={data as Results} />}
            {page === 'fees' && <FeesBoard fees={data as Fees} />}
            {page === 'library' && <LibraryBoard library={data as Library} />}
            {page === 'transport' && <BusBoard bus={data as Bus} />}
            {page === 'documents' && <DocumentsBoard documents={data as DocumentRow[]} />}
            {page === 'leave' && <LeaveBoard leaves={data as LeaveRow[]} action={route('student.leave.store')} />}
            {page === 'profile' && <ProfileForm profile={profile} passwordAction={route('student.password.update')} profileAction={route('student.profile.update')} />}
            {page !== 'home' && <p className="mt-4 text-sm text-slate-500">{blurb(page)}</p>}
        </PortalShell>
    );
}

function blurb(page: string) {
    const copy: Record<string, string> = {
        routine: "Your section's weekly timetable, period by period.",
        calendar: 'Holidays, exams and school events for this month.',
        attendance: 'Present, late, absent and leave for the current month.',
        homework: 'Assignments from your teachers. Submit a note when the work is done.',
        results: 'Published exam marks for the current session.',
        fees: 'Invoices and receipts for this session.',
        library: 'Books on your library card.',
        transport: 'Pickup, drop and the driver for your route.',
        documents: 'ID card and certificates issued by the school.',
        leave: 'Apply for sick or casual leave. Sick leave allows up to 10 days a year. Casual leave allows up to 7.',
        profile: 'Keep your contact details up to date.',
    };

    return copy[page] ?? '';
}

function Home({ profile }: { profile: Profile }) {
    const first = profile.name.split(' ')[0];

    return (
        <div className="space-y-6">
            <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-700 p-6 text-white shadow-lg shadow-indigo-600/20">
                <div className="relative z-10 flex flex-wrap items-center gap-5">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 text-2xl font-bold">{profile.name.charAt(0)}</div>
                    <div className="min-w-0 flex-1">
                        <h1 className="text-2xl font-extrabold tracking-tight">Hello, {first} 👋</h1>
                        <p className="mt-0.5 text-sm text-indigo-100">
                            {profile.class}
                            {profile.section ? ` · Section ${profile.section}` : ''}
                            {profile.roll_no ? ` · Roll ${profile.roll_no}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href="/student/homework" className="rounded-lg bg-white/15 px-3.5 py-2 text-xs font-semibold backdrop-blur">
                            My Homework
                        </Link>
                        <Link href="/student/results" className="rounded-lg bg-white/15 px-3.5 py-2 text-xs font-semibold backdrop-blur">
                            My Results
                        </Link>
                        <Link href="/student/fees" className="rounded-lg bg-white px-3.5 py-2 text-xs font-bold text-indigo-700">
                            My Fees
                        </Link>
                    </div>
                </div>
            </section>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card tone="bg-emerald-50 text-emerald-800" label="My Attendance" value={profile.attendance.percent === null ? '—' : `${profile.attendance.percent}%`} sub={`${profile.attendance.present}/${profile.attendance.working_days} working days`} />
                <Card tone="bg-white text-indigo-700 ring-1 ring-slate-200" label="Latest GPA" value={profile.latest_result?.gpa ?? '—'} sub={profile.latest_result?.exam ?? 'No published result'} />
                <Card tone={profile.dues.total > 0 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800'} label="My Dues" value={profile.dues.total > 0 ? `Tk ${profile.dues.total.toLocaleString()}` : 'Clear'} sub={profile.dues.next_due_date ? `Due ${profile.dues.next_due_date}` : 'No outstanding dues'} />
                <Card tone="bg-sky-50 text-sky-800" label="Class" value={profile.section ? `${profile.class} · ${profile.section}` : profile.class} sub={profile.roll_no ? `Roll ${profile.roll_no}` : profile.session} />
            </div>
            <div className="grid gap-6 lg:grid-cols-3">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                    {profile.latest_result ? (
                        <>
                            <p className="text-sm text-slate-500">{profile.latest_result.exam}</p>
                            <p className="mt-2 text-4xl font-bold text-indigo-600">GPA {profile.latest_result.gpa}</p>
                            <span className="mt-3 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Published</span>
                            <a href="/student/results" className="mt-5 inline-flex h-10 items-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">
                                View all results
                            </a>
                        </>
                    ) : (
                        <p className="py-8 text-center text-sm text-slate-400">No published result yet.</p>
                    )}
                </section>
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="font-semibold text-slate-800">Fee status</h2>
                    <p className={`mt-4 text-3xl font-bold ${profile.dues.total > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>
                        {profile.dues.total > 0 ? `Tk ${profile.dues.total.toLocaleString()}` : 'All clear'}
                    </p>
                    <p className="mt-1 text-sm text-slate-500">
                        {profile.dues.total > 0 ? `${profile.dues.count} unpaid invoice(s)${profile.dues.next_due_date ? ` · due ${profile.dues.next_due_date}` : ''}` : 'No outstanding dues. Thank you!'}
                    </p>
                </section>
            </div>
        </div>
    );
}

function Card({ label, value, sub, tone }: { label: string; value: string; sub: string; tone: string }) {
    return (
        <article className={`rounded-2xl p-5 ${tone}`}>
            <p className="text-xs font-semibold tracking-wide uppercase">{label}</p>
            <p className="mt-2 text-3xl font-bold tabular-nums">{value}</p>
            <p className="mt-1 text-sm opacity-80">{sub}</p>
        </article>
    );
}

