import { Badge, Card, inputCls, Nav, OfficePage } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';

type Cell = { marks: number; absent: boolean; grade: string; failed: boolean };
type Row = {
    id: number;
    name: string;
    roll_no: string;
    section: string;
    cells: Record<string, Cell>;
    total: number;
    complete: boolean;
    gpa: number | null;
    grade: string | null;
    failed: boolean | null;
    class_position: number | null;
    section_position: number | null;
};
type Stats = { students: number; complete: number; passed: number; failed: number; pass_rate: number | null; average_gpa: number | null };

export default function ExamResults({
    navigation,
    roleLabel,
    exams,
    classes,
    filters,
    subjects,
    rows,
    stats,
    published,
    canPublish,
}: {
    navigation: Nav;
    roleLabel: string;
    exams: { id: number; name: string }[];
    classes: string[];
    filters: { exam: number; class: string; section: string };
    subjects: { id: number; name: string; full_marks: number }[];
    rows: Row[];
    stats: Stats | null;
    published: boolean;
    canPublish: boolean;
}) {
    const { t } = useI18n();
    const go = (next: Record<string, string | number>) =>
        router.get('/app/exams/results', { ...filters, ...next }, { preserveState: true, replace: true });
    const body = { exam_id: filters.exam, class_name: filters.class };

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Results" subtitle="Marks, grades, GPA and merit positions for an exam." wide>
            <Card className="no-print flex flex-wrap items-end gap-4">
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Exam')}</label>
                    <select className={inputCls} value={filters.exam} onChange={(e) => go({ exam: e.target.value })}>
                        {exams.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Class')}</label>
                    <select className={inputCls} value={filters.class} onChange={(e) => go({ class: e.target.value })}>
                        {classes.map((c) => (
                            <option key={c} value={c}>
                                {t(c)}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Section')}</label>
                    <select className={inputCls} value={filters.section} onChange={(e) => go({ section: e.target.value })}>
                        <option value="">{t('All sections')}</option>
                        <option>A</option>
                        <option>B</option>
                    </select>
                </div>
                <div className="ms-auto flex items-center gap-3">
                    <Badge tone={published ? 'green' : 'amber'}>{published ? t('Published') : t('Not published')}</Badge>
                    <button onClick={() => window.print()} className="flex items-center gap-1 rounded-lg border px-4 py-2 text-sm hover:bg-slate-50">
                        <Printer className="h-4 w-4" /> {t('Print')}
                    </button>
                    {canPublish &&
                        (published ? (
                            <button
                                onClick={() => router.post('/app/exams/unpublish', body, { preserveScroll: true })}
                                className="rounded-lg border px-4 py-2 text-sm hover:bg-slate-50"
                            >
                                {t('Unpublish results')}
                            </button>
                        ) : (
                            <button
                                disabled={subjects.length === 0}
                                onClick={() =>
                                    confirm(t('Publish these results? Students and parents will see them.')) &&
                                    router.post('/app/exams/publish', body, { preserveScroll: true })
                                }
                                className="rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-50"
                            >
                                {t('Publish results')}
                            </button>
                        ))}
                </div>
            </Card>

            {stats && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {[
                        ['Students', stats.students],
                        ['Marked in full', stats.complete],
                        ['Passed', stats.passed],
                        ['Failed', stats.failed],
                        ['Pass rate', stats.pass_rate === null ? '—' : `${stats.pass_rate}%`],
                    ].map(([label, value]) => (
                        <Card key={String(label)}>
                            <div className="text-xs text-slate-500">{t(String(label))}</div>
                            <div className="mt-1 text-xl font-bold">{value}</div>
                        </Card>
                    ))}
                </div>
            )}

            {subjects.length === 0 ? (
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {t('No schedule has been set for this class yet. Add one in Exam Setup.')}
                </p>
            ) : rows.length === 0 ? (
                <p className="text-sm text-slate-500">{t('No students found.')}</p>
            ) : (
                <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full min-w-[820px] text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="px-3 py-3 text-start">{t('Position')}</th>
                                <th className="px-3 py-3 text-start">{t('Roll')}</th>
                                <th className="px-3 py-3 text-start">{t('Student')}</th>
                                <th className="px-3 py-3 text-start">{t('Section')}</th>
                                {subjects.map((s) => (
                                    <th key={s.id} className="px-3 py-3 text-start">
                                        {s.name}
                                        <div className="font-normal normal-case">/{s.full_marks}</div>
                                    </th>
                                ))}
                                <th className="px-3 py-3 text-end">{t('Total')}</th>
                                <th className="px-3 py-3 text-end">{t('GPA')}</th>
                                <th className="px-3 py-3 text-start">{t('Grade')}</th>
                                <th className="px-3 py-3 text-start">{t('Result')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((r) => (
                                <tr key={r.id} className="hover:bg-slate-50">
                                    <td className="px-3 py-2 font-semibold">{r.class_position ?? '—'}</td>
                                    <td className="px-3 py-2">{r.roll_no}</td>
                                    <td className="px-3 py-2">{r.name}</td>
                                    <td className="px-3 py-2">{r.section}</td>
                                    {subjects.map((s) => {
                                        const c = r.cells[String(s.id)];

                                        return (
                                            <td key={s.id} className={`px-3 py-2 ${c?.failed ? 'text-red-600' : ''}`}>
                                                {c ? (c.absent ? t('Absent') : `${c.marks} (${c.grade})`) : '—'}
                                            </td>
                                        );
                                    })}
                                    <td className="px-3 py-2 text-end tabular-nums">{r.total}</td>
                                    <td className="px-3 py-2 text-end tabular-nums">{r.gpa === null ? '—' : r.gpa.toFixed(2)}</td>
                                    <td className="px-3 py-2">{r.grade ?? '—'}</td>
                                    <td className="px-3 py-2">
                                        {r.complete ? (
                                            <Badge tone={r.failed ? 'red' : 'green'}>{r.failed ? t('Failed') : t('Passed')}</Badge>
                                        ) : (
                                            <Badge tone="slate">{t('Incomplete')}</Badge>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {stats?.average_gpa !== null && stats?.average_gpa !== undefined && (
                <p className="text-sm text-slate-600">{t('Average GPA: {gpa}', { gpa: stats.average_gpa.toFixed(2) })}</p>
            )}
        </OfficePage>
    );
}
