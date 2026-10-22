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
import { CalendarCheck, Eye, GraduationCap, School, Wallet, type LucideIcon } from 'lucide-react';

type Profile = {
    name: string;
    photo?: string;
    email: string;
    phone: string | null;
    class: string;
    section: string | null;
    roll_no: string | null;
    session: string;
    attendance: { percent: number | null; present: number; working_days: number };
    latest_result: { exam: string; gpa: string; promotion_status?: string } | null;
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
            {!['home', 'homework', 'results', 'fees'].includes(page) && <p className="mt-4 text-sm text-slate-500">{blurb(page)}</p>}
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
        <div className="space-y-5">
            <section className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#c01e9c] via-[#b517a6] to-[#7c3aed] px-6 py-5 text-white shadow-lg shadow-fuchsia-500/20">
                <div className="relative z-10 flex flex-wrap items-center gap-4">
                    {profile.photo ? (
                        <img src={profile.photo} alt={profile.name} className="h-16 w-16 rounded-2xl border-2 border-white/40 object-cover shadow-md" />
                    ) : (
                        <span className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-2xl font-bold">{profile.name.charAt(0)}</span>
                    )}
                    <div className="min-w-0 flex-1">
                        <h1 className="text-2xl font-extrabold tracking-tight">Hello, {first} 👋</h1>
                        <p className="mt-1 text-sm text-white/85">
                            {profile.class}
                            {profile.section ? ` · Section ${profile.section}` : ''}
                            {profile.roll_no ? ` · Roll ${profile.roll_no}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href="/student/homework" className="rounded-lg bg-white/15 px-4 py-2 text-xs font-semibold backdrop-blur transition hover:bg-white/25">
                            My Homework
                        </Link>
                        <Link href="/student/results" className="rounded-lg bg-white/15 px-4 py-2 text-xs font-semibold backdrop-blur transition hover:bg-white/25">
                            My Results
                        </Link>
                        <Link href="/student/fees" className="rounded-lg bg-white px-4 py-2 text-xs font-bold text-[#a3128f] shadow-sm">
                            My Fees
                        </Link>
                    </div>
                </div>
            </section>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryCard
                    icon={CalendarCheck}
                    className="bg-[#eafaf1] text-emerald-900"
                    iconClass="bg-white/70 text-emerald-600"
                    label="My Attendance"
                    value={profile.attendance.percent === null ? '—' : `${profile.attendance.percent}%`}
                    sub={`${profile.attendance.present}/${profile.attendance.working_days} working days`}
                />
                <SummaryCard
                    icon={GraduationCap}
                    className="bg-gradient-to-br from-[#c2189b] to-[#df1fa9] text-white"
                    iconClass="bg-white/20 text-white"
                    label="Latest GPA"
                    value={profile.latest_result?.gpa ?? '—'}
                    sub={profile.latest_result?.exam ?? 'No published result'}
                />
                <SummaryCard
                    icon={Wallet}
                    className="bg-[#fff9e8] text-amber-900"
                    iconClass="bg-white/70 text-amber-500"
                    label="My Dues"
                    value={profile.dues.total > 0 ? `Tk ${profile.dues.total.toLocaleString()}` : 'Clear'}
                    sub={profile.dues.next_due_date ? `Due ${profile.dues.next_due_date}` : 'No outstanding dues'}
                />
                <SummaryCard
                    icon={School}
                    className="bg-[#eef8ff] text-sky-900"
                    iconClass="bg-white/70 text-sky-600"
                    label="Class"
                    value={profile.section ? `${profile.class} · ${profile.section}` : profile.class}
                    sub={profile.roll_no ? `Roll ${profile.roll_no}` : profile.session}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                    {profile.latest_result ? (
                        <>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm text-slate-500">{profile.latest_result.exam}</p>
                                    <p className="mt-2 text-4xl font-bold text-[#b31299]">GPA {profile.latest_result.gpa}</p>
                                </div>
                                <span className="rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-600">Published</span>
                            </div>
                            <Link href="/student/results" className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-[#b31299] px-4 text-sm font-semibold text-white transition hover:bg-[#9a0e84]">
                                <Eye className="h-4 w-4" /> View all results
                            </Link>
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
                        {profile.dues.total > 0
                            ? `${profile.dues.count} unpaid invoice(s)${profile.dues.next_due_date ? ` · due ${profile.dues.next_due_date}` : ''}`
                            : 'No outstanding dues. Thank you!'}
                    </p>
                </section>
            </div>
        </div>
    );
}

function SummaryCard({
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
