import { Link, router, useForm } from '@inertiajs/react';
import { Download, Eye, Pencil, ReceiptText, Search, Upload, UserRound } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

type StudentRow = {
    id: number;
    name: string;
    admissionNo: string;
    class: string;
    roll: string;
    gender: string;
    dob: string;
    shift: string;
    mobile: string;
    status: string;
};

export function StudentsView({ module }: { module: Record<string, any> }) {
    const filters = module.filters as { q: string; session: string; class: string; section: string; status: string; page: number };
    const [q, setQ] = useState(filters.q);
    const rows = module.rows as StudentRow[];

    const visit = (next: Record<string, string | number>) => {
        router.get('/app/students', { ...filters, page: 1, ...next }, { preserveState: true, replace: true });
    };

    const search = (event: FormEvent) => {
        event.preventDefault();
        visit({ q });
    };

    return (
        <div className="mx-auto max-w-[1600px] space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{module.title}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">{module.subtitle}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={() => window.print()} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
                        Print
                    </button>
                    <button type="button" className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
                        <Download className="h-4 w-4" /> Export
                    </button>
                    <button type="button" className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
                        <Upload className="h-4 w-4" /> Import
                    </button>
                    {module.canAdmit && (
                        <Link href="/app/students/new" className="rounded-lg bg-[#b31299] px-3 py-2 text-sm font-semibold text-white hover:bg-[#9a0e84]">
                            + New admission
                        </Link>
                    )}
                </div>
            </div>
            <form onSubmit={search} className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
                <input
                    aria-label="Name, admission no, or guardian mobile"
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                    placeholder="Name, admission no, or guardian mobile"
                    className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
                />
                <select aria-label="Session" value={filters.session} onChange={(event) => visit({ session: event.target.value })} className="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="2026">2026 (current)</option>
                    <option value="all">All sessions</option>
                </select>
                <select aria-label="All classes" value={filters.class} onChange={(event) => visit({ class: event.target.value, section: '' })} className="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">All classes</option>
                    {(module.classes as string[]).map((name) => (
                        <option key={name}>{name}</option>
                    ))}
                </select>
                <select aria-label="All sections" value={filters.section} disabled={filters.class === ''} onChange={(event) => visit({ section: event.target.value })} className="rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-50">
                    <option value="">All sections</option>
                    <option>A</option>
                    <option>B</option>
                </select>
                <select aria-label="Status" value={filters.status} onChange={(event) => visit({ status: event.target.value })} className="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="active">Active</option>
                    <option value="transferred">Transferred</option>
                    <option value="dropped">Dropped</option>
                    <option value="all">All statuses</option>
                </select>
            </form>
            <p className="text-sm text-slate-500">
                {module.total} students · showing {module.from}–{module.to}
            </p>
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                {rows.length === 0 ? (
                    <div className="px-6 py-16 text-center">
                        <h3 className="font-semibold text-slate-800">No students found</h3>
                        <p className="mt-1 text-sm text-slate-500">Try changing the filters, or admit the first student.</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[960px] text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                {['Student', 'Admission no', 'Class', 'Roll', 'Gender', 'Date of birth', 'Shift', 'Guardian mobile', 'Status', 'Actions'].map((header) => (
                                    <th key={header} className="px-4 py-3 text-left font-semibold">
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((row) => (
                                <tr key={row.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3 font-medium text-slate-900">{row.name}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-500">{row.admissionNo}</td>
                                    <td className="px-4 py-3">{row.class}</td>
                                    <td className="px-4 py-3">{row.roll}</td>
                                    <td className="px-4 py-3">{row.gender}</td>
                                    <td className="px-4 py-3">{row.dob}</td>
                                    <td className="px-4 py-3">{row.shift}</td>
                                    <td className="px-4 py-3">{row.mobile}</td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-bold text-emerald-700">{row.status}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link href={`/app/students/${row.id}`} title="View profile" aria-label={`View ${row.name}`} className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-[#b31299]">
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            <button type="button" title="Edit student" aria-label={`Edit ${row.name}`} className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-[#b31299]">
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
            <div className="flex justify-end gap-2">
                <Link href={`/app/students?${query({ ...filters, page: Math.max(1, filters.page - 1) })}`} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                    Prev
                </Link>
                <Link href={`/app/students?${query({ ...filters, page: Math.min(module.lastPage, filters.page + 1) })}`} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                    Next
                </Link>
            </div>
        </div>
    );
}

export function ProfileView({ module }: { module: Record<string, any> }) {
    const student = module.student;

    return (
        <div className="mx-auto max-w-4xl space-y-6">
            <div>
                <p className="text-sm font-medium text-slate-500">Student Profile</p>
                <h1 className="text-2xl font-semibold text-slate-900">{student.name}</h1>
                <p className="mt-1 text-sm text-slate-600">{module.subtitle}</p>
                <span className="mt-2 inline-flex rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-bold text-emerald-700">{student.status}</span>
            </div>
            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="font-semibold text-slate-900">Personal details</h2>
                <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                    <Detail label="Date of birth" value={student.dob} />
                    <Detail label="Gender" value={student.gender} />
                    <Detail label="Religion" value={student.religion} />
                    <Detail label="Mobile" value={student.mobile} />
                    <Detail label="Email" value={student.email} />
                    <Detail label="Address" value={student.address} />
                    <Detail label="Login username" value={student.username} />
                </dl>
            </section>
            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="font-semibold text-slate-900">Guardians</h2>
                <p className="mt-3 font-medium">{student.guardian}</p>
                <p className="text-sm text-slate-500">{student.guardianMobile}</p>
            </section>
            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="font-semibold text-slate-900">Enrollment history</h2>
                <p className="mt-3 text-sm text-slate-700">{student.enrollment}</p>
            </section>
        </div>
    );
}

export function AdmissionView({ module }: { module: Record<string, any> }) {
    const form = useForm({
        name: '',
        class_name: 'Class 8',
        section: 'A',
        gender: 'Male',
        date_of_birth: '',
        guardian_name: '',
        guardian_mobile: '',
    });

    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <div>
                <h1 className="text-2xl font-semibold text-slate-900">{module.title}</h1>
                <p className="mt-0.5 text-sm text-slate-600">{module.subtitle}</p>
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('office.students.store'));
                }}
                className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <Field label="Student name" value={form.data.name} onChange={(value) => form.setData('name', value)} />
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">Class</span>
                        <select value={form.data.class_name} onChange={(event) => form.setData('class_name', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                            {(module.classes as string[]).map((name) => (
                                <option key={name}>{name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">Section</span>
                        <select value={form.data.section} onChange={(event) => form.setData('section', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                            {(module.sections as string[]).map((name) => (
                                <option key={name}>{name}</option>
                            ))}
                        </select>
                    </label>
                </div>
                <label className="block text-sm">
                    <span className="font-medium text-slate-700">Gender</span>
                    <select value={form.data.gender} onChange={(event) => form.setData('gender', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="font-medium text-slate-700">Date of birth</span>
                    <input type="date" value={form.data.date_of_birth} onChange={(event) => form.setData('date_of_birth', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
                </label>
                <Field label="Guardian name" value={form.data.guardian_name} onChange={(value) => form.setData('guardian_name', value)} />
                <Field label="Guardian mobile" value={form.data.guardian_mobile} onChange={(value) => form.setData('guardian_mobile', value)} />
                <button disabled={form.processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                    Enroll student
                </button>
            </form>
        </div>
    );
}

export function AttendanceView({ module }: { module: Record<string, any> }) {
    const students = module.students as { id: number; roll: string; name: string; status: string }[];
    const [marks, setMarks] = useState<Record<number, string>>({});

    useEffect(() => {
        setMarks(Object.fromEntries(students.map((student) => [student.id, student.status])));
    }, [module.section, module.date, students]);

    const counts = { present: 0, absent: 0, late: 0, leave: 0 };
    students.forEach((student) => {
        const status = (marks[student.id] ?? 'present') as keyof typeof counts;
        if (status in counts) {
            counts[status] += 1;
        }
    });

    const load = (section: string, date: string) => {
        router.get('/app/attendance', { section, date }, { preserveState: true, replace: true });
    };

    return (
        <div className="mx-auto max-w-[1600px] space-y-6">
            <div>
                <h1 className="text-2xl font-semibold text-slate-900">{module.title}</h1>
                <p className="mt-0.5 text-sm text-slate-600">{module.subtitle}</p>
            </div>
            <div className="flex flex-wrap items-end gap-3">
                <label className="text-sm">
                    <span className="font-medium text-slate-700">Section</span>
                    <select aria-label="Select section" value={module.section} onChange={(event) => load(event.target.value, module.date)} className="mt-1 block rounded-lg border border-slate-200 px-3 py-2">
                        {(module.sections as string[]).map((section) => (
                            <option key={section}>{section}</option>
                        ))}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="font-medium text-slate-700">Date</span>
                    <input aria-label="Attendance date" type="date" value={module.date} onChange={(event) => load(module.section, event.target.value)} className="mt-1 block rounded-lg border border-slate-200 px-3 py-2" />
                </label>
                <button type="button" onClick={() => setMarks(Object.fromEntries(students.map((student) => [student.id, 'present'])))} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium">
                    All present
                </button>
                <button
                    type="button"
                    onClick={() =>
                        router.post(route('office.attendance.store'), {
                            section: module.section,
                            date: module.date,
                            marks: students.map((student) => ({ user_id: student.id, status: marks[student.id] ?? 'present' })),
                        })
                    }
                    className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white"
                >
                    Save attendance
                </button>
            </div>
            <div className="flex flex-wrap gap-3 text-sm">
                <span className="rounded-full bg-emerald-50 px-3 py-1 font-medium text-emerald-700">Present {counts.present}</span>
                <span className="rounded-full bg-rose-50 px-3 py-1 font-medium text-rose-700">Absent {counts.absent}</span>
                <span className="rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-700">Late {counts.late}</span>
                <span className="rounded-full bg-sky-50 px-3 py-1 font-medium text-sky-700">Leave {counts.leave}</span>
            </div>
            {students.length === 0 ? (
                <div className="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center">
                    <h3 className="font-semibold">No students to mark</h3>
                    <p className="mt-1 text-sm text-slate-500">This section has no active students enrolled on the selected date.</p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Roll</th>
                                <th className="px-4 py-3 text-left">Student</th>
                                <th className="px-4 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {students.map((student) => (
                                <tr key={student.id}>
                                    <td className="px-4 py-3">{student.roll}</td>
                                    <td className="px-4 py-3 font-medium">{student.name}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {['present', 'absent', 'late', 'leave'].map((status) => (
                                                <button
                                                    key={status}
                                                    type="button"
                                                    onClick={() => setMarks((current) => ({ ...current, [student.id]: status }))}
                                                    className={`h-8 rounded-lg border px-2.5 text-xs font-medium capitalize ${(marks[student.id] ?? student.status) === status ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 text-slate-600'}`}
                                                >
                                                    {status}
                                                </button>
                                            ))}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

export function NoticesView({ module }: { module: Record<string, any> }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState(module.q as string);
    const form = useForm({ title: '', body: '', published_on: module.today ?? new Date().toISOString().slice(0, 10), expires_on: '' });
    const notices = module.notices as { id: number; title: string; body: string; published: string; expires: string | null; active: boolean }[];

    return (
        <div className="mx-auto max-w-4xl space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold text-slate-900">Notices</h1>
                <button type="button" onClick={() => setOpen(true)} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">
                    New notice
                </button>
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get('/app/notices', { q }, { preserveState: true, replace: true });
                }}
            >
                <input aria-label="Search notices" value={q} onChange={(event) => setQ(event.target.value)} placeholder="Search notices…" className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </form>
            {open && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('office.notices.store'));
                    }}
                    className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h2 className="font-semibold">New notice</h2>
                    <Field label="Title" value={form.data.title} onChange={(value) => form.setData('title', value)} placeholder="Annual sports day — schedule announced" />
                    <label className="block text-sm">
                        <span className="font-medium text-slate-700">Body</span>
                        <textarea aria-label="Body" value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} placeholder="Notice content…" className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" rows={4} />
                    </label>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block text-sm">
                            <span className="font-medium text-slate-700">Publish date</span>
                            <input aria-label="Publish date" type="date" value={form.data.published_on} onChange={(event) => form.setData('published_on', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
                        </label>
                        <label className="block text-sm">
                            <span className="font-medium text-slate-700">Expiry date (optional)</span>
                            <input aria-label="Expiry date" type="date" value={form.data.expires_on} onChange={(event) => form.setData('expires_on', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
                        </label>
                    </div>
                    <div className="flex gap-2">
                        <button type="button" onClick={() => setOpen(false)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            Cancel
                        </button>
                        <button disabled={form.processing || form.data.title === '' || form.data.body === ''} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">
                            Publish notice
                        </button>
                    </div>
                </form>
            )}
            <div className="space-y-3">
                {notices.map((notice) => (
                    <article key={notice.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="font-semibold text-slate-900">{notice.title}</h3>
                        <p className="mt-2 text-sm text-slate-600">{notice.body}</p>
                        <p className="mt-3 text-xs text-slate-400">
                            {notice.active ? 'Active' : 'Expired'} · Published {notice.published}
                            {notice.expires ? ` · Expires ${notice.expires}` : ''}
                        </p>
                    </article>
                ))}
            </div>
        </div>
    );
}

export function HomeworkView({ module }: { module: Record<string, any> }) {
    const [q, setQ] = useState(module.q as string);
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: '',
        subject: 'Mathematics',
        class_name: 'Class 8',
        section: 'A',
        due_on: new Date().toISOString().slice(0, 10),
        instructions: '',
    });
    const rows = module.rows as { title: string; teacher: string; class: string; subject: string; due: string; status: string }[];

    return (
        <div className="mx-auto max-w-[1600px] space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{module.title}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">{module.subtitle}</p>
                </div>
                <button type="button" onClick={() => setOpen(!open)} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">
                    Set homework
                </button>
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get('/app/homework', { q }, { preserveState: true, replace: true });
                }}
            >
                <input aria-label="Search by title" value={q} onChange={(event) => setQ(event.target.value)} placeholder="Search by title…" className="w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </form>
            {open && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('office.homework.store'));
                    }}
                    className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2"
                >
                    <Field label="Title" value={form.data.title} onChange={(value) => form.setData('title', value)} />
                    <label className="text-sm">
                        <span className="font-medium text-slate-700">Subject</span>
                        <select value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                            {(module.subjects as string[]).map((subject) => (
                                <option key={subject}>{subject}</option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        <span className="font-medium text-slate-700">Class</span>
                        <select value={form.data.class_name} onChange={(event) => form.setData('class_name', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                            {(module.classes as string[]).map((name) => (
                                <option key={name}>{name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        <span className="font-medium text-slate-700">Section</span>
                        <select value={form.data.section} onChange={(event) => form.setData('section', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                            {(module.sections as string[]).map((name) => (
                                <option key={name}>{name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        <span className="font-medium text-slate-700">Due</span>
                        <input type="date" aria-label="Due" value={form.data.due_on} onChange={(event) => form.setData('due_on', event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
                    </label>
                    <Field label="Instructions" value={form.data.instructions} onChange={(value) => form.setData('instructions', value)} />
                    <button disabled={form.processing} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white md:col-span-2 md:w-fit">
                        Publish homework
                    </button>
                </form>
            )}
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full min-w-[720px] text-sm">
                    <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                        <tr>
                            {['Homework', 'Class', 'Subject', 'Due', 'Status'].map((header) => (
                                <th key={header} className="px-4 py-3 text-left">{header}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.map((row) => (
                            <tr key={`${row.title}-${row.class}`}>
                                <td className="px-4 py-3">
                                    <p className="font-medium">{row.title}</p>
                                    <p className="text-xs text-slate-400">{row.teacher}</p>
                                </td>
                                <td className="px-4 py-3">{row.class}</td>
                                <td className="px-4 py-3">{row.subject}</td>
                                <td className="px-4 py-3">{row.due}</td>
                                <td className="px-4 py-3 capitalize">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export function LeaveView({ module }: { module: Record<string, any> }) {
    const rows = module.rows as { id: number; name: string; type: string; dates: string; reason: string; status: string }[];

    return (
        <div className="mx-auto max-w-[1600px] space-y-6">
            <div>
                <h1 className="text-2xl font-semibold text-slate-900">{module.title}</h1>
                <p className="mt-0.5 text-sm text-slate-600">{module.subtitle}</p>
            </div>
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full min-w-[760px] text-sm">
                    <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                        <tr>
                            {['Person', 'Type', 'Dates', 'Reason', 'Status', 'Decision'].map((header) => (
                                <th key={header} className="px-4 py-3 text-left">{header}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td className="px-4 py-3 font-medium">{row.name}</td>
                                <td className="px-4 py-3">{row.type}</td>
                                <td className="px-4 py-3">{row.dates}</td>
                                <td className="px-4 py-3">{row.reason}</td>
                                <td className="px-4 py-3 capitalize">{row.status}</td>
                                <td className="px-4 py-3">
                                    {row.status === 'pending' && (
                                        <div className="flex gap-2">
                                            <button type="button" onClick={() => router.post(route('office.leave.decide', row.id), { status: 'approved' })} className="rounded-lg bg-emerald-600 px-2 py-1 text-xs font-semibold text-white">
                                                Approve
                                            </button>
                                            <button type="button" onClick={() => router.post(route('office.leave.decide', row.id), { status: 'rejected' })} className="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700">
                                                Reject
                                            </button>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export function FeesView({ module }: { module: Record<string, any> }) {
    const rows = module.rows as {
        id: number;
        student: string;
        admissionNo: string | null;
        class: string | null;
        section: string | null;
        label: string;
        number: string;
        payable: number;
        paid: number;
        status: string;
    }[];
    const classes = [...new Set(rows.map((row) => row.class).filter(Boolean))] as string[];
    const [className, setClassName] = useState('');
    const [section, setSection] = useState('');
    const [query, setQuery] = useState('');
    const active = className !== '' || query.trim() !== '';
    const visible = rows.filter((row) => {
        if (className && row.class !== className) return false;
        if (section && row.section !== section) return false;
        const needle = query.trim().toLowerCase();
        if (needle && !`${row.student} ${row.admissionNo ?? ''} ${row.number}`.toLowerCase().includes(needle)) return false;
        return true;
    });

    return (
        <div className="mx-auto max-w-[1600px] space-y-5">
            <div>
                <h1 className="text-2xl font-bold text-slate-900">{module.title}</h1>
                <p className="mt-0.5 text-sm text-slate-600">{module.subtitle}</p>
            </div>

            <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div className="flex items-center gap-2">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-pink-50 text-[#b31299]">
                            <UserRound className="h-4 w-4" />
                        </span>
                        <div>
                            <h2 className="font-semibold text-slate-900">Find student</h2>
                            <p className="text-xs text-slate-400">Class or section change will clear the current selection.</p>
                        </div>
                    </div>
                    <div className="text-right">
                        <p className="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">Collected today</p>
                        <p className="font-bold text-slate-800">Tk {Number(module.collectedToday ?? 0).toLocaleString()}</p>
                    </div>
                </div>
                <div className="mt-3 grid gap-3 md:grid-cols-4">
                    <label className="text-xs font-medium text-slate-600">
                        Session
                        <select className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700">
                            <option>2026 · Current</option>
                        </select>
                    </label>
                    <label className="text-xs font-medium text-slate-600">
                        Class
                        <select
                            value={className}
                            onChange={(event) => {
                                setClassName(event.target.value);
                                setSection('');
                            }}
                            className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700"
                        >
                            <option value="">Choose class</option>
                            {classes.map((name) => <option key={name}>{name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-medium text-slate-600">
                        Section
                        <select disabled={className === ''} value={section} onChange={(event) => setSection(event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 disabled:bg-slate-50">
                            <option value="">All sections</option>
                            <option>A</option>
                            <option>B</option>
                        </select>
                    </label>
                    <label className="text-xs font-medium text-slate-600">
                        Roll, admission number or name
                        <span className="relative mt-1 block">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search student..."
                                className="h-10 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm outline-none focus:border-[#b31299]"
                            />
                        </span>
                    </label>
                </div>
            </section>

            {!active ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-24 text-center shadow-sm">
                    <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <ReceiptText className="h-5 w-5" />
                    </span>
                    <h2 className="mt-3 font-semibold text-slate-700">Select a student to start</h2>
                    <p className="mt-1 text-sm text-slate-400">Choose a class to browse students or use the search field.</p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {visible.length === 0 ? (
                        <p className="px-6 py-16 text-center text-sm text-slate-400">No invoices match this student or filter.</p>
                    ) : (
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                                <tr>
                                    {['Student', 'Invoice', 'Payable', 'Paid', 'Status', 'Collect'].map((header) => (
                                        <th key={header} className="px-4 py-3 text-left">{header}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {visible.map((row) => (
                                    <tr key={row.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <span className="block font-medium">{row.student}</span>
                                            <span className="block text-xs text-slate-400">{row.class}{row.section ? ` · ${row.section}` : ''}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.label}
                                            <span className="mt-0.5 block font-mono text-xs text-slate-400">{row.number}</span>
                                        </td>
                                        <td className="px-4 py-3">Tk {row.payable.toLocaleString()}</td>
                                        <td className="px-4 py-3">Tk {row.paid.toLocaleString()}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${row.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : row.status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'}`}>{row.status}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.status !== 'paid' && (
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(route('office.fees.collect', row.id), { amount: row.payable - row.paid })}
                                                    className="rounded-lg bg-[#b31299] px-2.5 py-1 text-xs font-semibold text-white hover:bg-[#9a0e84]"
                                                >
                                                    Collect Tk {(row.payable - row.paid).toLocaleString()}
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-slate-400 uppercase">{label}</dt>
            <dd className="mt-1 text-slate-800">{value}</dd>
        </div>
    );
}

function Field({ label, value, onChange, placeholder }: { label: string; value: string; onChange: (value: string) => void; placeholder?: string }) {
    return (
        <label className="block text-sm">
            <span className="font-medium text-slate-700">{label}</span>
            <input aria-label={label} value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
        </label>
    );
}

function query(filters: { q: string; session: string; class: string; section: string; status: string; page: number }) {
    return new URLSearchParams({
        q: filters.q,
        session: filters.session,
        class: filters.class,
        section: filters.section,
        status: filters.status,
        page: String(filters.page),
    }).toString();
}
