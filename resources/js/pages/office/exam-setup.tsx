import { Badge, Card, Field, ghostBtn, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Exam = { id: number; name: string; starts_on: string; ends_on: string; classes: number; marked: boolean; published: number };
type Row = {
    subject_id: number;
    name: string;
    include: boolean;
    exam_date: string;
    starts_at: string;
    full_marks: number;
    pass_marks: number;
    room: string;
    locked: boolean;
};

function ExamForm({ exam, onDone }: { exam: Exam | null; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm({ name: exam?.name ?? '', starts_on: exam?.starts_on ?? '', ends_on: exam?.ends_on ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: onDone };
        if (exam) form.put(`/app/exams/${exam.id}`, done);
        else form.post('/app/exams', done);
    };

    return (
        <form onSubmit={submit}>
            <Card className="space-y-4 border-fuchsia-200">
                <h2 className="font-semibold">{exam ? t('Edit exam') : t('New exam')}</h2>
                <div className="grid gap-4 md:grid-cols-3">
                    <Field label="Exam name" error={form.errors.name}>
                        <input
                            className={inputCls}
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Half Yearly Examination 2026"
                        />
                    </Field>
                    <Field label="Starts on" error={form.errors.starts_on}>
                        <input
                            type="date"
                            className={inputCls}
                            value={form.data.starts_on}
                            onChange={(e) => form.setData('starts_on', e.target.value)}
                        />
                    </Field>
                    <Field label="Ends on" error={form.errors.ends_on}>
                        <input type="date" className={inputCls} value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} />
                    </Field>
                </div>
                <div className="flex gap-3">
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Save')}
                    </button>
                    <button type="button" onClick={onDone} className={ghostBtn}>
                        {t('Cancel')}
                    </button>
                </div>
            </Card>
        </form>
    );
}


function QuickCreate({ classes, onDone }: { classes: string[]; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm<{ name: string; starts_on: string; ends_on: string; starts_at: string; classes: string[] }>({ name: '', starts_on: '', ends_on: '', starts_at: '10:00', classes: classes });
    const toggle = (c: string) => form.setData('classes', form.data.classes.includes(c) ? form.data.classes.filter((x) => x !== c) : [...form.data.classes, c]);

    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post('/app/exams/quick', { preserveScroll: true, onSuccess: onDone }); }}>
            <Card className="space-y-4 border-fuchsia-200">
                <div>
                    <h2 className="font-semibold">{t('Create exam with timetable')}</h2>
                    <p className="text-sm text-slate-500">{t('Choose the dates and classes. Every subject is placed on its own working day (Friday and Saturday are skipped) and you can change any date afterwards.')}</p>
                </div>
                <div className="grid gap-4 md:grid-cols-4">
                    <Field label="Exam name" error={form.errors.name}><input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Half Yearly Examination 2026" /></Field>
                    <Field label="Starts on" error={form.errors.starts_on}><input type="date" className={inputCls} value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} /></Field>
                    <Field label="Ends on" error={form.errors.ends_on}><input type="date" className={inputCls} value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} /></Field>
                    <Field label="Start time" error={form.errors.starts_at}><input type="time" className={inputCls} value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} /></Field>
                </div>
                <div>
                    <div className="mb-2 flex items-center gap-3 text-sm font-medium">{t('Classes')}
                        <button type="button" className="text-xs text-fuchsia-700" onClick={() => form.setData('classes', classes)}>{t('Select all')}</button>
                        <button type="button" className="text-xs text-slate-500" onClick={() => form.setData('classes', [])}>{t('Clear')}</button>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {classes.map((c) => (
                            <label key={c} className={`cursor-pointer rounded-full border px-3 py-1 text-sm ${form.data.classes.includes(c) ? 'border-fuchsia-600 bg-fuchsia-50 text-fuchsia-800' : 'border-slate-200'}`}>
                                <input type="checkbox" className="sr-only" checked={form.data.classes.includes(c)} onChange={() => toggle(c)} />{t(c)}
                            </label>
                        ))}
                    </div>
                    {(form.errors.classes as string | undefined) && <p className="mt-1 text-xs text-red-600">{form.errors.classes}</p>}
                </div>
                <div className="flex gap-3">
                    <button disabled={form.processing} className={primaryBtn}>{t('Create exam')}</button>
                    <button type="button" onClick={onDone} className={ghostBtn}>{t('Cancel')}</button>
                </div>
            </Card>
        </form>
    );
}

function AdmitRule({ limit, currency }: { limit: number | null; currency: string }) {
    const { t } = useI18n();
    const form = useForm({ admit_due_limit: limit === null ? '' : String(limit) });

    return (
        <Card>
            <form onSubmit={(e) => { e.preventDefault(); form.post('/app/exams/admit-settings', { preserveScroll: true }); }} className="flex flex-wrap items-end gap-3">
                <div className="min-w-[240px] flex-1">
                    <h2 className="font-semibold">{t('Admit card and unpaid fees')}</h2>
                    <p className="text-sm text-slate-500">{t('Students and parents download admit cards from their portal. Hold a card back when unpaid fees are above this amount. Leave empty to never hold cards. You can release one student from the Admit Cards page.')}</p>
                </div>
                <Field label={`${t('Hold when dues exceed')} (${currency})`} error={form.errors.admit_due_limit}>
                    <input type="number" min={0} className={inputCls} value={form.data.admit_due_limit} onChange={(e) => form.setData('admit_due_limit', e.target.value)} placeholder={t('Never hold')} />
                </Field>
                <button disabled={form.processing} className={primaryBtn}>{t('Save')}</button>
            </form>
        </Card>
    );
}

function Schedule({
    examId,
    className,
    initial,
    published,
    rooms,
}: {
    examId: number;
    className: string;
    initial: Row[];
    published: boolean;
    rooms: string[];
}) {
    const { t } = useI18n();
    const form = useForm<{ exam_id: number; class_name: string; rows: Row[] }>({ exam_id: examId, class_name: className, rows: initial });
    const set = (i: number, patch: Partial<Row>) =>
        form.setData(
            'rows',
            form.data.rows.map((r, idx) => (idx === i ? { ...r, ...patch } : r)),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/exams/schedule', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            {published && (
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {t('The result is published. Unpublish it before changing the schedule.')}
                </p>
            )}
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full min-w-[820px] text-sm">
                    <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                        <tr>
                            {['Include', 'Subject', 'Date', 'Time', 'Full marks', 'Pass marks', 'Room'].map((h) => (
                                <th key={h} className="px-3 py-3 text-start font-semibold">
                                    {t(h)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {form.data.rows.map((r, i) => (
                            <tr key={r.subject_id}>
                                <td className="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        checked={r.include}
                                        disabled={r.locked}
                                        title={r.locked ? t('This subject already has marks.') : undefined}
                                        onChange={(e) => set(i, { include: e.target.checked })}
                                    />
                                </td>
                                <td className="px-3 py-2 font-medium">{r.name}</td>
                                <td className="px-3 py-2">
                                    <input
                                        type="date"
                                        disabled={!r.include}
                                        className={inputCls}
                                        value={r.exam_date}
                                        onChange={(e) => set(i, { exam_date: e.target.value })}
                                    />
                                    {form.errors[`rows.${i}.exam_date` as keyof typeof form.errors] && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {t(String(form.errors[`rows.${i}.exam_date` as keyof typeof form.errors]))}
                                        </p>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    <input
                                        type="time"
                                        disabled={!r.include}
                                        className={inputCls}
                                        value={r.starts_at}
                                        onChange={(e) => set(i, { starts_at: e.target.value })}
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <input
                                        type="number"
                                        min={1}
                                        disabled={!r.include}
                                        className={`${inputCls} w-24`}
                                        value={r.full_marks}
                                        onChange={(e) => set(i, { full_marks: Number(e.target.value) })}
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <input
                                        type="number"
                                        min={0}
                                        disabled={!r.include}
                                        className={`${inputCls} w-24`}
                                        value={r.pass_marks}
                                        onChange={(e) => set(i, { pass_marks: Number(e.target.value) })}
                                    />
                                    {form.errors[`rows.${i}.pass_marks` as keyof typeof form.errors] && (
                                        <p className="mt-1 text-xs text-red-600">{t('Pass marks cannot be more than the full marks.')}</p>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    <select
                                        disabled={!r.include}
                                        aria-label={t('Room')}
                                        className={`${inputCls} w-36`}
                                        value={r.room}
                                        onChange={(e) => set(i, { room: e.target.value })}
                                    >
                                        <option value="">{t('No room')}</option>
                                        {rooms.map((room) => (
                                            <option key={room}>{room}</option>
                                        ))}
                                    </select>
                                    {form.errors[`rows.${i}.room` as keyof typeof form.errors] && (
                                        <p className="mt-1 max-w-[9rem] text-xs text-red-600">
                                            {t('This room is already booked for another exam at that time.')}
                                        </p>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <button disabled={form.processing || published} className={primaryBtn}>
                {t('Save schedule')}
            </button>
        </form>
    );
}

export default function ExamSetup({
    navigation,
    roleLabel,
    exams,
    classes,
    selected,
    schedule,
    published,
    rooms = [],
    admitLimit = null,
    currency = 'Tk',
}: {
    navigation: Nav;
    roleLabel: string;
    exams: Exam[];
    classes: string[];
    selected: { exam: number; class: string };
    schedule: Row[];
    published: boolean;
    rooms?: string[];
    admitLimit?: number | null;
    currency?: string;
}) {
    const { t } = useI18n();
    const [editing, setEditing] = useState<Exam | 'new' | 'quick' | null>(exams.length === 0 ? 'quick' : null);
    const go = (next: Record<string, string | number>) => router.get('/app/exams', { ...selected, ...next }, { preserveState: false, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Exam Setup"
            subtitle="Create exams, then set each class's subjects, dates and marks."
            wide
        >
            <div className="flex flex-wrap justify-end gap-2">
                <button onClick={() => setEditing('new')} className={ghostBtn}>
                    {t('New exam')}
                </button>
                <button onClick={() => setEditing('quick')} className={primaryBtn}>
                    {t('Create exam with timetable')}
                </button>
            </div>
            {editing === 'quick' && <QuickCreate classes={classes} onDone={() => setEditing(null)} />}
            {editing && editing !== 'quick' && (
                <ExamForm key={editing === 'new' ? 'new' : editing.id} exam={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />
            )}

            <Table headers={['Exam', 'Starts on', 'Ends on', 'Classes scheduled', 'Status', '']} empty={exams.length === 0 ? 'No exams yet.' : null}>
                {exams.map((e) => (
                    <tr key={e.id} className={`hover:bg-slate-50 ${e.id === selected.exam ? 'bg-fuchsia-50' : ''}`}>
                        <td className="px-4 py-3">
                            <button className="font-medium text-fuchsia-700" onClick={() => go({ exam: e.id })}>
                                {e.name}
                            </button>
                        </td>
                        <td className="px-4 py-3">{e.starts_on}</td>
                        <td className="px-4 py-3">{e.ends_on}</td>
                        <td className="px-4 py-3">{e.classes}</td>
                        <td className="px-4 py-3">
                            {e.published > 0 ? (
                                <Badge tone="green">{t('Published')}</Badge>
                            ) : e.marked ? (
                                <Badge tone="amber">{t('Marking')}</Badge>
                            ) : (
                                <Badge tone="slate">{t('Draft')}</Badge>
                            )}
                        </td>
                        <td className="space-x-2 px-4 py-3 text-end whitespace-nowrap">
                            <button aria-label={t('Edit')} onClick={() => setEditing(e)} className="text-slate-500 hover:text-fuchsia-700">
                                <Pencil className="inline h-4 w-4" />
                            </button>
                            <button
                                aria-label={t('Delete')}
                                onClick={() => confirm(t('Delete this entry?')) && router.delete(`/app/exams/${e.id}`, { preserveScroll: true })}
                                className="text-slate-500 hover:text-red-600"
                            >
                                <Trash2 className="inline h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>

            <AdmitRule limit={admitLimit} currency={currency} />

            {selected.exam > 0 && (
                <section className="space-y-4">
                    <div className="flex flex-wrap items-end gap-4">
                        <h2 className="text-lg font-semibold">{t('Schedule')}</h2>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">{t('Class')}</label>
                            <select className={inputCls} value={selected.class} onChange={(e) => go({ class: e.target.value })}>
                                {classes.map((c) => (
                                    <option key={c} value={c}>
                                        {t(c)}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <Schedule
                        key={`${selected.exam}-${selected.class}`}
                        examId={selected.exam}
                        className={selected.class}
                        initial={schedule}
                        rooms={rooms}
                        published={published}
                    />
                </section>
            )}
        </OfficePage>
    );
}
