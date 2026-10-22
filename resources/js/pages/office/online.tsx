import { Badge, Card, Field, inputCls, money, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { FormEvent } from 'react';

type Row = {
    id: number;
    name: string;
    invoice_no: string | null;
    gateway: string;
    reference: string;
    amount: number;
    status: string;
    created_at: string;
    paid_on?: string | null;
    sender_number?: string | null;
    note?: string | null;
    review_note?: string | null;
    proof_url?: string | null;
    submitted_by_name?: string | null;
};
const tone = { pending: 'amber', success: 'green', failed: 'red' } as const;
const label = { pending: 'Pending', success: 'Paid', failed: 'Failed' } as const;

export default function Online({
    navigation,
    roleLabel,
    rows,
    counts,
    filters,
    gateways,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    counts: Record<string, number>;
    filters: { status: string };
    gateways: string[];
}) {
    const { t } = useI18n();
    const form = useForm({ invoice_no: '', gateway: gateways[0], reference: '', amount: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/fees/online', { preserveScroll: true, onSuccess: () => form.reset('invoice_no', 'reference', 'amount') });
    };
    const settle = (id: number, status: 'success' | 'failed') => {
        const review_note = status === 'failed' ? prompt(t('Why is this payment rejected? The family will see this note.')) : '';
        if (status === 'failed' && review_note === null) return;
        router.put(`/app/fees/online/${id}`, { status, review_note: review_note ?? '' }, { preserveScroll: true });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Transactions"
            subtitle="Payments families say they made by bKash, Nagad or bank. Check the slip, then approve to post it to the invoice or reject with a reason."
        >
            <div className="grid gap-3 sm:grid-cols-3">
                {(['pending', 'success', 'failed'] as const).map((s) => (
                    <button
                        key={s}
                        onClick={() =>
                            router.get('/app/fees/online', { status: filters.status === s ? '' : s }, { preserveState: true, replace: true })
                        }
                        className={`rounded-xl border bg-white p-4 text-start shadow-sm ${filters.status === s ? 'border-fuchsia-500' : 'border-slate-200'}`}
                    >
                        <div className="text-2xl font-bold">{counts[s] ?? 0}</div>
                        <div className="text-xs text-slate-500">{t(label[s])}</div>
                    </button>
                ))}
            </div>

            <form onSubmit={submit}>
                <Card className="space-y-4">
                    <h2 className="font-semibold">{t('Record a transaction')}</h2>
                    <div className="grid gap-4 md:grid-cols-4">
                        <Field label="Invoice no." error={form.errors.invoice_no}>
                            <input className={inputCls} value={form.data.invoice_no} onChange={(e) => form.setData('invoice_no', e.target.value)} />
                        </Field>
                        <Field label="Gateway" error={form.errors.gateway}>
                            <select className={inputCls} value={form.data.gateway} onChange={(e) => form.setData('gateway', e.target.value)}>
                                {gateways.map((g) => (
                                    <option key={g}>{g}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Reference" error={form.errors.reference}>
                            <input className={inputCls} value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                        </Field>
                        <Field label="Amount" error={form.errors.amount}>
                            <input
                                type="number"
                                min={1}
                                className={inputCls}
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                            />
                        </Field>
                    </div>
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Record')}
                    </button>
                </Card>
            </form>

            <Table
                headers={['Date', 'Student', 'Invoice', 'Method', 'Transaction ID', 'Amount', 'Details', 'Status', '']}
                empty={rows.length === 0 ? 'No transactions yet.' : null}
            >
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">{r.created_at.slice(0, 10)}</td>
                        <td className="px-4 py-3">{r.name}</td>
                        <td className="px-4 py-3">{r.invoice_no}</td>
                        <td className="px-4 py-3">{r.gateway}</td>
                        <td className="px-4 py-3 font-mono text-xs">{r.reference}</td>
                        <td className="px-4 py-3">{money(r.amount)}</td>
                        <td className="max-w-[240px] px-4 py-3 text-xs text-slate-500">
                            {r.paid_on && <div>{t('Paid on')} {r.paid_on}</div>}
                            {r.sender_number && <div dir="ltr">{t('From')} {r.sender_number}</div>}
                            {r.submitted_by_name && <div>{t('Submitted by')} {r.submitted_by_name}</div>}
                            {r.note && <div className="text-slate-600">“{r.note}”</div>}
                            {r.review_note && <div className="text-rose-600">{t('School')}: {r.review_note}</div>}
                            {r.proof_url && (
                                <a href={r.proof_url} target="_blank" rel="noreferrer" className="font-semibold text-fuchsia-700">
                                    {t('View slip')}
                                </a>
                            )}
                        </td>
                        <td className="px-4 py-3">
                            <Badge tone={tone[r.status as keyof typeof tone] ?? 'slate'}>
                                {t(label[r.status as keyof typeof label] ?? r.status)}
                            </Badge>
                        </td>
                        <td className="space-x-2 px-4 py-3 text-end whitespace-nowrap">
                            {r.status === 'pending' && (
                                <>
                                    <button aria-label={t('Confirm payment')} onClick={() => settle(r.id, 'success')} className="text-green-600">
                                        <Check className="inline h-4 w-4" />
                                    </button>
                                    <button aria-label={t('Mark failed')} onClick={() => settle(r.id, 'failed')} className="text-red-500">
                                        <X className="inline h-4 w-4" />
                                    </button>
                                </>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
