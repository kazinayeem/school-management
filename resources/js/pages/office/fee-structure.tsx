import { Badge, Card, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';

type Row = { name: string; category: string; frequency: string; optional: boolean; description: string | null; amounts: Record<string, number | string> };

export default function FeeStructure({
    navigation,
    roleLabel,
    rows,
    classes,
    categories,
    frequencies,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    classes: string[];
    categories: Record<string, string>;
    frequencies: Record<string, string>;
}) {
    const { t } = useI18n();
    const form = useForm<{ rows: Row[] }>({ rows: rows.map((r) => ({ ...r, amounts: { ...r.amounts } })) });
    const errors = form.errors as Record<string, string>;
    const setRow = (i: number, patch: Partial<Row>) => form.setData('rows', form.data.rows.map((r, k) => (k === i ? { ...r, ...patch } : r)));
    const setAmount = (i: number, cls: string, v: string) => setRow(i, { amounts: { ...form.data.rows[i].amounts, [cls]: v === '' ? '' : Number(v) } });
    const fillAll = (i: number) => {
        const first = classes.map((c) => form.data.rows[i].amounts[c]).find((v) => v !== undefined && v !== '');
        if (first !== undefined) setRow(i, { amounts: Object.fromEntries(classes.map((c) => [c, first])) });
    };
    const add = () =>
        form.setData('rows', [...form.data.rows, { name: '', category: 'other', frequency: 'monthly', optional: false, description: '', amounts: {} }]);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ rows: d.rows.map((r) => ({ ...r, amounts: Object.fromEntries(Object.entries(r.amounts).filter(([, v]) => v !== '')) })) }));
        form.post('/app/fees/structure', { preserveScroll: true });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Fee Structure"
            subtitle="Set what each class pays: monthly tuition, admission, labs, exams and other charges. Leave a class blank if it does not pay that fee."
            wide
        >
            <form onSubmit={submit} className="space-y-4">
                <Card className="overflow-x-auto p-0">
                    <table className="w-full min-w-[1100px] text-sm">
                        <thead className="border-b border-slate-100 bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th className="px-3 py-3 text-start">{t('Fee')}</th>
                                <th className="px-3 py-3 text-start">{t('Type')}</th>
                                <th className="px-3 py-3 text-start">{t('Charged')}</th>
                                <th className="px-3 py-3 text-start">{t('Only if joined')}</th>
                                {classes.map((c) => (
                                    <th key={c} className="px-1 py-3 text-center whitespace-nowrap">
                                        {t(c).replace(/^Class /, '')}
                                    </th>
                                ))}
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {form.data.rows.map((r, i) => (
                                <tr key={i} className="align-top">
                                    <td className="px-3 py-2">
                                        <input className={inputCls} value={r.name} placeholder={t('e.g. Science Lab')} onChange={(e) => setRow(i, { name: e.target.value })} />
                                        {errors[`rows.${i}.name`] && <p className="text-xs text-red-600">{errors[`rows.${i}.name`]}</p>}
                                    </td>
                                    <td className="px-3 py-2">
                                        <select className={inputCls} value={r.category} onChange={(e) => setRow(i, { category: e.target.value })}>
                                            {Object.entries(categories).map(([k, v]) => (
                                                <option key={k} value={k}>
                                                    {t(v)}
                                                </option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="px-3 py-2">
                                        <select className={inputCls} value={r.frequency} onChange={(e) => setRow(i, { frequency: e.target.value })}>
                                            {Object.entries(frequencies).map(([k, v]) => (
                                                <option key={k} value={k}>
                                                    {t(v)}
                                                </option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        <input type="checkbox" checked={r.optional} onChange={(e) => setRow(i, { optional: e.target.checked })} aria-label={t('Only if joined')} />
                                    </td>
                                    {classes.map((c) => (
                                        <td key={c} className="px-1 py-2">
                                            <input
                                                type="number"
                                                min={0}
                                                className="w-[74px] rounded-lg border border-slate-200 px-2 py-2 text-end text-sm outline-none focus:border-fuchsia-500"
                                                value={r.amounts[c] ?? ''}
                                                onChange={(e) => setAmount(i, c, e.target.value)}
                                                aria-label={`${r.name} ${c}`}
                                            />
                                        </td>
                                    ))}
                                    <td className="px-2 py-2 whitespace-nowrap">
                                        <button type="button" className="me-2 text-xs text-fuchsia-700" onClick={() => fillAll(i)}>
                                            {t('Same for all')}
                                        </button>
                                        <button type="button" aria-label={t('Delete')} className="text-slate-400 hover:text-red-600" onClick={() => form.setData('rows', form.data.rows.filter((_, k) => k !== i))}>
                                            <Trash2 className="inline h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
                <div className="flex flex-wrap items-center gap-3">
                    <button type="button" onClick={add} className="flex items-center gap-1 rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50">
                        <Plus className="h-4 w-4" /> {t('Add a fee')}
                    </button>
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Save fee structure')}
                    </button>
                    <p className="text-xs text-slate-500">
                        <Badge tone="slate">{t('Only if joined')}</Badge> {t('fees (labs, activities) are charged only to students you add under Student Fee Account.')}
                    </p>
                </div>
            </form>
        </OfficePage>
    );
}
