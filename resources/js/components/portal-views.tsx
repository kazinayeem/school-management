import { useForm } from '@inertiajs/react';
import { CalendarCheck, Download, Eye, FileText, RefreshCw, Search, Trophy, Upload, Wallet } from 'lucide-react';
import { FormEvent, Fragment, useState } from 'react';

export type Routine = {
    days: string[];
    periods: { label: string; time: string; is_break: boolean; slots: ({ subject: string; teacher: string; room: string } | null)[] }[];
};
export type Attendance = {
    month: string;
    percent: number | null;
    counts: { present: number; late: number; absent: number; leave: number };
    days: { date: string; day: number; weekday: number; status: string | null }[];
};
export type HomeworkItem = {
    id: number;
    title: string;
    subject: string;
    teacher: string | null;
    due_on: string;
    status: string;
    late: boolean;
    instructions: string | null;
    note: string | null;
    remarks: string | null;
    submitted_at: string | null;
};
export type Results = {
    summary: { exam: string; gpa: string; class_position: number; section_position: number; promotion_status: string } | null;
    subjects: { subject: string; marks: number; full_marks: number; letter_grade: string; failed: boolean }[];
};
export type Fees = {
    total_outstanding: number;
    unpaid_count: number;
    oldest_due_date: string | null;
    invoices: { invoice_no: string; label: string; due_date: string | null; payable: number; paid: number; outstanding: number; status: string }[];
    payments: { receipt_no: string; date: string; method: string; amount: number; late_fee: number; status: string }[];
};
export type Library = {
    card_no: string | null;
    member_since: string | null;
    fine_due: number;
    loans: { title: string; author: string | null; issued_on: string; due_on: string; returned_on: string | null; fine: number; status: string; overdue: boolean }[];
};
export type Bus = {
    route: string;
    vehicle: string | null;
    stop: string;
    landmark: string | null;
    pickup_time: string | null;
    drop_time: string | null;
    driver: string | null;
    driver_phone: string | null;
    fare: number;
    since: string | null;
} | null;
export type DocumentRow = { type: string; serial_no: string; session: string | null; issue_date: string };
export type LeaveRow = { id: number; leave_type: string; start_date: string; end_date: string; days: number; reason: string; status: string };
export type CalendarData = { month: string; days: { date: string; day: number; weekday: number; items: { title: string; type: string }[] }[] };

const money = (value: number) => `Tk ${value.toLocaleString()}`;

const statusTone: Record<string, string> = {
    present: 'bg-emerald-100 text-emerald-700',
    late: 'bg-amber-100 text-amber-700',
    absent: 'bg-rose-100 text-rose-700',
    leave: 'bg-sky-100 text-sky-700',
    unpaid: 'bg-rose-100 text-rose-700',
    partial: 'bg-amber-100 text-amber-700',
    paid: 'bg-emerald-100 text-emerald-700',
    posted: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
    todo: 'bg-slate-100 text-slate-600',
    overdue: 'bg-rose-100 text-rose-700',
    submitted: 'bg-sky-100 text-sky-700',
    marked: 'bg-emerald-100 text-emerald-700',
    'on loan': 'bg-indigo-100 text-indigo-700',
    returned: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-slate-100 text-slate-500',
};

function Pill({ value }: { value: string }) {
    return <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusTone[value] ?? 'bg-slate-100 text-slate-600'}`}>{value}</span>;
}

function Empty({ children }: { children: string }) {
    return <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-400">{children}</div>;
}

export function RoutineBoard({ routine }: { routine: Routine }) {
    if (routine.periods.length === 0) {
        return <Empty>No routine has been published for this section yet.</Empty>;
    }

    return (
        <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[760px] text-sm">
                <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th className="px-4 py-3 text-left">Period</th>
                        {routine.days.map((day) => (
                            <th key={day} className="px-4 py-3 text-left">
                                {day}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {routine.periods.map((period) => (
                        <tr key={period.label} className={period.is_break ? 'bg-amber-50/60' : undefined}>
                            <td className="px-4 py-2.5 whitespace-nowrap">
                                <span className="block font-medium text-slate-700">{period.label}</span>
                                <span className="block text-xs text-slate-400 tabular-nums">{period.time}</span>
                            </td>
                            {period.is_break ? (
                                <td colSpan={5} className="px-4 text-center text-xs font-semibold tracking-wide text-amber-700">
                                    BREAK
                                </td>
                            ) : (
                                period.slots.map((slot, index) => (
                                    <td key={index} className="px-4 py-2.5">
                                        {slot && (
                                            <>
                                                <span className="block font-medium">{slot.subject}</span>
                                                <span className="block text-xs text-slate-400">
                                                    {slot.teacher} · {slot.room}
                                                </span>
                                            </>
                                        )}
                                    </td>
                                ))
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function AttendanceBoard({ attendance }: { attendance: Attendance }) {
    const blanks = attendance.days[0] ? Array.from({ length: attendance.days[0].weekday }) : [];

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-semibold text-slate-800">{attendance.month}</h2>
                <div className="flex flex-wrap gap-2 text-xs">
                    <span className="rounded-full bg-emerald-100 px-2 py-1 text-emerald-700">{attendance.counts.present} present</span>
                    <span className="rounded-full bg-amber-100 px-2 py-1 text-amber-700">{attendance.counts.late} late</span>
                    <span className="rounded-full bg-rose-100 px-2 py-1 text-rose-700">{attendance.counts.absent} absent</span>
                    <span className="rounded-full bg-sky-100 px-2 py-1 text-sky-700">{attendance.counts.leave} leave</span>
                    {attendance.percent !== null && <span className="rounded-full bg-slate-100 px-2 py-1 font-semibold">{attendance.percent}% this month</span>}
                </div>
            </div>
            <div className="grid grid-cols-7 gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => (
                    <div key={day} className="text-center text-[11px] font-semibold text-slate-400 uppercase">
                        {day}
                    </div>
                ))}
                {blanks.map((_, index) => (
                    <div key={`b${index}`} />
                ))}
                {attendance.days.map((day) => (
                    <div key={day.date} className={`rounded-xl px-1 py-2 text-center text-sm ${day.status ? statusTone[day.status] : 'bg-slate-50 text-slate-400'}`}>
                        <div className="font-semibold">{day.day}</div>
                        <div className="text-[10px] capitalize">{day.status ?? '—'}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

const homeworkLabel: Record<string, string> = {
    todo: 'to do',
    overdue: 'past due',
    submitted: 'handed in',
    marked: 'done',
};

export function HomeworkBoard({ items, canSubmit }: { items: HomeworkItem[]; canSubmit: boolean }) {
    const form = useForm({ note: '' });
    const [open, setOpen] = useState<number | null>(null);
    const [query, setQuery] = useState('');
    const [subject, setSubject] = useState('');
    const [teacher, setTeacher] = useState('');
    const [status, setStatus] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');

    const subjects = [...new Set(items.map((item) => item.subject))];
    const teachers = [...new Set(items.map((item) => item.teacher).filter(Boolean))] as string[];
    const stats = {
        todo: items.filter((item) => item.status === 'todo').length,
        overdue: items.filter((item) => item.status === 'overdue' || (item.status === 'submitted' && item.late)).length,
        waiting: items.filter((item) => item.status === 'submitted').length,
    };

    const visible = items.filter((item) => {
        if (query && !item.title.toLowerCase().includes(query.toLowerCase())) return false;
        if (subject && item.subject !== subject) return false;
        if (teacher && item.teacher !== teacher) return false;
        if (status && item.status !== status) return false;
        return true;
    });

    const submit = (id: number) => (event: FormEvent) => {
        event.preventDefault();
        form.post(route('student.homework.submit', id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(null);
            },
        });
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard label="To do" value={stats.todo} tone="text-slate-800" />
                <StatCard label="Past due" value={stats.overdue} tone="text-rose-600" />
                <StatCard label="Waiting to be marked" value={stats.waiting} tone="text-slate-800" />
            </div>

            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 p-3">
                    <label className="relative min-w-[180px] flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search homework..."
                            className="h-10 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm outline-none focus:border-[#b31299]"
                        />
                    </label>
                    <select value={subject} onChange={(event) => setSubject(event.target.value)} className="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                        <option value="">All subjects</option>
                        {subjects.map((name) => <option key={name}>{name}</option>)}
                    </select>
                    <select value={teacher} onChange={(event) => setTeacher(event.target.value)} className="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                        <option value="">All teachers</option>
                        {teachers.map((name) => <option key={name}>{name}</option>)}
                    </select>
                    <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} aria-label="From date" className="h-10 rounded-lg border border-slate-200 px-3 text-sm" />
                    <span className="text-xs text-slate-400">to</span>
                    <input type="date" value={to} onChange={(event) => setTo(event.target.value)} aria-label="To date" className="h-10 rounded-lg border border-slate-200 px-3 text-sm" />
                    <select value={status} onChange={(event) => setStatus(event.target.value)} className="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                        <option value="">Any status</option>
                        <option value="todo">To do</option>
                        <option value="overdue">Past due</option>
                        <option value="submitted">Handed in</option>
                        <option value="marked">Done</option>
                    </select>
                </div>

                {visible.length === 0 ? (
                    <p className="px-6 py-16 text-center text-sm text-slate-400">No homework matches these filters.</p>
                ) : (
                    <table className="w-full min-w-[820px] text-sm">
                        <thead className="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Homework</th>
                                <th className="px-4 py-3 text-left">Subject</th>
                                <th className="px-4 py-3 text-left">Teacher</th>
                                <th className="px-4 py-3 text-left">Due</th>
                                <th className="px-4 py-3 text-center">Status</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {visible.map((item) => {
                                const label = homeworkLabel[item.status] ?? item.status;
                                const late = item.late;
                                const expanded = open === item.id;
                                const rowTone = item.status === 'todo'
                                    ? 'bg-rose-50/30'
                                    : item.status === 'submitted'
                                      ? 'bg-sky-50/40'
                                      : 'hover:bg-slate-50/70';

                                return (
                                    <Fragment key={item.id}>
                                        <tr className={rowTone}>
                                            <td className="px-4 py-3 font-medium text-slate-800">{item.title}</td>
                                            <td className="px-4 py-3 text-slate-600">{item.subject}</td>
                                            <td className="px-4 py-3 text-slate-600">{item.teacher ?? '—'}</td>
                                            <td className={`px-4 py-3 tabular-nums ${item.status === 'overdue' ? 'font-semibold text-rose-600' : 'text-slate-600'}`}>
                                                {item.due_on}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-center gap-1">
                                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusTone[item.status] ?? 'bg-slate-100 text-slate-600'}`}>{label}</span>
                                                    {late && <span className="rounded-full bg-orange-100 px-2 py-0.5 text-[11px] font-semibold text-orange-600">late</span>}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <button type="button" onClick={() => setOpen(expanded ? null : item.id)} aria-label={`View ${item.title}`} className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100">
                                                        <Eye className="h-4 w-4" />
                                                    </button>
                                                    {canSubmit && item.status === 'submitted' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                form.setData('note', item.note ?? '');
                                                                setOpen(item.id);
                                                            }}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                                        >
                                                            <RefreshCw className="h-3.5 w-3.5" /> Change
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                        {expanded && (
                                            <tr className="bg-slate-50/60">
                                                <td colSpan={6} className="px-4 py-4">
                                                    <div className="space-y-2 text-sm">
                                                        {item.instructions && <p className="whitespace-pre-line text-slate-700">{item.instructions}</p>}
                                                        {item.note && <p className="rounded-lg bg-white px-3 py-2 text-slate-600">Your note: {item.note}</p>}
                                                        {item.remarks && <p className="text-xs text-slate-500">Teacher note: {item.remarks}</p>}
                                                        {canSubmit && ['todo', 'overdue', 'submitted'].includes(item.status) && (
                                                            <form onSubmit={submit(item.id)} className="flex flex-col gap-2 sm:flex-row">
                                                                <input
                                                                    value={form.data.note}
                                                                    onChange={(event) => form.setData('note', event.target.value)}
                                                                    placeholder="Add a note for your teacher"
                                                                    className="h-10 flex-1 rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-[#b31299]"
                                                                />
                                                                <button className="inline-flex h-10 items-center justify-center gap-1 rounded-lg bg-[#b31299] px-4 text-sm font-semibold text-white hover:bg-[#9a0e84]">
                                                                    <Upload className="h-4 w-4" /> {item.status === 'submitted' ? 'Update' : 'Submit'}
                                                                </button>
                                                            </form>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

function StatCard({ label, value, tone }: { label: string; value: number; tone: string }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">{label}</p>
            <p className={`mt-1 text-2xl font-bold tabular-nums ${tone}`}>{value}</p>
        </div>
    );
}

export function ResultsBoard({ results }: { results: Results }) {
    const [exam, setExam] = useState('');
    const summary = results.summary;

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-2xl font-bold text-slate-900">My Results</h1>
                <p className="mt-1 text-sm text-slate-500">Only officially published results and computed annual decisions appear here.</p>
            </div>

            {summary ? (
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#a91093] via-[#b31299] to-[#7c3aed] p-5 text-white shadow-lg shadow-fuchsia-500/20">
                    <Trophy className="absolute top-5 right-6 h-7 w-7 text-yellow-300" />
                    <p className="text-[11px] font-semibold tracking-[0.16em] text-white/70 uppercase">Annual result</p>
                    <h2 className="mt-1 text-lg font-semibold">Final academic standing</h2>
                    <div className="mt-4 grid gap-3 sm:grid-cols-4">
                        <ResultStat label="GPA" value={String(Number(summary.gpa))} />
                        <ResultStat label="Class position" value={`#${summary.class_position}`} />
                        <ResultStat label="Section position" value={`#${summary.section_position}`} />
                        <ResultStat label="Decision" value={summary.promotion_status} />
                    </div>
                </section>
            ) : (
                <Empty>No annual result has been published yet.</Empty>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <label className="text-xs font-medium text-slate-600">
                    Session
                    <select className="mt-1 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                        <option>All sessions</option>
                    </select>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    View an exam result
                    <select value={exam} onChange={(event) => setExam(event.target.value)} className="mt-1 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                        <option value="">Choose exam</option>
                        {summary && <option value={summary.exam}>{summary.exam}</option>}
                    </select>
                </label>
            </div>

            {exam && (
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-4 py-3 font-semibold">{exam}</div>
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Subject</th>
                                <th className="px-4 py-3 text-right">Marks</th>
                                <th className="px-4 py-3 text-center">Grade</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {results.subjects.map((subject) => (
                                <tr key={subject.subject}>
                                    <td className="px-4 py-2.5 font-medium">{subject.subject}</td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">{subject.marks}/{subject.full_marks}</td>
                                    <td className="px-4 py-2.5 text-center">{subject.letter_grade}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function ResultStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
            <p className="text-xs text-white/70">{label}</p>
            <p className="mt-0.5 text-2xl font-bold">{value}</p>
        </div>
    );
}

export function FeesBoard({ fees }: { fees: Fees }) {
    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-2xl font-bold text-slate-900">My Fees</h1>
                <p className="mt-1 text-sm text-slate-500">Your invoices and what is still due. Payments are made at the school counter or by your guardian in the parent portal.</p>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <div className={`rounded-2xl border bg-white px-5 py-4 shadow-sm ${fees.total_outstanding > 0 ? 'border-amber-300' : 'border-emerald-200'}`}>
                    <p className={`flex items-center gap-1.5 text-[11px] font-bold tracking-wide uppercase ${fees.total_outstanding > 0 ? 'text-amber-500' : 'text-emerald-600'}`}>
                        <Wallet className="h-3.5 w-3.5" /> Outstanding
                    </p>
                    <p className="mt-1 text-2xl font-bold tabular-nums">{money(fees.total_outstanding)}</p>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <p className="flex items-center gap-1.5 text-[11px] font-bold tracking-wide text-slate-500 uppercase">
                        <FileText className="h-3.5 w-3.5" /> Unpaid invoices
                    </p>
                    <p className="mt-1 text-2xl font-bold tabular-nums">{fees.unpaid_count}</p>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <p className="flex items-center gap-1.5 text-[11px] font-bold tracking-wide text-slate-500 uppercase">
                        <CalendarCheck className="h-3.5 w-3.5" /> Oldest due date
                    </p>
                    <p className="mt-1 text-2xl font-bold tabular-nums">{fees.oldest_due_date ?? '—'}</p>
                </div>
            </div>

            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                {fees.invoices.length === 0 ? (
                    <p className="p-6 text-sm text-slate-400">No invoices yet. Invoices appear here once the school generates them.</p>
                ) : (
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="border-b border-slate-100 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Invoice</th>
                                <th className="px-4 py-3 text-left">Due date</th>
                                <th className="px-4 py-3 text-right">Payable</th>
                                <th className="px-4 py-3 text-right">Paid</th>
                                <th className="px-4 py-3 text-right">Outstanding</th>
                                <th className="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {fees.invoices.map((invoice) => (
                                <tr key={invoice.invoice_no}>
                                    <td className="px-4 py-3">
                                        <span className="block font-medium">{invoice.label}</span>
                                        <span className="block text-xs text-slate-400">{invoice.invoice_no}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{invoice.due_date}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{invoice.payable.toLocaleString()}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{invoice.paid.toLocaleString()}</td>
                                    <td className={`px-4 py-3 text-right font-semibold tabular-nums ${invoice.outstanding > 0 ? 'text-amber-600' : 'text-slate-700'}`}>{invoice.outstanding.toLocaleString()}</td>
                                    <td className="px-4 py-3 text-center">
                                        <Pill value={invoice.status} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Payment history</div>
                {fees.payments.length === 0 ? (
                    <p className="p-6 text-sm text-slate-400">Once a payment is made at the counter, its receipt appears here.</p>
                ) : (
                    <table className="w-full min-w-[680px] text-sm">
                        <thead className="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Receipt</th>
                                <th className="px-4 py-3 text-left">Date</th>
                                <th className="px-4 py-3 text-left">Method</th>
                                <th className="px-4 py-3 text-right">Amount</th>
                                <th className="px-4 py-3 text-center">Status</th>
                                <th className="px-4 py-3 text-right">Copy</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {fees.payments.map((payment) => (
                                <tr key={payment.receipt_no}>
                                    <td className="px-4 py-3">
                                        <span className="block font-medium">{payment.receipt_no}</span>
                                        <span className="block text-xs text-slate-400">{(fees.invoices.find((invoice) => invoice.paid > 0) ?? fees.invoices[0])?.invoice_no ?? ''}</span>
                                    </td>
                                    <td className="px-4 py-3">{payment.date}</td>
                                    <td className="px-4 py-3 capitalize">{payment.method}</td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">{(payment.amount + payment.late_fee).toLocaleString()}</td>
                                    <td className="px-4 py-3 text-center">
                                        <Pill value={payment.status} />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button type="button" onClick={() => window.print()} className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            <Download className="h-3.5 w-3.5" /> PDF
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

export function LibraryBoard({ library }: { library: Library }) {
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <span className="rounded-lg bg-indigo-50 px-3 py-1.5 font-semibold text-indigo-700">Card {library.card_no ?? '—'}</span>
                <span className="text-slate-500">Member since {library.member_since ?? '—'}</span>
                <span className={`ml-auto font-semibold ${library.fine_due > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                    {library.fine_due > 0 ? `Fine due: Tk ${library.fine_due}` : 'No fines due'}
                </span>
            </div>
            {library.loans.length === 0 ? (
                <Empty>No library loans yet.</Empty>
            ) : (
                <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full min-w-[680px] text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Book</th>
                                <th className="px-4 py-3 text-left">Issued</th>
                                <th className="px-4 py-3 text-left">Due</th>
                                <th className="px-4 py-3 text-left">Returned</th>
                                <th className="px-4 py-3 text-center">Status</th>
                                <th className="px-4 py-3 text-right">Fine</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {library.loans.map((loan) => (
                                <tr key={loan.title}>
                                    <td className="px-4 py-2.5">
                                        <span className="block font-medium">{loan.title}</span>
                                        <span className="block text-xs text-slate-400">{loan.author}</span>
                                    </td>
                                    <td className="px-4 py-2.5 tabular-nums">{loan.issued_on}</td>
                                    <td className="px-4 py-2.5 tabular-nums">{loan.due_on}</td>
                                    <td className="px-4 py-2.5 tabular-nums">{loan.returned_on ?? '—'}</td>
                                    <td className="px-4 py-2.5 text-center">
                                        <Pill value={loan.overdue ? 'overdue' : loan.status} />
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">{loan.fine}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

export function BusBoard({ bus }: { bus: Bus }) {
    if (!bus) {
        return <Empty>No school bus is assigned for this student.</Empty>;
    }

    return (
        <section className="max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="grid gap-4 p-5 sm:grid-cols-2">
                <Field label="Route" value={bus.route} />
                <Field label="Vehicle" value={bus.vehicle ?? '—'} />
                <Field label="Stop" value={bus.stop} />
                <Field label="Landmark" value={bus.landmark ?? '—'} />
                <Field label="Pickup" value={bus.pickup_time ?? '—'} />
                <Field label="Drop" value={bus.drop_time ?? '—'} />
                <Field label="Driver" value={bus.driver ?? '—'} />
                <Field label="Phone" value={bus.driver_phone ?? '—'} />
            </div>
            <p className="border-t border-slate-100 px-5 py-3 text-xs text-slate-400">
                Fare {money(bus.fare)} / month · riding since {bus.since}
            </p>
        </section>
    );
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <span className="block text-xs text-slate-400">{label}</span>
            <span className="font-semibold">{value}</span>
        </div>
    );
}

export function DocumentsBoard({ documents }: { documents: DocumentRow[] }) {
    if (documents.length === 0) {
        return <Empty>No documents have been issued yet.</Empty>;
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {documents.map((document) => (
                <article key={document.serial_no} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="font-semibold">{document.type}</h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {document.serial_no}
                        {document.session ? ` · ${document.session}` : ''}
                    </p>
                    <p className="mt-3 text-xs text-slate-400">Issued {document.issue_date}</p>
                </article>
            ))}
        </div>
    );
}

export function LeaveBoard({ leaves, action, child }: { leaves: LeaveRow[]; action: string; child?: number }) {
    const form = useForm({
        child: child ?? '',
        leave_type: 'Sick Leave',
        start_date: '',
        end_date: '',
        reason: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((payload) => ({ ...payload, ...(child ? { child } : {}) }));
        form.post(action, { preserveScroll: true, onSuccess: () => form.reset('reason', 'start_date', 'end_date') });
    };

    return (
        <div className="space-y-4">
            <form onSubmit={submit} className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <label className="block text-xs font-medium text-slate-600">
                    Type
                    <select
                        value={form.data.leave_type}
                        onChange={(event) => form.setData('leave_type', event.target.value)}
                        className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm"
                    >
                        <option>Sick Leave</option>
                        <option>Casual Leave</option>
                    </select>
                </label>
                <label className="block text-xs font-medium text-slate-600">
                    From
                    <input type="date" required value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" />
                </label>
                <label className="block text-xs font-medium text-slate-600">
                    To
                    <input type="date" required value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" />
                </label>
                <label className="block text-xs font-medium text-slate-600 sm:col-span-2">
                    Reason
                    <textarea required maxLength={500} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" rows={3} />
                </label>
                <button disabled={form.processing} className="h-10 rounded-lg bg-indigo-600 text-sm font-semibold text-white sm:col-span-2">
                    Submit application
                </button>
            </form>
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-100 px-4 py-3 text-sm font-semibold">My applications</div>
                {leaves.length === 0 ? (
                    <p className="p-6 text-sm text-slate-400">No leave applications yet.</p>
                ) : (
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Type</th>
                                <th className="px-4 py-3 text-left">Dates</th>
                                <th className="px-4 py-3 text-right">Days</th>
                                <th className="px-4 py-3 text-left">Reason</th>
                                <th className="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {leaves.map((leave) => (
                                <tr key={leave.id}>
                                    <td className="px-4 py-2.5 font-medium">{leave.leave_type}</td>
                                    <td className="px-4 py-2.5">
                                        {leave.start_date} – {leave.end_date}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">{leave.days}</td>
                                    <td className="px-4 py-2.5">{leave.reason}</td>
                                    <td className="px-4 py-2.5 text-center">
                                        <Pill value={leave.status} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

const eventTone: Record<string, string> = {
    exam: 'bg-indigo-100 text-indigo-700',
    holiday: 'bg-rose-100 text-rose-700',
    event: 'bg-emerald-100 text-emerald-700',
    meeting: 'bg-amber-100 text-amber-700',
};

export function CalendarBoard({ calendar }: { calendar: CalendarData }) {
    const blanks = calendar.days[0] ? Array.from({ length: calendar.days[0].weekday }) : [];

    return (
        <div className="space-y-4">
            <h2 className="text-center text-sm font-semibold">{calendar.month}</h2>
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="grid min-w-[560px] grid-cols-7">
                    {['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'].map((day) => (
                        <div key={day} className="border-b border-slate-100 px-2 py-2 text-center text-[11px] font-semibold text-slate-400">
                            {day}
                        </div>
                    ))}
                    {blanks.map((_, index) => (
                        <div key={`b${index}`} className="min-h-[76px] border-r border-b border-slate-100 bg-slate-50" />
                    ))}
                    {calendar.days.map((day) => (
                        <div key={day.date} className="min-h-[76px] border-r border-b border-slate-100 p-1.5 text-xs">
                            <span className={`inline-flex h-6 w-6 items-center justify-center rounded-full tabular-nums ${day.date === new Date().toISOString().slice(0, 10) ? 'bg-indigo-600 font-bold text-white' : ''}`}>
                                {day.day}
                            </span>
                            {day.items.map((item) => (
                                <div key={item.title} className={`mt-1 truncate rounded px-1 py-0.5 text-[10px] font-medium ${eventTone[item.type] ?? 'bg-slate-100'}`}>
                                    {item.title}
                                </div>
                            ))}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
