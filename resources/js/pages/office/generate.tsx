import { Card, Field, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Head = { id: number; name: string; class_name: string | null; amount: number; category: string; frequency: string; optional: number };

export default function GenerateInvoices({
    navigation,
    roleLabel,
    heads,
    classes,
    categories = {},
    frequencies = {},
}: {
    navigation: Nav;
    roleLabel: string;
    heads: Head[];
    classes: string[];
    categories?: Record<string, string>;
    frequencies?: Record<string, string>;
}) {
    const { t } = useI18n();
    const result = (usePage().props as { flash?: Record<string, any> }).flash?.generated as
        | { created: number; skipped: number; waived: number; nothing?: number }
        | undefined;
    const form = useForm({ head_ids: [] as number[], class_name: '', section: '', label: '', due_on: '' });
    const shown = heads.filter((h) => !form.data.class_name || !h.class_name || h.class_name === form.data.class_name);
    // One entry per fee name, so "Monthly Tuition" is a single choice that picks each class's own amount.
    const names = [...new Map(shown.map((h) => [h.name, h])).values()];
    const idsFor = (name: string) => heads.filter((h) => h.name === name).map((h) => h.id);
    const on = (name: string) => idsFor(name).some((id) => form.data.head_ids.includes(id));
    const toggle = (name: string) =>
        form.setData('head_ids', on(name) ? form.data.head_ids.filter((id) => !idsFor(name).includes(id)) : [...form.data.head_ids, ...idsFor(name)]);
    const amounts = (name: string) => {
        const list = heads.filter((h) => h.name === name).map((h) => h.amount);
        const lo = Math.min(...list);
        const hi = Math.max(...list);

        return lo === hi ? lo.toLocaleString() : `${lo.toLocaleString()} – ${hi.toLocaleString()}`;
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/fees/generate', { preserveScroll: true });
    };

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Generate Invoices" subtitle="Bill a whole class or section at once. Each student gets one invoice with a line for every fee." wide>
            {result && (
                <Card className="border-green-200">
                    <p role="status" className="font-semibold">
                        {t('{created} invoices created, {skipped} skipped (already billed), {waived} with a waiver.', result)}
                    </p>
                    {Boolean(result.nothing) && <p className="text-sm text-slate-500">{t('{n} students had nothing to bill for the chosen fees.', { n: result.nothing ?? 0 })}</p>}
                </Card>
            )}
            <form onSubmit={submit}>
                <Card className="space-y-5">
                    {heads.length === 0 ? (
                        <p className="text-sm text-slate-500">{t('Add a fee head under Fee Structure first.')}</p>
                    ) : (
                        <>
                            <div className="grid gap-4 md:grid-cols-4">
                                <Field label="Class" error={form.errors.class_name}>
                                    <select className={inputCls} value={form.data.class_name} onChange={(e) => form.setData('class_name', e.target.value)}>
                                        <option value="">{t('All classes')}</option>
                                        {classes.map((c) => (
                                            <option key={c} value={c}>
                                                {t(c)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Section" error={form.errors.section}>
                                    <select className={inputCls} value={form.data.section} onChange={(e) => form.setData('section', e.target.value)}>
                                        <option value="">{t('All sections')}</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                    </select>
                                </Field>
                                <Field label="Invoice label" error={form.errors.label}>
                                    <input className={inputCls} value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} placeholder="November 2026 Fees" />
                                </Field>
                                <Field label="Due date" error={form.errors.due_on}>
                                    <input type="date" className={inputCls} value={form.data.due_on} onChange={(e) => form.setData('due_on', e.target.value)} />
                                </Field>
                            </div>
                            <fieldset>
                                <legend className="mb-2 text-sm font-medium text-slate-700">{t('Fees to include')}</legend>
                                {form.errors.head_ids && <p className="mb-2 text-sm text-red-600">{form.errors.head_ids}</p>}
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {names.map((h) => (
                                        <label key={h.name} className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm ${on(h.name) ? 'border-fuchsia-500 bg-fuchsia-50' : 'border-slate-200'}`}>
                                            <input type="checkbox" checked={on(h.name)} onChange={() => toggle(h.name)} />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">{h.name}</span>
                                                <span className="block text-xs text-slate-500">
                                                    {t(categories[h.category] ?? h.category)} · {t(frequencies[h.frequency] ?? h.frequency)}
                                                    {h.optional ? ` · ${t('Only if joined')}` : ''}
                                                </span>
                                            </span>
                                            <span className="text-xs tabular-nums text-slate-600">{amounts(h.name)}</span>
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        </>
                    )}
                    <p className="text-xs text-slate-500">
                        {t('Students who already have an invoice with this label are skipped. Class amounts, extras the student joined and active discounts are applied automatically.')}
                    </p>
                    <button disabled={form.processing || heads.length === 0} className={primaryBtn}>
                        {t('Generate invoices')}
                    </button>
                </Card>
            </form>
        </OfficePage>
    );
}
