import { Card, Field, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { FormEvent } from 'react';

type Row = {
    id: number;
    date: string;
    class_name: string;
    section: string;
    period_label: string;
    original_teacher: string | null;
    substitute: string;
    reason: string | null;
};

export default function Substitutions({
    navigation,
    roleLabel,
    rows,
    classes,
    periods,
    teachers,
    today,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    classes: string[];
    periods: { id: number; label: string }[];
    teachers: { id: number; name: string }[];
    today: string;
}) {
    const { t } = useI18n();
    const form = useForm({
        date: today,
        class_name: classes[0] ?? '',
        section: 'A',
        period_label: periods[0]?.label ?? '',
        substitute_user_id: '',
        reason: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/timetable/substitutions', { preserveScroll: true, onSuccess: () => form.reset('reason', 'substitute_user_id') });
    };

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Substitutions" subtitle="Cover a class when its teacher is away.">
            <form onSubmit={submit}>
                <Card className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Date" error={form.errors.date}>
                            <input
                                type="date"
                                min={today}
                                className={inputCls}
                                value={form.data.date}
                                onChange={(e) => form.setData('date', e.target.value)}
                            />
                        </Field>
                        <Field label="Class" error={form.errors.class_name}>
                            <select className={inputCls} value={form.data.class_name} onChange={(e) => form.setData('class_name', e.target.value)}>
                                {classes.map((c) => (
                                    <option key={c} value={c}>
                                        {t(c)}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Section" error={form.errors.section}>
                            <select className={inputCls} value={form.data.section} onChange={(e) => form.setData('section', e.target.value)}>
                                <option>A</option>
                                <option>B</option>
                            </select>
                        </Field>
                        <Field label="Period" error={form.errors.period_label}>
                            <select
                                className={inputCls}
                                value={form.data.period_label}
                                onChange={(e) => form.setData('period_label', e.target.value)}
                            >
                                {periods.map((p) => (
                                    <option key={p.id}>{p.label}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Substitute teacher" error={form.errors.substitute_user_id}>
                            <select
                                className={inputCls}
                                value={form.data.substitute_user_id}
                                onChange={(e) => form.setData('substitute_user_id', e.target.value)}
                            >
                                <option value="">{t('Choose...')}</option>
                                {teachers.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Reason" error={form.errors.reason}>
                            <input className={inputCls} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                        </Field>
                    </div>
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Add substitution')}
                    </button>
                </Card>
            </form>

            <Table
                headers={['Date', 'Class', 'Period', 'Away', 'Covered by', 'Reason', '']}
                empty={rows.length === 0 ? 'No substitutions yet.' : null}
            >
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">{r.date}</td>
                        <td className="px-4 py-3">
                            {t(r.class_name)} {r.section}
                        </td>
                        <td className="px-4 py-3">{r.period_label}</td>
                        <td className="px-4 py-3">{r.original_teacher}</td>
                        <td className="px-4 py-3">{r.substitute}</td>
                        <td className="px-4 py-3">{r.reason}</td>
                        <td className="px-4 py-3 text-end">
                            <button
                                aria-label={t('Delete')}
                                className="text-slate-500 hover:text-red-600"
                                onClick={() =>
                                    confirm(t('Delete this entry?')) &&
                                    router.delete(`/app/timetable/substitutions/${r.id}`, { preserveScroll: true })
                                }
                            >
                                <Trash2 className="inline h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
