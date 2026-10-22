import { Card, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Cell = { subject?: string | null; teacher?: string | null; room?: string | null };
type Period = { id: number; label: string; time_range: string; is_break: boolean; sort: number };

type Props = {
    navigation: Nav;
    roleLabel: string;
    readOnly: boolean;
    teacherMode?: boolean;
    classes?: string[];
    teachers: string[];
    subjects?: string[];
    filters: { class?: string; section?: string; teacher?: string };
    days: string[];
    periods: Period[];
    cells: Record<string, Cell>;
    rooms?: string[];
    taken?: Record<string, { room: string; by: string }[]>;
};

function Grid({ props }: { props: Props }) {
    const { t } = useI18n();
    const { readOnly, teacherMode, periods, days, subjects = [], teachers, filters, rooms = [], taken = {} } = props;
    const [cells, setCells] = useState<Record<string, Cell>>(props.cells);
    const form = useForm<Record<string, any>>({
        class_name: filters.class ?? '',
        section: filters.section ?? 'A',
        cells: [],
    });

    const set = (key: string, patch: Cell) => setCells((c) => ({ ...c, [key]: { ...c[key], ...patch } }));

    const save = () => {
        form.transform(() => ({
            class_name: filters.class ?? '',
            section: filters.section ?? 'A',
            cells: Object.entries(cells)
                .filter(([, c]) => c.subject || c.teacher || c.room)
                .map(([key, c]) => ({
                    day: Number(key.split('-')[0]),
                    sort: Number(key.split('-')[1]),
                    subject: c.subject || null,
                    teacher: c.teacher || null,
                    room: c.room || null,
                })),
        }));
        form.post('/app/timetable', { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full min-w-[900px] text-sm">
                    <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th className="px-3 py-3 text-start">{t('Period')}</th>
                            {days.map((d) => (
                                <th key={d} className="px-3 py-3 text-start">
                                    {t(d)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {periods.map((p) => (
                            <tr key={p.id} className={p.is_break ? 'bg-slate-50' : ''}>
                                <td className="px-3 py-2 align-top whitespace-nowrap">
                                    <div className="font-medium">{p.label}</div>
                                    <div className="text-xs text-slate-400" dir="ltr">
                                        {p.time_range}
                                    </div>
                                </td>
                                {days.map((_, d) => {
                                    const key = `${d}-${p.sort}`;
                                    const c = cells[key] ?? {};
                                    if (p.is_break) {
                                        return (
                                            <td key={key} className="px-3 py-2 text-center text-xs text-slate-400">
                                                {t('Break')}
                                            </td>
                                        );
                                    }
                                    if (readOnly) {
                                        return (
                                            <td key={key} className="px-3 py-2 align-top">
                                                <div className="font-medium">{c.subject}</div>
                                                <div className="text-xs text-slate-500">{c.teacher}</div>
                                                {c.room && <div className="text-xs text-slate-400">{c.room}</div>}
                                            </td>
                                        );
                                    }
                                    return (
                                        <td key={key} className="space-y-1 px-2 py-2 align-top">
                                            <select
                                                aria-label={t('Subject')}
                                                className={inputCls}
                                                value={c.subject ?? ''}
                                                onChange={(e) => set(key, { subject: e.target.value })}
                                            >
                                                <option value="">—</option>
                                                {subjects.map((s) => (
                                                    <option key={s}>{s}</option>
                                                ))}
                                            </select>
                                            <select
                                                aria-label={t('Teacher')}
                                                className={inputCls}
                                                value={c.teacher ?? ''}
                                                onChange={(e) => set(key, { teacher: e.target.value })}
                                            >
                                                <option value="">—</option>
                                                {teachers.map((s) => (
                                                    <option key={s}>{s}</option>
                                                ))}
                                            </select>
                                            <select
                                                aria-label={t('Room')}
                                                className={inputCls}
                                                value={c.room ?? ''}
                                                onChange={(e) => set(key, { room: e.target.value })}
                                            >
                                                <option value="">{t('No room')}</option>
                                                {rooms.map((room) => {
                                                    const holder = (taken[key] ?? []).find((x) => x.room === room);

                                                    return (
                                                        <option key={room} value={room} disabled={Boolean(holder)}>
                                                            {holder
                                                                ? `${room} — ${t('taken by {by}', { by: t(holder.by.replace(/ ([AB])$/, ' $1')) })}`
                                                                : room}
                                                        </option>
                                                    );
                                                })}
                                            </select>
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {!readOnly && (
                <div className="space-y-2">
                    {form.errors.cells && <p className="text-sm text-red-600">{form.errors.cells}</p>}
                    <button onClick={save} disabled={form.processing} className={primaryBtn}>
                        {t('Save routine')}
                    </button>
                </div>
            )}
            {teacherMode && <p className="text-xs text-slate-500">{t('Each cell shows the subject and the class being taught.')}</p>}
        </div>
    );
}

export default function Routine(props: Props) {
    const { t } = useI18n();
    const { filters, classes = [], teachers, teacherMode, navigation, roleLabel } = props;
    const go = (next: Record<string, string>) =>
        router.get(window.location.pathname, { ...filters, ...next }, { preserveState: false, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title={teacherMode ? 'Teacher Routine' : 'Class Routine'}
            subtitle={
                teacherMode
                    ? 'A teacher’s week at a glance.'
                    : props.readOnly
                      ? 'The weekly routine for a class and section.'
                      : 'Plan each period of the week. A teacher cannot be in two classes at once.'
            }
            wide
        >
            <Card className="flex flex-wrap items-end gap-4">
                {teacherMode ? (
                    props.roleLabel !== 'Teacher' ? (
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">{t('Teacher')}</label>
                            <select className={inputCls} value={filters.teacher} onChange={(e) => go({ teacher: e.target.value })}>
                                {teachers.map((n) => (
                                    <option key={n}>{n}</option>
                                ))}
                            </select>
                        </div>
                    ) : (
                        <p className="font-medium">{filters.teacher}</p>
                    )
                ) : (
                    <>
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
                                <option>A</option>
                                <option>B</option>
                            </select>
                        </div>
                    </>
                )}
            </Card>
            <Grid key={`${filters.class}-${filters.section}-${filters.teacher}`} props={props} />
        </OfficePage>
    );
}
