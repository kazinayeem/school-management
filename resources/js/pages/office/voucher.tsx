import { Card, Field, ghostBtn, inputCls, money, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';

type Account = { id: number; code: string; name: string; type: string };
type Line = { account_id: string; debit: string; credit: string };

export default function Voucher({
    navigation,
    roleLabel,
    accounts,
    today,
}: {
    navigation: Nav;
    roleLabel: string;
    accounts: Account[];
    today: string;
}) {
    const { t } = useI18n();
    const byCode = (code: string) => String(accounts.find((a) => a.code === code)?.id ?? '');
    const blank = (): Line => ({ account_id: '', debit: '', credit: '' });
    const form = useForm<{ date: string; type: string; narration: string; lines: Line[] }>({
        date: today,
        type: 'receipt',
        narration: '',
        lines: [
            { account_id: byCode('1001'), debit: '', credit: '' },
            { account_id: byCode('4001'), debit: '', credit: '' },
        ],
    });

    const setLine = (i: number, patch: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((l, idx) => (idx === i ? { ...l, ...patch } : l)),
        );
    const totalDebit = form.data.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0);
    const totalCredit = form.data.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0);
    const balanced = totalDebit > 0 && totalDebit === totalCredit;

    const chooseType = (type: string) => {
        const presets: Record<string, [string, string]> = { receipt: ['1001', '4001'], payment: ['5001', '1001'] };
        form.setData((d) => ({
            ...d,
            type,
            lines: presets[type]
                ? [
                      { account_id: byCode(presets[type][0]), debit: '', credit: '' },
                      { account_id: byCode(presets[type][1]), debit: '', credit: '' },
                  ]
                : d.lines,
        }));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            lines: d.lines.map((l) => ({
                account_id: l.account_id,
                debit: l.debit === '' ? 0 : Number(l.debit),
                credit: l.credit === '' ? 0 : Number(l.credit),
            })),
        }));
        form.post('/app/accounting/vouchers', {
            preserveScroll: true,
            onSuccess: () => form.setData((d) => ({ ...d, narration: '', lines: d.lines.map((l) => ({ ...l, debit: '', credit: '' })) })),
        });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Voucher Entry"
            subtitle="Record money received, paid, or any adjustment. Debits must equal credits."
        >
            <form onSubmit={submit}>
                <Card className="space-y-5">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Date" error={form.errors.date}>
                            <input type="date" className={inputCls} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                        </Field>
                        <Field label="Type" error={form.errors.type}>
                            <select className={inputCls} value={form.data.type} onChange={(e) => chooseType(e.target.value)}>
                                <option value="receipt">{t('Receipt')}</option>
                                <option value="payment">{t('Payment')}</option>
                                <option value="journal">{t('Journal')}</option>
                            </select>
                        </Field>
                        <Field label="Narration" error={form.errors.narration}>
                            <input className={inputCls} value={form.data.narration} onChange={(e) => form.setData('narration', e.target.value)} />
                        </Field>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead className="text-xs text-slate-500 uppercase">
                                <tr>
                                    <th className="py-2 text-start">{t('Account')}</th>
                                    <th className="w-36 py-2 text-start">{t('Debit')}</th>
                                    <th className="w-36 py-2 text-start">{t('Credit')}</th>
                                    <th className="w-10" />
                                </tr>
                            </thead>
                            <tbody>
                                {form.data.lines.map((line, i) => (
                                    <tr key={i}>
                                        <td className="py-1 pe-2">
                                            <select
                                                className={inputCls}
                                                value={line.account_id}
                                                onChange={(e) => setLine(i, { account_id: e.target.value })}
                                                aria-label={t('Account')}
                                            >
                                                <option value="">{t('Choose...')}</option>
                                                {accounts.map((a) => (
                                                    <option key={a.id} value={a.id}>
                                                        {a.code} · {a.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="py-1 pe-2">
                                            <input
                                                type="number"
                                                min={0}
                                                className={inputCls}
                                                value={line.debit}
                                                onChange={(e) => setLine(i, { debit: e.target.value, credit: e.target.value ? '' : line.credit })}
                                                aria-label={t('Debit')}
                                            />
                                        </td>
                                        <td className="py-1 pe-2">
                                            <input
                                                type="number"
                                                min={0}
                                                className={inputCls}
                                                value={line.credit}
                                                onChange={(e) => setLine(i, { credit: e.target.value, debit: e.target.value ? '' : line.debit })}
                                                aria-label={t('Credit')}
                                            />
                                        </td>
                                        <td>
                                            {form.data.lines.length > 2 && (
                                                <button
                                                    type="button"
                                                    aria-label={t('Delete')}
                                                    onClick={() =>
                                                        form.setData(
                                                            'lines',
                                                            form.data.lines.filter((_, idx) => idx !== i),
                                                        )
                                                    }
                                                    className="text-slate-400 hover:text-red-600"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="font-semibold">
                                <tr>
                                    <td className="py-2 text-end">{t('Total')}</td>
                                    <td className="py-2">{money(totalDebit)}</td>
                                    <td className="py-2">{money(totalCredit)}</td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {form.errors.lines && <p className="text-sm text-red-600">{t(form.errors.lines)}</p>}
                    {!balanced && totalDebit + totalCredit > 0 && (
                        <p className="text-sm text-amber-600">{t('Total debit and total credit must be equal.')}</p>
                    )}

                    <div className="flex gap-3">
                        <button
                            type="button"
                            onClick={() => form.setData('lines', [...form.data.lines, blank()])}
                            className={`${ghostBtn} flex items-center gap-1`}
                        >
                            <Plus className="h-4 w-4" /> {t('Add line')}
                        </button>
                        <button disabled={form.processing || !balanced} className={primaryBtn}>
                            {t('Save voucher')}
                        </button>
                    </div>
                </Card>
            </form>
        </OfficePage>
    );
}
