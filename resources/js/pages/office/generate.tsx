import { Card, Field, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Head = { id: number; name: string; class_name: string | null; amount: number };

export default function GenerateInvoices({
    navigation,
    roleLabel,
    heads,
    classes,
}: {
    navigation: Nav;
    roleLabel: string;
    heads: Head[];
    classes: string[];
}) {
    const { t } = useI18n();
    const result = (usePage().props as { flash?: Record<string, any> }).flash?.generated as
        | { created: number; skipped: number; waived: number }
        | undefined;
    const form = useForm({ fee_head_id: heads[0]?.id ? String(heads[0].id) : '', class_name: '', section: '', label: '', due_on: '' });
    const head = heads.find((h) => String(h.id) === form.data.fee_head_id);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/fees/generate', { preserveScroll: true });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Generate Invoices"
            subtitle="Create a fee invoice for every student in a class at once."
        >
            {result && (
                <Card className="border-green-200">
                    <p role="status" className="font-semibold">
                        {t('{created} invoices created, {skipped} skipped (already billed), {waived} with a waiver.', result)}
                    </p>
                </Card>
            )}
            <form onSubmit={submit}>
                <Card className="space-y-5">
                    {heads.length === 0 ? (
                        <p className="text-sm text-slate-500">{t('Add a fee head under Fee Structure first.')}</p>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="Fee" error={form.errors.fee_head_id}>
                                <select
                                    className={inputCls}
                                    value={form.data.fee_head_id}
                                    onChange={(e) => form.setData('fee_head_id', e.target.value)}
                                >
                                    {heads.map((h) => (
                                        <option key={h.id} value={h.id}>
                                            {h.name} — {h.amount.toLocaleString()}
                                            {h.class_name ? ` (${t(h.class_name)})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Invoice label" error={form.errors.label}>
                                <input
                                    className={inputCls}
                                    value={form.data.label}
                                    onChange={(e) => form.setData('label', e.target.value)}
                                    placeholder="October 2026 Tuition"
                                />
                            </Field>
                            <Field label="Class" error={form.errors.class_name}>
                                <select
                                    className={inputCls}
                                    value={head?.class_name ?? form.data.class_name}
                                    disabled={Boolean(head?.class_name)}
                                    onChange={(e) => form.setData('class_name', e.target.value)}
                                >
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
                            <Field label="Due date" error={form.errors.due_on}>
                                <input
                                    type="date"
                                    className={inputCls}
                                    value={form.data.due_on}
                                    onChange={(e) => form.setData('due_on', e.target.value)}
                                />
                            </Field>
                        </div>
                    )}
                    <p className="text-xs text-slate-500">
                        {t('Students who already have an invoice with this label are skipped. Active waivers are applied automatically.')}
                    </p>
                    <button disabled={form.processing || heads.length === 0} className={primaryBtn}>
                        {t('Generate invoices')}
                    </button>
                </Card>
            </form>
        </OfficePage>
    );
}
