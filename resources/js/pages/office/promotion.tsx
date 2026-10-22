import { Avatar } from '@/components/avatar';
import { FlashStatus } from '@/components/flash';
import { Badge, Card, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { ArrowRight, Filter, Search, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Student = {
    id: number;
    name: string;
    photo: string | null;
    section: string;
    roll_no: string;
    admission_no: string;
    gender: string | null;
    gpa: number | null;
    exam: string | null;
    dues: number;
    attendance: number | null;
};
type Filters = { class: string; section: string; q: string; gender: string; result: string; dues: string; attendance: string };

const money = (n: number) => n.toLocaleString();

function Board({ filters, students, classes, minimum }: { filters: Filters; students: Student[]; classes: string[]; minimum: number }) {
    const { t } = useI18n();
    const nextClass = classes[classes.indexOf(filters.class) + 1] ?? 'Passed out';
    const form = useForm<{ from_class: string; to_class: string; student_ids: number[] }>({
        from_class: filters.class,
        to_class: nextClass,
        student_ids: [],
    });
    const chosen = new Set(form.data.student_ids);
    const passed = students.filter((s) => s.gpa !== null && s.gpa > 0);
    const allShown = students.length > 0 && students.every((s) => chosen.has(s.id));
    const set = (ids: number[]) => form.setData('student_ids', ids);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const to = t(form.data.to_class);
        if (!confirm(t('Move {n} students from {from} to {to}? They get new roll numbers there.', { n: chosen.size, from: t(filters.class), to })))
            return;
        form.post('/app/students/promotion', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <button
                    type="button"
                    onClick={() => set(allShown ? [] : students.map((s) => s.id))}
                    className="rounded-lg border px-3 py-1.5 hover:bg-slate-50"
                >
                    {allShown ? t('Clear selection') : t('Select all shown ({n})', { n: students.length })}
                </button>
                <button
                    type="button"
                    disabled={passed.length === 0}
                    onClick={() => set(passed.map((s) => s.id))}
                    className="rounded-lg border px-3 py-1.5 hover:bg-slate-50 disabled:opacity-50"
                >
                    {t('Select only those who passed ({n})', { n: passed.length })}
                </button>
                <span className="ms-auto text-slate-500">{t('{n} selected', { n: chosen.size })}</span>
            </div>

            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                {students.length === 0 ? (
                    <p className="px-6 py-14 text-center text-sm text-slate-400">{t('No active students match these filters.')}</p>
                ) : (
                    <table className="w-full min-w-[820px] text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        aria-label={t('Select all')}
                                        checked={allShown}
                                        onChange={() => set(allShown ? [] : students.map((s) => s.id))}
                                    />
                                </th>
                                {['Student', 'Section', 'Roll', 'Result', 'Attendance', 'Fee dues'].map((h) => (
                                    <th key={h} className="px-4 py-3 text-start font-semibold">
                                        {t(h)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {students.map((s) => (
                                <tr key={s.id} className={`hover:bg-slate-50 ${chosen.has(s.id) ? 'bg-fuchsia-50/60' : ''}`}>
                                    <td className="px-4 py-2.5">
                                        <input
                                            type="checkbox"
                                            aria-label={s.name}
                                            checked={chosen.has(s.id)}
                                            onChange={() =>
                                                set(
                                                    chosen.has(s.id)
                                                        ? form.data.student_ids.filter((x) => x !== s.id)
                                                        : [...form.data.student_ids, s.id],
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <div className="flex items-center gap-3">
                                            <Avatar name={s.name} src={s.photo} className="h-9 w-9" />
                                            <div>
                                                <div className="font-medium">{s.name}</div>
                                                <div className="text-xs text-slate-400">{s.admission_no}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-2.5">{s.section}</td>
                                    <td className="px-4 py-2.5">{s.roll_no}</td>
                                    <td className="px-4 py-2.5">
                                        {s.gpa === null ? (
                                            <Badge tone="slate">{t('No result')}</Badge>
                                        ) : s.gpa > 0 ? (
                                            <Badge tone="green">
                                                {t('Passed')} · {s.gpa.toFixed(2)}
                                            </Badge>
                                        ) : (
                                            <Badge tone="red">{t('Failed')}</Badge>
                                        )}
                                    </td>
                                    <td
                                        className={`px-4 py-2.5 ${s.attendance !== null && s.attendance < minimum ? 'font-medium text-red-600' : ''}`}
                                    >
                                        {s.attendance === null ? '—' : `${s.attendance}%`}
                                    </td>
                                    <td className={`px-4 py-2.5 ${s.dues > 0 ? 'font-medium text-amber-600' : 'text-slate-400'}`}>
                                        {s.dues > 0 ? money(s.dues) : t('Clear')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(form.errors.student_ids || form.errors.to_class) && (
                <p className="text-sm text-red-600">{t(form.errors.student_ids ?? form.errors.to_class ?? '')}</p>
            )}

            <div className="sticky bottom-3 z-10 flex flex-wrap items-center gap-3 rounded-2xl border border-fuchsia-200 bg-white p-3 shadow-lg">
                <span className="flex items-center gap-2 text-sm font-medium">
                    {t(filters.class)} <ArrowRight className="h-4 w-4 text-slate-400 rtl:rotate-180" />
                </span>
                <select
                    aria-label={t('Promote to')}
                    value={form.data.to_class}
                    onChange={(e) => form.setData('to_class', e.target.value)}
                    className={`${inputCls} w-48`}
                >
                    {[...classes.filter((c) => c !== filters.class), 'Passed out'].map((c) => (
                        <option key={c} value={c}>
                            {t(c)}
                        </option>
                    ))}
                </select>
                <button disabled={form.processing || chosen.size === 0} className={`${primaryBtn} ms-auto`}>
                    {t('Promote {n} students', { n: chosen.size })}
                </button>
            </div>
        </form>
    );
}

export default function Promotion({
    navigation,
    roleLabel,
    classes,
    filters,
    students,
    total,
    minimum,
    result,
}: {
    navigation: Nav;
    roleLabel: string;
    classes: string[];
    filters: Filters;
    students: Student[];
    total: number;
    minimum: number;
    result?: { moved: number; to: string } | null;
}) {
    const { t } = useI18n();
    const [q, setQ] = useState(filters.q);
    const [open, setOpen] = useState(Boolean(filters.gender || filters.result || filters.dues || filters.attendance));
    const go = (next: Partial<Filters>) => router.get('/app/students/promotion', { ...filters, ...next }, { preserveState: true, replace: true });

    const labels: Record<string, Record<string, string>> = {
        gender: { Male: 'Boys', Female: 'Girls' },
        result: { passed: 'Passed', failed: 'Failed', none: 'No result yet' },
        dues: { clear: 'No fee dues', owing: 'Has fee dues' },
        attendance: { low: 'Attendance below minimum', ok: 'Attendance at or above minimum' },
    };
    const active = (['gender', 'result', 'dues', 'attendance'] as const).filter((k) => filters[k]);

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Promotion"
            subtitle="Move students up to the next class at the end of the year. Filter, select, then promote."
            wide
        >
            <FlashStatus />
            {result && (
                <div role="status" className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {t('{moved} students moved to {to}.', { moved: result.moved, to: t(result.to) })}
                </div>
            )}

            <Card className="space-y-4">
                <div className="grid gap-3 md:grid-cols-[180px_140px_1fr_auto]">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-500">{t('From class')}</label>
                        <select value={filters.class} onChange={(e) => go({ class: e.target.value })} className={inputCls}>
                            {classes.map((c) => (
                                <option key={c} value={c}>
                                    {t(c)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-500">{t('Section')}</label>
                        <select value={filters.section} onChange={(e) => go({ section: e.target.value })} className={inputCls}>
                            <option value="">{t('All sections')}</option>
                            <option>A</option>
                            <option>B</option>
                        </select>
                    </div>
                    <form
                        className="relative self-end"
                        onSubmit={(e) => {
                            e.preventDefault();
                            go({ q });
                        }}
                    >
                        <Search className="absolute start-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder={t('Search name, admission no. or roll')}
                            className={`${inputCls} ps-9`}
                            aria-label={t('Search students')}
                        />
                    </form>
                    <button
                        type="button"
                        onClick={() => setOpen(!open)}
                        className="flex items-center gap-2 self-end rounded-lg border px-4 py-2 text-sm hover:bg-slate-50"
                    >
                        <Filter className="h-4 w-4" /> {t('Advanced filters')}
                        {active.length > 0 && <span className="rounded-full bg-fuchsia-700 px-1.5 text-xs text-white">{active.length}</span>}
                    </button>
                </div>

                {open && (
                    <div className="grid gap-3 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4">
                        {(
                            [
                                [
                                    'gender',
                                    'Gender',
                                    [
                                        ['', 'Everyone'],
                                        ['Male', 'Boys'],
                                        ['Female', 'Girls'],
                                    ],
                                ],
                                [
                                    'result',
                                    'Exam result',
                                    [
                                        ['', 'Any result'],
                                        ['passed', 'Passed'],
                                        ['failed', 'Failed'],
                                        ['none', 'No result yet'],
                                    ],
                                ],
                                [
                                    'attendance',
                                    'Attendance',
                                    [
                                        ['', 'Any attendance'],
                                        ['low', 'Below the minimum'],
                                        ['ok', 'At or above the minimum'],
                                    ],
                                ],
                                [
                                    'dues',
                                    'Fee dues',
                                    [
                                        ['', 'Any'],
                                        ['clear', 'No dues'],
                                        ['owing', 'Has dues'],
                                    ],
                                ],
                            ] as const
                        ).map(([key, label, options]) => (
                            <div key={key}>
                                <label className="mb-1 block text-xs font-medium text-slate-500">{t(label)}</label>
                                <select value={filters[key]} onChange={(e) => go({ [key]: e.target.value })} className={inputCls}>
                                    {options.map(([value, text]) => (
                                        <option key={value} value={value}>
                                            {t(text)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ))}
                        <p className="text-xs text-slate-400 sm:col-span-2 lg:col-span-4">
                            {t('The attendance minimum is {n}%. Change it under Settings.', { n: minimum })}
                        </p>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <span>
                        {t('Showing {shown} of {total} active students in {class}.', { shown: students.length, total, class: t(filters.class) })}
                    </span>
                    {active.map((k) => (
                        <button
                            key={k}
                            type="button"
                            onClick={() => go({ [k]: '' })}
                            className="flex items-center gap-1 rounded-full bg-fuchsia-50 px-2.5 py-1 text-xs text-fuchsia-800 hover:bg-fuchsia-100"
                        >
                            {t(labels[k][filters[k]] ?? filters[k])} <X className="h-3 w-3" />
                        </button>
                    ))}
                    {(active.length > 0 || filters.q || filters.section) && (
                        <button
                            type="button"
                            onClick={() => {
                                setQ('');
                                router.get('/app/students/promotion', { class: filters.class }, { preserveState: true, replace: true });
                            }}
                            className="text-fuchsia-700 underline"
                        >
                            {t('Clear all filters')}
                        </button>
                    )}
                </div>
            </Card>

            <Board
                key={JSON.stringify(filters) + students.map((s) => s.id).join(',')}
                filters={filters}
                students={students}
                classes={classes}
                minimum={minimum}
            />
        </OfficePage>
    );
}
