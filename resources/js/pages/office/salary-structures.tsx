import { Card, Field, ghostBtn, inputCls, money, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Component = { id: number; name: string; kind: string; calc: string; value: number };
type Row = {
    id: number;
    name: string;
    basic: number | null;
    values: Record<string, number>;
    earnings: number | null;
    deductions: number | null;
    net: number | null;
};

function Editor({ row, components, onDone }: { row: Row; components: Component[]; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm<{ user_id: number; basic: string; items: Record<string, string> }>({
        user_id: row.id,
        basic: row.basic ? String(row.basic) : '',
        items: Object.fromEntries(
            components.map((c) => [String(c.id), row.basic ? (row.values[c.id] !== undefined ? String(row.values[c.id]) : '') : String(c.value)]),
        ),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/payroll/structures', { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit}>
            <Card className="space-y-4 border-fuchsia-200">
                <h2 className="font-semibold">{row.name}</h2>
                <div className="grid gap-4 md:grid-cols-3">
                    <Field label="Basic salary" error={form.errors.basic}>
                        <input
                            type="number"
                            min={1}
                            className={inputCls}
                            value={form.data.basic}
                            onChange={(e) => form.setData('basic', e.target.value)}
                        />
                    </Field>
                    {components.map((c) => (
                        <Field
                            key={c.id}
                            label={`${c.name} (${c.kind === 'earning' ? t('Earning') : t('Deduction')}, ${c.calc === 'percent' ? '%' : t('amount')})`}
                        >
                            <input
                                type="number"
                                min={0}
                                className={inputCls}
                                value={form.data.items[c.id] ?? ''}
                                onChange={(e) => form.setData('items', { ...form.data.items, [c.id]: e.target.value })}
                            />
                        </Field>
                    ))}
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

export default function SalaryStructures({
    navigation,
    roleLabel,
    rows,
    components,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    components: Component[];
}) {
    const { t } = useI18n();
    const [editing, setEditing] = useState<Row | null>(null);

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Salary Structures"
            subtitle="Each staff member's basic pay and which allowances and deductions apply."
        >
            {editing && <Editor key={editing.id} row={editing} components={components} onDone={() => setEditing(null)} />}
            <Table headers={['Staff', 'Basic', 'Earnings', 'Deductions', 'Net pay', '']} empty={rows.length === 0 ? 'No staff yet.' : null}>
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">{r.name}</td>
                        <td className="px-4 py-3">{r.basic ? money(r.basic) : <span className="text-slate-400">{t('Not set')}</span>}</td>
                        <td className="px-4 py-3">{money(r.earnings)}</td>
                        <td className="px-4 py-3">{money(r.deductions)}</td>
                        <td className="px-4 py-3 font-semibold">{money(r.net)}</td>
                        <td className="px-4 py-3 text-end">
                            <button onClick={() => setEditing(r)} className="rounded-lg border px-3 py-1 text-xs hover:bg-slate-50">
                                {r.basic ? t('Edit') : t('Set up')}
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
