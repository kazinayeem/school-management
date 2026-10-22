import { MadeBy } from '@/components/made-by';
import { Avatar } from '@/components/avatar';
import { code128Bars } from '@/lib/barcode';
import { useI18n } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect } from 'react';

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

type Props = {
    mode: 'transcript' | 'file';
    school: { name: string; address: string; phone: string };
    student: Record<string, any>;
    parents: { id: number; name: string; phone: string | null }[];
    attendance: { percent: number | null; present: number; late: number; absent: number; leave: number };
    results: Exam[];
    fees: {
        billed: number;
        paid: number;
        due: number;
        invoices: { id: number; invoice_no: string; label: string; due_on: string; payable: number; paid: number; status: string }[];
    };
    loans: { id: number; title: string; issued_on: string; returned_on: string | null; status: string }[];
    transport: { route: string; stop: string; vehicle: string | null } | null;
    certificates: { id: number; number: string; title: string; issued_on: string }[];
    notes: {
        id: number;
        kind: string;
        title: string;
        occurred_on: string;
        severity: string | null;
        details: string | null;
        action_taken: string | null;
    }[];
};

function Barcode({ value }: { value: string }) {
    const { bars, width } = code128Bars(value);

    return (
        <svg role="img" aria-label={`Barcode ${value}`} viewBox={`0 0 ${width} 30`} preserveAspectRatio="none" className="h-8 w-40">
            <rect width={width} height="30" fill="#fff" />
            {bars.map((b, i) => (
                <rect key={i} x={b.x} y="0" width={b.w} height="30" fill="#111" />
            ))}
        </svg>
    );
}

const th = 'border border-slate-300 bg-slate-100 px-2 py-1 text-start text-xs font-semibold';
const td = 'border border-slate-300 px-2 py-1 text-sm';

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="break-inside-avoid space-y-1.5">
            <h2 className="border-b-2 border-fuchsia-800 pb-0.5 text-sm font-bold tracking-wide text-fuchsia-900 uppercase">{title}</h2>
            {children}
        </section>
    );
}

export default function StudentDocument(p: Props) {
    const { t } = useI18n();
    const logo = (usePage().props as { branding?: { logo?: string } }).branding?.logo ?? '';
    const s = p.student;
    const full = p.mode === 'file';

    useEffect(() => {
        const style = document.createElement('style');
        style.textContent = '@page { size: A4 portrait; margin: 12mm }';
        document.head.appendChild(style);

        return () => style.remove();
    }, []);

    return (
        <div className="min-h-screen bg-slate-200 p-4 print:bg-white print:p-0" style={{ colorScheme: 'light' }}>
            <Head title={`${full ? t('Student file') : t('Academic transcript')} · ${s.name}`} />
            <div className="mx-auto mb-3 flex max-w-[794px] justify-end print:hidden">
                <span className="flex gap-2">
                    <a href={`/students/${s.id}/${full ? 'file' : 'transcript'}.pdf`} className="flex items-center gap-2 rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white">
                        {t('Download PDF')}
                    </a>
                    <button onClick={() => window.print()} className="flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm">
                        <Printer className="h-4 w-4" /> {t('Print')}
                    </button>
                </span>
            </div>

            <article className="mx-auto max-w-[794px] space-y-5 bg-white p-8 text-slate-900 shadow-xl print:max-w-none print:p-0 print:shadow-none">
                <header className="flex items-center gap-4 border-b-4 border-double border-fuchsia-800 pb-3">
                    {logo && <img src={logo} alt="" className="h-14 w-14 object-contain" />}
                    <div className="flex-1">
                        <h1 className="text-xl font-extrabold">{p.school.name}</h1>
                        <p className="text-xs text-slate-500">{[p.school.address, p.school.phone].filter(Boolean).join(' · ')}</p>
                    </div>
                    <div className="text-end">
                        <div className="text-lg font-bold text-fuchsia-900">{full ? t('Student file') : t('Academic transcript')}</div>
                        <div className="text-xs text-slate-500">
                            {new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
                        </div>
                    </div>
                </header>

                <div className="flex gap-4">
                    <div className="h-32 w-28 shrink-0 overflow-hidden rounded border border-slate-300">
                        {s.photo_url ? (
                            <img src={s.photo_url} alt={s.name} className="h-full w-full object-cover" />
                        ) : (
                            <Avatar name={s.name} className="h-full w-full rounded-none text-4xl" />
                        )}
                    </div>
                    <dl className="grid flex-1 grid-cols-2 gap-x-6 gap-y-1 text-sm">
                        {[
                            ['Name', s.name],
                            ['Admission no.', s.admission_no],
                            ['Class', `${t(s.class_name)} · ${s.section}`],
                            ['Roll', s.roll_no],
                            ['Session', s.session],
                            ['Date of birth', s.date_of_birth],
                            ['Gender', s.gender],
                            ['Religion', s.religion],
                            ['Guardian', s.guardian_name],
                            ['Guardian phone', s.guardian_mobile],
                        ].map(([label, value]) => (
                            <div key={label as string} className="flex gap-2">
                                <dt className="w-28 shrink-0 text-slate-500">{t(label as string)}</dt>
                                <dd className="font-medium">{(value as string) || '—'}</dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <Section title={t('Academic results')}>
                    {p.results.length === 0 ? (
                        <p className="text-sm text-slate-500">{t('No published results yet.')}</p>
                    ) : (
                        p.results.map((r) => (
                            <div key={r.exam} className="mb-3 break-inside-avoid">
                                <div className="mb-1 flex items-center justify-between text-sm font-semibold">
                                    <span>{r.exam}</span>
                                    <span>
                                        {r.gpa !== null && `GPA ${r.gpa.toFixed(2)} (${r.grade})`}
                                        {r.class_position ? ` · ${t('Position {n}', { n: r.class_position })}` : ''}
                                    </span>
                                </div>
                                <table className="w-full border-collapse">
                                    <thead>
                                        <tr>
                                            {['Subject', 'Marks', 'Grade', 'Point'].map((h) => (
                                                <th key={h} className={th}>
                                                    {t(h)}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {r.subjects.map((x) => (
                                            <tr key={x.subject}>
                                                <td className={td}>{x.subject}</td>
                                                <td className={`${td} ${x.failed ? 'text-red-600' : ''}`}>
                                                    {x.absent ? t('Absent') : `${x.marks}/${x.full_marks}`}
                                                </td>
                                                <td className={td}>{x.grade}</td>
                                                <td className={td}>{x.point === null ? '—' : x.point.toFixed(2)}</td>
                                            </tr>
                                        ))}
                                        <tr className="font-semibold">
                                            <td className={td}>{t('Total')}</td>
                                            <td className={td}>
                                                {r.total}/{r.full_total}
                                            </td>
                                            <td className={td} colSpan={2}>
                                                {r.failed ? t('Failed') : t('Passed')}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        ))
                    )}
                </Section>

                {full && (
                    <>
                        <Section title={t('Attendance')}>
                            <p className="text-sm">
                                {t('Present')} {p.attendance.present} · {t('Late')} {p.attendance.late} · {t('Absent')} {p.attendance.absent} ·{' '}
                                {t('Leave')} {p.attendance.leave} · <b>{p.attendance.percent === null ? '—' : `${p.attendance.percent}%`}</b>
                            </p>
                        </Section>
                        <Section title={t('Fees')}>
                            <p className="text-sm">
                                {t('Billed')} {p.fees.billed.toLocaleString()} · {t('Paid')} {p.fees.paid.toLocaleString()} ·{' '}
                                <b>
                                    {t('Due')} {p.fees.due.toLocaleString()}
                                </b>
                            </p>
                        </Section>
                        {p.transport && (
                            <Section title={t('Transport')}>
                                <p className="text-sm">
                                    {p.transport.route} · {p.transport.stop} {p.transport.vehicle ? `· ${p.transport.vehicle}` : ''}
                                </p>
                            </Section>
                        )}
                        <Section title={t('Certificates')}>
                            {p.certificates.length === 0 ? (
                                <p className="text-sm text-slate-500">{t('No certificates issued yet.')}</p>
                            ) : (
                                <ul className="text-sm">
                                    {p.certificates.map((c) => (
                                        <li key={c.id}>
                                            {c.title} · {c.number} · {c.issued_on}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Section>
                        <Section title={t('Notes & conduct')}>
                            {p.notes.length === 0 ? (
                                <p className="text-sm text-slate-500">{t('No notes about this student.')}</p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {p.notes.map((n) => (
                                        <li key={n.id} className="break-inside-avoid">
                                            <b>{n.title}</b>{' '}
                                            <span className="text-xs text-slate-500">
                                                · {n.kind}
                                                {n.severity ? ` (${n.severity})` : ''} · {n.occurred_on}
                                            </span>
                                            {n.details && <div className="text-slate-600">{n.details}</div>}
                                            {n.action_taken && (
                                                <div className="text-slate-600">
                                                    {t('Action taken')}: {n.action_taken}
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Section>
                    </>
                )}

                <footer className="flex items-end justify-between pt-8 text-sm">
                    <Barcode value={s.admission_no} />
                    <div className="text-center">
                        <div className="mb-1 h-px w-48 bg-slate-500" />
                        {t('Principal')}
                    </div>
                </footer>
            </article>
            <MadeBy className="mt-3" />
        </div>
    );
}
