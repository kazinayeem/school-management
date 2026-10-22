import { useUrlTab } from '@/hooks/use-url-tab';
import { UserFiles, type UserFile } from '@/components/user-files';
import { Avatar, PhotoUpload } from '@/components/avatar';
import { Badge, Card, Field, ghostBtn, inputCls, money, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { BookOpen, Bus, CalendarCheck, Download, FileText, IdCard, Printer, Wallet } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Student = {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    photo_url: string | null;
    admission_no: string;
    class_name: string;
    section: string;
    roll_no: string;
    session: string;
    gender: string | null;
    date_of_birth: string | null;
    religion: string | null;
    address: string | null;
    status: string;
    guardian_name: string | null;
    guardian_mobile: string | null;
    shift: string | null;
    active: boolean;
};
type Exam = {
    exam: string;
    subjects: { subject: string; marks: number; full_marks: number; grade: string; point: number | null; failed: boolean; absent: boolean }[];
    total: number;
    full_total: number;
    gpa: number | null;
    grade: string | null;
    failed: boolean;
    class_position: number | null;
    section_position: number | null;
};
type Note = {
    id: number;
    kind: string;
    severity: string | null;
    title: string;
    details: string | null;
    action_taken: string | null;
    occurred_on: string;
    visible_to_parent: number;
    author: string | null;
};
type Props = {
    navigation: Nav;
    roleLabel: string;
    student: Student;
    parents: { id: number; name: string; phone: string | null; email: string }[];
    attendance: {
        percent: number | null;
        present: number;
        late: number;
        absent: number;
        leave: number;
        days: number;
        minimum: number;
        recent: { date: string; status: string }[];
    };
    results: Exam[];
    fees: {
        invoices: {
            id: number;
            invoice_no: string;
            label: string;
            due_on: string;
            payable: number;
            paid: number;
            status: string;
            late_fee: number;
        }[];
        payments: { id: number; receipt_no: string; paid_on: string; method: string; amount: number; late_fee: number }[];
        billed: number;
        paid: number;
        due: number;
    };
    loans: {
        id: number;
        title: string;
        author: string | null;
        issued_on: string;
        due_on: string;
        returned_on: string | null;
        fine: number;
        status: string;
    }[];
    transport: {
        route: string;
        stop: string;
        vehicle: string | null;
        pickup_time: string | null;
        drop_time: string | null;
        fare: number;
        driver: string | null;
        driver_phone: string | null;
    } | null;
    certificates: { id: number; number: string; title: string; issued_on: string }[];
    leave: { id: number; leave_type: string; start_date: string; end_date: string; days: number; status: string; reason: string }[];
    documents: { id: number; type: string; serial_no: string; issue_date: string }[];
    notes: Note[];
    timeline: { date: string; type: string; title: string; detail: string }[];
    can: { edit: boolean; delete: boolean; files?: boolean };
    files: UserFile[];
    fileKinds: Record<string, string>;
};

const tabs = ['overview', 'results', 'attendance', 'fees', 'library', 'transport', 'certificates', 'leave', 'documents', 'notes-conduct'] as const;
const tabLabel: Record<string, string> = {
    overview: 'Overview',
    results: 'Results',
    attendance: 'Attendance',
    fees: 'Fees',
    library: 'Library',
    transport: 'Transport',
    certificates: 'Certificates',
    leave: 'Leave',
    documents: 'Documents',
    'notes-conduct': 'Notes & conduct',
};
const kindTone = { violation: 'red', commendation: 'green', health: 'amber', other: 'slate' } as const;
const kindLabel = { violation: 'Rule violation', commendation: 'Commendation', health: 'Health', other: 'Other' } as const;
const dot: Record<string, string> = {
    attendance: 'bg-amber-500',
    fee: 'bg-green-600',
    library: 'bg-sky-500',
    certificate: 'bg-fuchsia-600',
    leave: 'bg-violet-500',
    note: 'bg-red-500',
    result: 'bg-indigo-600',
};
const statusTone = (s: string) => (s === 'present' ? 'green' : s === 'late' ? 'amber' : s === 'absent' ? 'red' : 'slate');

function Stat({ icon: Icon, label, value, hint }: { icon: typeof Wallet; label: string; value: string; hint?: string }) {
    return (
        <Card className="flex items-center gap-3">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-fuchsia-50 text-fuchsia-700">
                <Icon className="h-5 w-5" />
            </span>
            <div>
                <div className="text-xs text-slate-500">{label}</div>
                <div className="text-lg font-bold">{value}</div>
                {hint && <div className="text-xs text-slate-400">{hint}</div>}
            </div>
        </Card>
    );
}

function NoteForm({ studentId }: { studentId: number }) {
    const { t } = useI18n();
    const form = useForm({
        kind: 'violation',
        severity: 'low',
        title: '',
        details: '',
        action_taken: '',
        occurred_on: new Date().toISOString().slice(0, 10),
        visible_to_parent: false as boolean,
    });

    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                form.post(`/app/students/${studentId}/notes`, {
                    preserveScroll: true,
                    onSuccess: () => form.reset('title', 'details', 'action_taken'),
                });
            }}
        >
            <Card className="space-y-4">
                <h3 className="font-semibold">{t('Add a note')}</h3>
                <div className="grid gap-4 md:grid-cols-4">
                    <Field label="Type" error={form.errors.kind}>
                        <select className={inputCls} value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>
                            {(['violation', 'commendation', 'health', 'other'] as const).map((k) => (
                                <option key={k} value={k}>
                                    {t(kindLabel[k])}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Severity" error={form.errors.severity}>
                        <select
                            className={inputCls}
                            value={form.data.severity}
                            onChange={(e) => form.setData('severity', e.target.value)}
                            disabled={form.data.kind !== 'violation'}
                        >
                            <option value="low">{t('Low')}</option>
                            <option value="medium">{t('Medium')}</option>
                            <option value="high">{t('High')}</option>
                        </select>
                    </Field>
                    <Field label="Date" error={form.errors.occurred_on}>
                        <input
                            type="date"
                            className={inputCls}
                            value={form.data.occurred_on}
                            max={new Date().toISOString().slice(0, 10)}
                            onChange={(e) => form.setData('occurred_on', e.target.value)}
                        />
                    </Field>
                    <Field label="Title" error={form.errors.title}>
                        <input
                            className={inputCls}
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder={t('Short summary')}
                        />
                    </Field>
                    <div className="md:col-span-2">
                        <Field label="What happened" error={form.errors.details}>
                            <textarea
                                rows={3}
                                className={inputCls}
                                value={form.data.details}
                                onChange={(e) => form.setData('details', e.target.value)}
                            />
                        </Field>
                    </div>
                    <div className="md:col-span-2">
                        <Field label="Action taken" error={form.errors.action_taken}>
                            <textarea
                                rows={3}
                                className={inputCls}
                                value={form.data.action_taken}
                                onChange={(e) => form.setData('action_taken', e.target.value)}
                            />
                        </Field>
                    </div>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.visible_to_parent}
                        onChange={(e) => form.setData('visible_to_parent', e.target.checked)}
                    />{' '}
                    {t('Visible to the parent')}
                </label>
                <button disabled={form.processing} className={primaryBtn}>
                    {t('Save note')}
                </button>
            </Card>
        </form>
    );
}

export default function StudentFile(p: Props) {
    const { t } = useI18n();
    const [tab, setTab] = useUrlTab(tabs);
    const s = p.student;
    const violations = p.notes.filter((n) => n.kind === 'violation').length;
    const latest = p.results[0];
    const detail = (label: string, value: string | null) => (
        <div>
            <dt className="text-xs text-slate-400">{t(label)}</dt>
            <dd className="text-sm">{value || '—'}</dd>
        </div>
    );

    return (
        <OfficePage navigation={p.navigation} roleLabel={p.roleLabel} title="Student file" subtitle={`${s.name} · ${s.admission_no}`} wide>
            <Card className="flex flex-wrap items-start gap-6">
                {p.can.edit ? (
                    <PhotoUpload userId={s.id} name={s.name} src={s.photo_url} className="h-28 w-28 text-4xl" />
                ) : (
                    <Avatar name={s.name} src={s.photo_url} className="h-28 w-28 text-4xl" />
                )}
                <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-2xl font-bold">{s.name}</h2>
                        <Badge tone={s.status === 'active' ? 'green' : 'slate'}>
                            {t(s.status === 'active' ? 'Active' : s.status === 'alumni' ? 'Alumni' : 'Inactive')}
                        </Badge>
                        {violations > 0 && <Badge tone="red">{t('{n} rule violation(s)', { n: violations })}</Badge>}
                    </div>
                    <p className="text-sm text-slate-600">
                        {t(s.class_name)} · {t('Section')} {s.section} · {t('Roll')} {s.roll_no} · {t('Session')} {s.session}
                    </p>
                    <p className="text-xs text-slate-400">
                        {t('ID')} {s.admission_no} · {s.username}
                    </p>
                    <div className="flex flex-wrap gap-2 pt-2">
                        <a
                            href={`/students/${s.id}/transcript.pdf`}
                            className={`${primaryBtn} flex items-center gap-2`}
                        >
                            <FileText className="h-4 w-4" /> {t('Transcript (PDF)')}
                        </a>
                        <a href={`/students/${s.id}/file.pdf`} className={`${ghostBtn} flex items-center gap-2`}>
                            <Printer className="h-4 w-4" /> {t('Full student file (PDF)')}
                        </a>
                        <a href={`/app/students/${s.id}/export`} className={`${ghostBtn} flex items-center gap-2`}>
                            <Download className="h-4 w-4" /> {t('Activity (CSV)')}
                        </a>
                        <Link
                            href={`/app/students/id-cards?class=${encodeURIComponent(s.class_name)}&section=${s.section}&roll=${s.roll_no}`}
                            className={`${ghostBtn} flex items-center gap-2`}
                        >
                            <IdCard className="h-4 w-4" /> {t('ID card')}
                        </Link>
                    </div>
                </div>
            </Card>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Stat
                    icon={CalendarCheck}
                    label={t('Attendance')}
                    value={p.attendance.percent === null ? '—' : `${p.attendance.percent}%`}
                    hint={p.attendance.percent !== null && p.attendance.percent < p.attendance.minimum ? t('Below the minimum') : undefined}
                />
                <Stat
                    icon={FileText}
                    label={t('Latest result')}
                    value={latest?.gpa != null ? `GPA ${latest.gpa.toFixed(2)}` : '—'}
                    hint={latest?.exam}
                />
                <Stat icon={Wallet} label={t('Fees due')} value={money(p.fees.due)} />
                <Stat icon={BookOpen} label={t('Books out')} value={String(p.loans.filter((l) => l.status !== 'returned').length)} />
                <Stat icon={Bus} label={t('School bus')} value={p.transport ? p.transport.route : t('No')} />
            </div>

            <div role="tablist" className="flex flex-wrap gap-2 border-b pb-2">
                {tabs.map((k) => (
                    <button
                        key={k}
                        role="tab"
                        aria-selected={tab === k}
                        onClick={() => setTab(k)}
                        className={`rounded-lg px-3 py-1.5 text-sm font-medium ${tab === k ? 'bg-fuchsia-700 text-white' : 'hover:bg-slate-100'}`}
                    >
                        {t(tabLabel[k])}
                        {k === 'notes-conduct' && p.notes.length > 0 && <span className="ms-1 text-xs opacity-80">({p.notes.length})</span>}
                    </button>
                ))}
            </div>

            {tab === 'overview' && (
                <div className="grid gap-4 lg:grid-cols-[1fr_380px]">
                    <Card>
                        <h3 className="mb-3 font-semibold">{t('Everything that happened')}</h3>
                        {p.timeline.length === 0 ? (
                            <p className="text-sm text-slate-400">{t('Nothing recorded yet.')}</p>
                        ) : (
                            <ol className="space-y-3">
                                {p.timeline.map((e, i) => (
                                    <li key={i} className="flex gap-3 text-sm">
                                        <span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${dot[e.type] ?? 'bg-slate-400'}`} />
                                        <div>
                                            <div>
                                                <span className="font-medium">{t(e.title)}</span>{' '}
                                                <span className="text-xs text-slate-400">{e.date}</span>
                                            </div>
                                            {e.detail && <div className="text-xs text-slate-500">{e.detail}</div>}
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Card>
                    <div className="space-y-4">
                        <Card>
                            <h3 className="mb-3 font-semibold">{t('Details')}</h3>
                            <dl className="grid grid-cols-2 gap-3">
                                {detail('Gender', s.gender)}
                                {detail('Date of birth', s.date_of_birth)}
                                {detail('Religion', s.religion)}
                                {detail('Shift', s.shift)}
                                {detail('Phone', s.phone)}
                                {detail('Email', s.email)}
                                <div className="col-span-2">{detail('Address', s.address)}</div>
                            </dl>
                        </Card>
                        <Card>
                            <h3 className="mb-3 font-semibold">{t('Guardians')}</h3>
                            <p className="text-sm">
                                {s.guardian_name ?? '—'}{' '}
                                <span className="text-xs text-slate-500" dir="ltr">
                                    {s.guardian_mobile}
                                </span>
                            </p>
                            {p.parents.map((g) => (
                                <p key={g.id} className="mt-2 text-sm">
                                    {g.name} <span className="text-xs text-slate-500">{g.phone}</span>{' '}
                                    <Badge tone="slate">{t('Parent account')}</Badge>
                                </p>
                            ))}
                        </Card>
                    </div>
                </div>
            )}

            {tab === 'results' &&
                (p.results.length === 0 ? (
                    <Card>
                        <p className="text-sm text-slate-400">{t('No published results yet.')}</p>
                    </Card>
                ) : (
                    p.results.map((r) => (
                        <div key={r.exam} className="space-y-2">
                            <div className="flex flex-wrap items-center gap-3">
                                <h3 className="font-semibold">{r.exam}</h3>
                                {r.gpa !== null && (
                                    <Badge tone={r.failed ? 'red' : 'green'}>
                                        GPA {r.gpa.toFixed(2)} · {r.grade}
                                    </Badge>
                                )}
                                {r.class_position && <span className="text-sm text-slate-500">{t('Position {n}', { n: r.class_position })}</span>}
                            </div>
                            <Table headers={['Subject', 'Marks', 'Grade', 'Point']}>
                                {r.subjects.map((x) => (
                                    <tr key={x.subject} className="hover:bg-slate-50">
                                        <td className="px-4 py-2">{x.subject}</td>
                                        <td className={`px-4 py-2 ${x.failed ? 'text-red-600' : ''}`}>
                                            {x.absent ? t('Absent') : `${x.marks}/${x.full_marks}`}
                                        </td>
                                        <td className="px-4 py-2">{x.grade}</td>
                                        <td className="px-4 py-2">{x.point === null ? '—' : x.point.toFixed(2)}</td>
                                    </tr>
                                ))}
                            </Table>
                        </div>
                    ))
                ))}

            {tab === 'attendance' && (
                <Card className="space-y-4">
                    <div className="flex flex-wrap gap-4 text-sm">
                        <span>
                            {t('Present')}: <b>{p.attendance.present}</b>
                        </span>
                        <span>
                            {t('Late')}: <b>{p.attendance.late}</b>
                        </span>
                        <span>
                            {t('Absent')}: <b>{p.attendance.absent}</b>
                        </span>
                        <span>
                            {t('Leave')}: <b>{p.attendance.leave}</b>
                        </span>
                        <span className="ms-auto font-semibold">{p.attendance.percent === null ? '—' : `${p.attendance.percent}%`}</span>
                    </div>
                    <div className="flex flex-wrap gap-1.5">
                        {p.attendance.recent.map((d) => (
                            <span
                                key={d.date}
                                title={`${d.date} · ${d.status}`}
                                className={`rounded-md px-2 py-1 text-xs ${d.status === 'present' ? 'bg-green-100 text-green-700' : d.status === 'late' ? 'bg-amber-100 text-amber-700' : d.status === 'absent' ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700'}`}
                            >
                                {d.date.slice(5)}
                            </span>
                        ))}
                    </div>
                </Card>
            )}

            {tab === 'fees' && (
                <div className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Stat icon={Wallet} label={t('Billed')} value={money(p.fees.billed)} />
                        <Stat icon={Wallet} label={t('Paid')} value={money(p.fees.paid)} />
                        <Stat icon={Wallet} label={t('Due')} value={money(p.fees.due)} />
                    </div>
                    <Table
                        headers={['Invoice', 'Fee', 'Due date', 'Payable', 'Paid', 'Status']}
                        empty={p.fees.invoices.length === 0 ? 'No invoices yet.' : null}
                    >
                        {p.fees.invoices.map((i) => (
                            <tr key={i.id} className="hover:bg-slate-50">
                                <td className="px-4 py-2 font-mono text-xs">{i.invoice_no}</td>
                                <td className="px-4 py-2">{i.label}</td>
                                <td className="px-4 py-2">{i.due_on}</td>
                                <td className="px-4 py-2">{money(i.payable)}</td>
                                <td className="px-4 py-2">{money(i.paid)}</td>
                                <td className="px-4 py-2">
                                    <Badge tone={i.status === 'paid' ? 'green' : i.status === 'partial' ? 'amber' : 'red'}>
                                        {t(i.status === 'paid' ? 'Paid' : i.status === 'partial' ? 'Partial' : 'Unpaid')}
                                    </Badge>
                                </td>
                            </tr>
                        ))}
                    </Table>
                    <Table headers={['Date', 'Receipt', 'Method', 'Amount']} empty={p.fees.payments.length === 0 ? 'No payments yet.' : null}>
                        {p.fees.payments.map((x) => (
                            <tr key={x.id} className="hover:bg-slate-50">
                                <td className="px-4 py-2">{x.paid_on}</td>
                                <td className="px-4 py-2 font-mono text-xs">{x.receipt_no}</td>
                                <td className="px-4 py-2">{x.method}</td>
                                <td className="px-4 py-2">{money(x.amount)}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
            )}

            {tab === 'library' && (
                <Table headers={['Book', 'Issued', 'Due', 'Returned', 'Fine', 'Status']} empty={p.loans.length === 0 ? 'No loans yet.' : null}>
                    {p.loans.map((l) => (
                        <tr key={l.id} className="hover:bg-slate-50">
                            <td className="px-4 py-2">{l.title}</td>
                            <td className="px-4 py-2">{l.issued_on}</td>
                            <td className="px-4 py-2">{l.due_on}</td>
                            <td className="px-4 py-2">{l.returned_on ?? '—'}</td>
                            <td className="px-4 py-2">{l.fine > 0 ? money(l.fine) : '—'}</td>
                            <td className="px-4 py-2">
                                <Badge tone={l.status === 'returned' ? 'green' : l.status === 'overdue' ? 'red' : 'amber'}>
                                    {t(l.status === 'returned' ? 'Returned' : l.status === 'overdue' ? 'Overdue' : 'On loan')}
                                </Badge>
                            </td>
                        </tr>
                    ))}
                </Table>
            )}

            {tab === 'transport' &&
                (p.transport ? (
                    <Card>
                        <dl className="grid gap-4 sm:grid-cols-3">
                            {detail('Route', p.transport.route)}
                            {detail('Stop', p.transport.stop)}
                            {detail('Vehicle', p.transport.vehicle)}
                            {detail('Morning pickup', p.transport.pickup_time)}
                            {detail('Afternoon drop', p.transport.drop_time)}
                            {detail('Monthly fare', money(p.transport.fare))}
                            {detail('Driver', p.transport.driver)}
                            {detail('Driver phone', p.transport.driver_phone)}
                        </dl>
                    </Card>
                ) : (
                    <Card>
                        <p className="text-sm text-slate-400">{t('This student does not use the school bus.')}</p>
                    </Card>
                ))}

            {tab === 'certificates' && (
                <div className="space-y-4">
                    <Table
                        headers={['Number', 'Certificate', 'Issued on', '']}
                        empty={p.certificates.length === 0 ? 'No certificates issued yet.' : null}
                    >
                        {p.certificates.map((c) => (
                            <tr key={c.id} className="hover:bg-slate-50">
                                <td className="px-4 py-2 font-mono text-xs">{c.number}</td>
                                <td className="px-4 py-2">{c.title}</td>
                                <td className="px-4 py-2">{c.issued_on}</td>
                                <td className="px-4 py-2 text-end">
                                    <a href={`/app/certificates/${c.id}/print`} target="_blank" rel="noreferrer" className="text-fuchsia-700">
                                        {t('Print')}
                                    </a>
                                </td>
                            </tr>
                        ))}
                    </Table>
                    {p.documents.length > 0 && (
                        <Table headers={['Document', 'Serial no.', 'Issued on']}>
                            {p.documents.map((d) => (
                                <tr key={d.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-2">{d.type}</td>
                                    <td className="px-4 py-2 font-mono text-xs">{d.serial_no}</td>
                                    <td className="px-4 py-2">{d.issue_date}</td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </div>
            )}

            {tab === 'leave' && (
                <Table headers={['Type', 'From', 'To', 'Days', 'Reason', 'Status']} empty={p.leave.length === 0 ? 'No leave requests.' : null}>
                    {p.leave.map((l) => (
                        <tr key={l.id} className="hover:bg-slate-50">
                            <td className="px-4 py-2">{l.leave_type}</td>
                            <td className="px-4 py-2">{l.start_date}</td>
                            <td className="px-4 py-2">{l.end_date}</td>
                            <td className="px-4 py-2">{l.days}</td>
                            <td className="px-4 py-2">{l.reason}</td>
                            <td className="px-4 py-2">
                                <Badge tone={statusTone(l.status === 'approved' ? 'present' : l.status === 'rejected' ? 'absent' : 'leave')}>
                                    {l.status}
                                </Badge>
                            </td>
                        </tr>
                    ))}
                </Table>
            )}

            {tab === 'documents' && (
                <Card className="space-y-3">
                    <UserFiles userId={s.id} files={p.files} kinds={p.fileKinds} canManage={Boolean(p.can.files)} />
                </Card>
            )}

            {tab === 'notes-conduct' && (
                <div className="space-y-4">
                    {p.can.edit && <NoteForm studentId={s.id} />}
                    {p.notes.length === 0 ? (
                        <Card>
                            <p className="text-sm text-slate-400">{t('No notes about this student.')}</p>
                        </Card>
                    ) : (
                        <ul className="space-y-3">
                            {p.notes.map((n) => (
                                <li key={n.id}>
                                    <Card className={n.kind === 'violation' && n.severity === 'high' ? 'border-red-300' : ''}>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge tone={kindTone[n.kind as keyof typeof kindTone] ?? 'slate'}>
                                                {t(kindLabel[n.kind as keyof typeof kindLabel] ?? n.kind)}
                                            </Badge>
                                            {n.severity && n.kind === 'violation' && (
                                                <Badge tone={n.severity === 'high' ? 'red' : n.severity === 'medium' ? 'amber' : 'slate'}>
                                                    {t(n.severity === 'high' ? 'High' : n.severity === 'medium' ? 'Medium' : 'Low')}
                                                </Badge>
                                            )}
                                            <span className="font-semibold">{n.title}</span>
                                            <span className="text-xs text-slate-400">{n.occurred_on}</span>
                                            {n.visible_to_parent ? <Badge tone="slate">{t('Visible to the parent')}</Badge> : null}
                                            {p.can.delete && (
                                                <button
                                                    onClick={() =>
                                                        confirm(t('Delete this entry?')) &&
                                                        router.delete(`/app/students/${s.id}/notes/${n.id}`, { preserveScroll: true })
                                                    }
                                                    className="ms-auto text-xs text-red-600"
                                                >
                                                    {t('Delete')}
                                                </button>
                                            )}
                                        </div>
                                        {n.details && <p className="mt-2 text-sm whitespace-pre-line text-slate-700">{n.details}</p>}
                                        {n.action_taken && (
                                            <p className="mt-2 text-sm text-slate-600">
                                                <b>{t('Action taken')}:</b> {n.action_taken}
                                            </p>
                                        )}
                                        {n.author && <p className="mt-2 text-xs text-slate-400">{t('Recorded by {name}', { name: n.author })}</p>}
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </OfficePage>
    );
}
