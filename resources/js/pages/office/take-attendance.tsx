import { Avatar } from '@/components/avatar';
import { Card, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Student = { id: number; name: string; roll_no: string; photo: string | null; status: string; marked: boolean };
type Filters = { date: string; class: string; section: string; subject: string };

const statuses = [
    ['present', 'Present', 'bg-green-600 text-white', 'hover:bg-green-50'],
    ['late', 'Late', 'bg-amber-500 text-white', 'hover:bg-amber-50'],
    ['absent', 'Absent', 'bg-red-600 text-white', 'hover:bg-red-50'],
    ['leave', 'Leave', 'bg-sky-600 text-white', 'hover:bg-sky-50'],
] as const;

function Sheet({ filters, students }: { filters: Filters; students: Student[] }) {
    const { t } = useI18n();
    const form = useForm<{ date: string; class_name: string; section: string; subject: string; marks: { user_id: number; status: string }[] }>({
        date: filters.date,
        class_name: filters.class,
        section: filters.section,
        subject: filters.subject,
        marks: students.map((s) => ({ user_id: s.id, status: s.status })),
    });
    const set = (i: number, status: string) =>
        form.setData(
            'marks',
            form.data.marks.map((m, idx) => (idx === i ? { ...m, status } : m)),
        );
    const count = (s: string) => form.data.marks.filter((m) => m.status === s).length;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/attendance/take', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="flex flex-wrap items-center gap-4 text-sm">
                {statuses.map(([key, label]) => (
                    <span key={key} className="text-slate-600">
                        {t(label)}: <b>{count(key)}</b>
                    </span>
                ))}
                <button
                    type="button"
                    onClick={() =>
                        form.setData(
                            'marks',
                            form.data.marks.map((m) => ({ ...m, status: 'present' })),
                        )
                    }
                    className="ms-auto text-fuchsia-700"
                >
                    {t('Mark everyone present')}
                </button>
            </div>

            <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                {students.map((s, i) => (
                    <li key={s.id} className="flex flex-wrap items-center gap-3 px-4 py-2.5">
                        <span className="w-8 text-sm text-slate-400">{s.roll_no}</span>
                        <Avatar name={s.name} src={s.photo} className="h-9 w-9" />
                        <span className="min-w-0 flex-1 truncate font-medium">{s.name}</span>
                        <div role="group" aria-label={s.name} className="flex gap-1">
                            {statuses.map(([key, label, active, hover]) => (
                                <button
                                    key={key}
                                    type="button"
                                    aria-pressed={form.data.marks[i].status === key}
                                    onClick={() => set(i, key)}
                                    className={`rounded-lg border px-3 py-1 text-xs font-medium ${form.data.marks[i].status === key ? `${active} border-transparent` : `border-slate-200 text-slate-600 ${hover}`}`}
                                >
                                    {t(label)}
                                </button>
                            ))}
                        </div>
                    </li>
                ))}
            </ul>
            {(form.errors.date || form.errors.marks || form.errors.subject) && (
                <p className="text-sm text-red-600">{t(form.errors.date ?? form.errors.marks ?? form.errors.subject ?? '')}</p>
            )}
            <button disabled={form.processing} className={primaryBtn}>
                {t('Save attendance')}
            </button>
        </form>
    );
}

export default function TakeAttendance({
    navigation,
    roleLabel,
    filters,
    classes,
    sections,
    subjects,
    students,
    saved,
    today,
    earliest,
    unassigned,
}: {
    navigation: Nav;
    roleLabel: string;
    filters: Filters;
    classes: string[];
    sections: string[];
    subjects: { id: string; name: string }[];
    students: Student[];
    saved: boolean;
    today: string;
    earliest: string | null;
    unassigned: boolean;
}) {
    const { t } = useI18n();
    // Changing an earlier choice clears the later ones, so the page offers only what fits.
    const go = (next: Partial<Filters>) => router.get('/app/attendance', { ...filters, ...next }, { preserveState: false, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Mark Attendance"
            subtitle="Choose the date, then the class, section and subject. Everyone starts as present, so just mark the exceptions."
            wide
        >
            {unassigned ? (
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {t('No classes have been assigned to you yet. Ask an administrator to add your teaching assignments.')}
                </p>
            ) : (
                <>
                    <Card className="grid gap-4 md:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">1 · {t('Date')}</label>
                            <input
                                type="date"
                                className={inputCls}
                                value={filters.date}
                                min={earliest ?? undefined}
                                max={today}
                                onChange={(e) => go({ date: e.target.value })}
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">2 · {t('Class')}</label>
                            <select
                                className={inputCls}
                                value={filters.class}
                                onChange={(e) => go({ class: e.target.value, section: '', subject: '' })}
                            >
                                {classes.map((c) => (
                                    <option key={c} value={c}>
                                        {t(c)}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">3 · {t('Section')}</label>
                            <select className={inputCls} value={filters.section} onChange={(e) => go({ section: e.target.value, subject: '' })}>
                                {sections.map((s) => (
                                    <option key={s}>{s}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">4 · {t('Subject')}</label>
                            <select className={inputCls} value={filters.subject} onChange={(e) => go({ subject: e.target.value })}>
                                {subjects.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {t(s.name)}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {earliest && (
                            <p className="text-xs text-slate-400 md:col-span-4">
                                {t('You can record or correct attendance from {date} up to today.', { date: earliest })}
                            </p>
                        )}
                    </Card>

                    {saved && (
                        <p className="text-sm text-green-700">{t('Attendance was already saved for this selection. Saving again updates it.')}</p>
                    )}
                    {students.length === 0 ? (
                        <p className="text-sm text-slate-500">{t('No students found.')}</p>
                    ) : (
                        <Sheet
                            key={`${filters.date}-${filters.class}-${filters.section}-${filters.subject}-${students.map((s) => s.status).join('')}`}
                            filters={filters}
                            students={students}
                        />
                    )}
                </>
            )}
        </OfficePage>
    );
}
