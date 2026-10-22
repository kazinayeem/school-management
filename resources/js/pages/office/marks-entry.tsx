import { Badge, Card, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { FormEvent } from 'react';

type Student = { id: number; name: string; roll_no: string; section: string; marks: number | null; absent: boolean };
type Band = { grade: string; min_marks: number; max_marks: number; point: number };

const gradeOf = (marks: number | null, absent: boolean, full: number, pass: number, scale: Band[]) => {
    if (absent) return { grade: 'F', failed: true };
    if (marks === null || Number.isNaN(marks)) return null;
    if (marks < pass) return { grade: 'F', failed: true };
    const percent = Math.round((marks / full) * 100);
    const band = scale.find((b) => percent >= b.min_marks && percent <= b.max_marks);

    return band && band.point > 0 ? { grade: band.grade, failed: false } : { grade: 'F', failed: true };
};

function Sheet({
    filters,
    current,
    students,
    scale,
    locked,
}: {
    filters: Props['filters'];
    current: NonNullable<Props['current']>;
    students: Student[];
    scale: Band[];
    locked: boolean;
}) {
    const { t } = useI18n();
    const form = useForm<{
        exam_id: number;
        class_name: string;
        section: string;
        subject_id: number;
        entries: { user_id: number; marks: string; absent: boolean }[];
    }>({
        exam_id: filters.exam,
        class_name: filters.class,
        section: filters.section,
        subject_id: filters.subject,
        entries: students.map((s) => ({ user_id: s.id, marks: s.marks === null ? '' : String(s.marks), absent: s.absent })),
    });
    const set = (i: number, patch: Partial<(typeof form.data.entries)[number]>) =>
        form.setData(
            'entries',
            form.data.entries.map((e, idx) => (idx === i ? { ...e, ...patch } : e)),
        );
    const hasError = Object.keys(form.errors).some((k) => k.startsWith('entries.'));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            entries: d.entries.map((x) => ({ user_id: x.user_id, absent: x.absent, marks: x.marks === '' ? null : Number(x.marks) })),
        }));
        form.post('/app/exams/marks', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            {locked && (
                <p className="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    <Lock className="h-4 w-4" /> {t('The result is published. Marks cannot be changed.')}
                </p>
            )}
            <p className="text-sm text-slate-600">
                {t('Full marks {full}, pass marks {pass}. Leave a mark empty if the student has not been marked yet.', {
                    full: current.full_marks,
                    pass: current.pass_marks,
                })}
            </p>
            <Table headers={['Roll', 'Student', 'Section', 'Marks', 'Absent', 'Grade']} empty={students.length === 0 ? 'No students found.' : null}>
                {students.map((s, i) => {
                    const e = form.data.entries[i];
                    const g = gradeOf(e.marks === '' ? null : Number(e.marks), e.absent, current.full_marks, current.pass_marks, scale);
                    const err = form.errors[`entries.${i}.marks` as keyof typeof form.errors];

                    return (
                        <tr key={s.id} className="hover:bg-slate-50">
                            <td className="px-4 py-2">{s.roll_no}</td>
                            <td className="px-4 py-2">{s.name}</td>
                            <td className="px-4 py-2">{s.section}</td>
                            <td className="px-4 py-2">
                                <input
                                    type="number"
                                    min={0}
                                    max={current.full_marks}
                                    aria-label={`${t('Marks')} ${s.name}`}
                                    disabled={locked || e.absent}
                                    className={`${inputCls} w-28 ${err ? 'border-red-500' : ''}`}
                                    value={e.absent ? '' : e.marks}
                                    onChange={(ev) => set(i, { marks: ev.target.value })}
                                />
                            </td>
                            <td className="px-4 py-2">
                                <input
                                    type="checkbox"
                                    aria-label={`${t('Absent')} ${s.name}`}
                                    disabled={locked}
                                    checked={e.absent}
                                    onChange={(ev) => set(i, { absent: ev.target.checked })}
                                />
                            </td>
                            <td className="px-4 py-2">{g && <Badge tone={g.failed ? 'red' : 'green'}>{g.grade}</Badge>}</td>
                        </tr>
                    );
                })}
            </Table>
            {hasError && <p className="text-sm text-red-600">{t('Marks cannot be more than the full marks.')}</p>}
            {!locked && students.length > 0 && (
                <button disabled={form.processing} className={primaryBtn}>
                    {t('Save marks')}
                </button>
            )}
        </form>
    );
}

type Props = {
    navigation: Nav;
    roleLabel: string;
    exams: { id: number; name: string }[];
    classes: string[];
    filters: { exam: number; class: string; section: string; subject: number };
    subjects: { id: number; name: string }[];
    current: { full_marks: number; pass_marks: number } | null;
    students: Student[];
    locked: boolean;
    scale: Band[];
    canUnlock: boolean;
};

export default function MarksEntry({ navigation, roleLabel, exams, classes, filters, subjects, current, students, locked, scale, canUnlock }: Props) {
    const { t } = useI18n();
    const go = (next: Record<string, string | number>) =>
        router.get('/app/exams/marks', { ...filters, ...next }, { preserveState: false, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Marks Entry"
            subtitle="Enter each student's marks for one subject. Grades come from the grading scale."
            wide
        >
            <Card className="flex flex-wrap items-end gap-4">
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Exam')}</label>
                    <select className={inputCls} value={filters.exam} onChange={(e) => go({ exam: e.target.value, subject: '' })}>
                        {exams.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Class')}</label>
                    <select className={inputCls} value={filters.class} onChange={(e) => go({ class: e.target.value, subject: '' })}>
                        {classes.map((c) => (
                            <option key={c} value={c}>
                                {t(c)}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Section')}</label>
                    <select className={inputCls} value={filters.section} onChange={(e) => go({ section: e.target.value, subject: '' })}>
                        <option value="">{t('All sections')}</option>
                        <option>A</option>
                        <option>B</option>
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Subject')}</label>
                    <select
                        className={inputCls}
                        value={filters.subject}
                        onChange={(e) => go({ subject: e.target.value })}
                        disabled={subjects.length === 0}
                    >
                        {subjects.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                </div>
                {locked && canUnlock && (
                    <button
                        onClick={() =>
                            router.post('/app/exams/unpublish', { exam_id: filters.exam, class_name: filters.class }, { preserveScroll: true })
                        }
                        className="ms-auto rounded-lg border px-4 py-2 text-sm hover:bg-slate-50"
                    >
                        {t('Unpublish results')}
                    </button>
                )}
            </Card>

            {!current ? (
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {exams.length === 0
                        ? t('Create an exam first.')
                        : t('No subjects are available to you for this exam and class. Check the exam schedule and your teaching assignments.')}
                </p>
            ) : (
                <Sheet
                    key={`${filters.exam}-${filters.class}-${filters.section}-${filters.subject}-${locked}`}
                    filters={filters}
                    current={current}
                    students={students}
                    scale={scale}
                    locked={locked}
                />
            )}
        </OfficePage>
    );
}
