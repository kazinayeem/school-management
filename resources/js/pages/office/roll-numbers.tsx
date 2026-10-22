import { Card, ghostBtn, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Student = { id: number; name: string; admission_no: string; roll_no: string };

function Sheet({ filters, students }: { filters: { class: string; section: string }; students: Student[] }) {
    const { t } = useI18n();
    const form = useForm<{ class_name: string; section: string; rolls: { user_id: number; roll_no: string }[] }>({
        class_name: filters.class,
        section: filters.section,
        rolls: students.map((s) => ({ user_id: s.id, roll_no: s.roll_no ?? '' })),
    });

    const renumber = (by: 'name' | 'admission') => {
        const order = [...students].sort((a, b) => (by === 'name' ? a.name.localeCompare(b.name) : a.admission_no.localeCompare(b.admission_no)));
        const next = new Map(order.map((s, i) => [s.id, String(i + 1)]));
        form.setData(
            'rolls',
            form.data.rolls.map((r) => ({ ...r, roll_no: next.get(r.user_id) ?? r.roll_no })),
        );
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, rolls: d.rolls.map((r) => ({ user_id: r.user_id, roll_no: r.roll_no === '' ? null : Number(r.roll_no) })) }));
        form.post('/app/students/roll-numbers', { preserveScroll: true });
    };

    const counts = new Map<string, number>();
    form.data.rolls.forEach((r) => r.roll_no && counts.set(r.roll_no, (counts.get(r.roll_no) ?? 0) + 1));

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="flex flex-wrap gap-3">
                <button type="button" onClick={() => renumber('name')} className={ghostBtn} disabled={students.length === 0}>
                    {t('Number by name (A–Z)')}
                </button>
                <button type="button" onClick={() => renumber('admission')} className={ghostBtn} disabled={students.length === 0}>
                    {t('Number by admission no.')}
                </button>
            </div>
            <Table headers={['Admission no.', 'Student', 'Roll']} empty={students.length === 0 ? 'No students found.' : null}>
                {students.map((s, i) => {
                    const value = form.data.rolls[i].roll_no;
                    const dup = value !== '' && (counts.get(value) ?? 0) > 1;

                    return (
                        <tr key={s.id} className="hover:bg-slate-50">
                            <td className="px-4 py-2">{s.admission_no}</td>
                            <td className="px-4 py-2">{s.name}</td>
                            <td className="px-4 py-2">
                                <input
                                    type="number"
                                    min={1}
                                    aria-label={`${t('Roll')} ${s.name}`}
                                    className={`${inputCls} w-28 ${dup ? 'border-red-500' : ''}`}
                                    value={value}
                                    onChange={(e) =>
                                        form.setData(
                                            'rolls',
                                            form.data.rolls.map((r, idx) => (idx === i ? { ...r, roll_no: e.target.value } : r)),
                                        )
                                    }
                                />
                            </td>
                        </tr>
                    );
                })}
            </Table>
            {form.errors.rolls && <p className="text-sm text-red-600">{t(form.errors.rolls)}</p>}
            {Object.keys(form.errors).some((k) => k.startsWith('rolls.')) && (
                <p className="text-sm text-red-600">{t('Every student needs a roll number.')}</p>
            )}
            {students.length > 0 && (
                <button disabled={form.processing} className={primaryBtn}>
                    {t('Save roll numbers')}
                </button>
            )}
        </form>
    );
}

export default function RollNumbers({
    navigation,
    roleLabel,
    classes,
    filters,
    students,
}: {
    navigation: Nav;
    roleLabel: string;
    classes: string[];
    filters: { class: string; section: string };
    students: Student[];
}) {
    const { t } = useI18n();
    const go = (next: Record<string, string>) =>
        router.get('/app/students/roll-numbers', { ...filters, ...next }, { preserveState: false, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Roll Numbers"
            subtitle="Give each student in a section their roll number. Each number can be used once."
            wide
        >
            <Card className="flex flex-wrap items-end gap-4">
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
            </Card>
            <Sheet key={`${filters.class}-${filters.section}-${students.map((s) => s.roll_no).join(',')}`} filters={filters} students={students} />
        </OfficePage>
    );
}
