import { Badge, Card, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Row = {
    id: number;
    name: string;
    employee_no: string | null;
    designation: string | null;
    status: string;
    note: string;
    marked: boolean;
    on_leave: boolean;
};
type Month = { name: string; present: number; late: number; absent: number; leave: number; percent: number | null };

const statuses = ['present', 'late', 'absent', 'leave'] as const;
const label: Record<string, string> = { present: 'Present', late: 'Late', absent: 'Absent', leave: 'Leave' };

function Sheet({ date, rows }: { date: string; rows: Row[] }) {
    const { t } = useI18n();
    const form = useForm<{ date: string; marks: { user_id: number; status: string; note: string }[] }>({
        date,
        marks: rows.map((r) => ({ user_id: r.id, status: r.status, note: r.note })),
    });
    const set = (i: number, patch: Partial<(typeof form.data.marks)[number]>) =>
        form.setData(
            'marks',
            form.data.marks.map((m, idx) => (idx === i ? { ...m, ...patch } : m)),
        );
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/attendance/staff', { preserveScroll: true });
    };
    const count = (s: string) => form.data.marks.filter((m) => m.status === s).length;

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="flex flex-wrap items-center gap-4 text-sm text-slate-600">
                {statuses.map((s) => (
                    <span key={s}>
                        {t(label[s])}: <b>{count(s)}</b>
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
            <Table headers={['Employee no.', 'Name', 'Role', 'Status', 'Note']} empty={rows.length === 0 ? 'No staff found.' : null}>
                {rows.map((r, i) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-2 font-mono text-xs">{r.employee_no}</td>
                        <td className="px-4 py-2 font-medium">
                            {r.name} {r.on_leave && <Badge tone="amber">{t('Approved leave')}</Badge>}
                        </td>
                        <td className="px-4 py-2">{r.designation}</td>
                        <td className="px-4 py-2">
                            <select
                                aria-label={`${t('Status')} ${r.name}`}
                                className={`${inputCls} w-36`}
                                value={form.data.marks[i].status}
                                onChange={(e) => set(i, { status: e.target.value })}
                            >
                                {statuses.map((s) => (
                                    <option key={s} value={s}>
                                        {t(label[s])}
                                    </option>
                                ))}
                            </select>
                        </td>
                        <td className="px-4 py-2">
                            <input
                                aria-label={`${t('Note')} ${r.name}`}
                                className={inputCls}
                                value={form.data.marks[i].note}
                                onChange={(e) => set(i, { note: e.target.value })}
                            />
                        </td>
                    </tr>
                ))}
            </Table>
            {rows.length > 0 && (
                <button disabled={form.processing} className={primaryBtn}>
                    {t('Save attendance')}
                </button>
            )}
        </form>
    );
}

export default function StaffAttendance({
    navigation,
    roleLabel,
    date,
    rows,
    month,
    monthLabel,
    lateAfter,
}: {
    navigation: Nav;
    roleLabel: string;
    date: string;
    rows: Row[];
    month: Month[];
    monthLabel: string;
    lateAfter: string;
}) {
    const { t } = useI18n();
    const marked = rows.filter((r) => r.marked).length;

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Staff Attendance"
            subtitle="Record who is in each day. Approved leave is filled in for you."
            wide
        >
            <Card className="flex flex-wrap items-end gap-4">
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Date')}</label>
                    <input
                        type="date"
                        className={inputCls}
                        value={date}
                        onChange={(e) => router.get('/app/attendance/staff', { date: e.target.value }, { preserveState: false, replace: true })}
                    />
                </div>
                <p className="text-sm text-slate-600">
                    {marked === 0 ? t('Not marked yet for this day.') : t('{n} of {total} saved for this day.', { n: marked, total: rows.length })}
                </p>
                <button
                    onClick={() =>
                        confirm(t('Mark attendance for {date} from the punch log?', { date })) &&
                        router.post('/app/attendance/staff/punches', { date }, { preserveScroll: true })
                    }
                    className="ms-auto rounded-lg border border-fuchsia-700 px-4 py-2 text-sm font-semibold text-fuchsia-700 hover:bg-fuchsia-50"
                >
                    {t('Mark from the punch log')}
                </button>
            </Card>
            <p className="text-xs text-slate-500">
                {t('From the punch log, arriving after {time} counts as late. Marks you have saved are never overwritten.', { time: lateAfter })}
            </p>

            <Sheet key={`${date}-${rows.map((r) => r.status + r.note).join('|')}`} date={date} rows={rows} />

            <h2 className="pt-4 font-semibold">{t('Summary for {month}', { month: monthLabel })}</h2>
            <Table headers={['Name', 'Present', 'Late', 'Absent', 'Leave', 'Attendance']} empty={month.length === 0 ? 'No staff found.' : null}>
                {month.map((m) => (
                    <tr key={m.name} className="hover:bg-slate-50">
                        <td className="px-4 py-2">{m.name}</td>
                        <td className="px-4 py-2">{m.present}</td>
                        <td className="px-4 py-2">{m.late}</td>
                        <td className="px-4 py-2">{m.absent}</td>
                        <td className="px-4 py-2">{m.leave}</td>
                        <td className="px-4 py-2">{m.percent === null ? '—' : `${m.percent}%`}</td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
