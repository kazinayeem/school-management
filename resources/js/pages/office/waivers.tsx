import { Badge, Card, Field, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { PickedStudent, StudentSelect } from '@/components/student-picker';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Row = {
    id: number;
    name: string;
    admission_no: string | null;
    class_name: string | null;
    kind: string;
    value: number;
    reason: string | null;
    active: number;
};

export default function Waivers({ navigation, roleLabel, rows }: { navigation: Nav; roleLabel: string; rows: Row[] }) {
    const { t } = useI18n();
    const [picked, setPicked] = useState<PickedStudent | null>(null);
    const form = useForm({ student: '', kind: 'percent', value: '', reason: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/fees/waivers', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setPicked(null);
            },
        });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Waivers & Discounts"
            subtitle="Reduce what a student pays. Waivers apply to invoices generated afterwards."
        >
            <form onSubmit={submit}>
                <Card className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-4">
                        <StudentSelect
                            label="Student"
                            value={picked}
                            error={form.errors.student}
                            onChange={(s) => {
                                setPicked(s);
                                form.setData('student', s?.admission_no ?? '');
                            }}
                        />
                        <Field label="Type" error={form.errors.kind}>
                            <select className={inputCls} value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>
                                <option value="percent">{t('Percent')}</option>
                                <option value="fixed">{t('Fixed amount')}</option>
                            </select>
                        </Field>
                        <Field label="Value" error={form.errors.value}>
                            <input
                                type="number"
                                min={1}
                                className={inputCls}
                                value={form.data.value}
                                onChange={(e) => form.setData('value', e.target.value)}
                            />
                        </Field>
                        <Field label="Reason" error={form.errors.reason}>
                            <input className={inputCls} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                        </Field>
                    </div>
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Add waiver')}
                    </button>
                </Card>
            </form>

            <Table headers={['Student', 'Class', 'Waiver', 'Reason', 'Status', '']} empty={rows.length === 0 ? 'No waivers yet.' : null}>
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">
                            {r.name} <span className="text-xs text-slate-400">{r.admission_no}</span>
                        </td>
                        <td className="px-4 py-3">{r.class_name ? t(r.class_name) : ''}</td>
                        <td className="px-4 py-3">{r.kind === 'percent' ? `${r.value}%` : r.value.toLocaleString()}</td>
                        <td className="px-4 py-3">{r.reason}</td>
                        <td className="px-4 py-3">
                            <button onClick={() => router.put(`/app/fees/waivers/${r.id}`, {}, { preserveScroll: true })}>
                                <Badge tone={r.active ? 'green' : 'slate'}>{r.active ? t('Active') : t('Paused')}</Badge>
                            </button>
                        </td>
                        <td className="px-4 py-3 text-end">
                            <button
                                aria-label={t('Delete')}
                                className="text-slate-500 hover:text-red-600"
                                onClick={() =>
                                    confirm(t('Delete this entry?')) && router.delete(`/app/fees/waivers/${r.id}`, { preserveScroll: true })
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
