import { Badge, Card, Field, inputCls, money, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Run = { id: number; month: string; status: string; paid_on: string | null; staff_count: number; total_net: number };
type Slip = { id: number; name: string; basic: number; earnings: number; deductions: number; net: number };

export default function PayrollRuns({
    navigation,
    roleLabel,
    runs,
    selected,
    payslips,
    defaultMonth,
}: {
    navigation: Nav;
    roleLabel: string;
    runs: Run[];
    selected: number;
    payslips: Slip[];
    defaultMonth: string;
}) {
    const { t } = useI18n();
    const form = useForm({ month: defaultMonth });
    const run = runs.find((r) => r.id === selected);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/payroll', { preserveScroll: true });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Payroll Runs"
            subtitle="Generate a month's payslips from the salary structures, then mark them paid."
        >
            <form onSubmit={submit}>
                <Card className="flex flex-wrap items-end gap-4">
                    <Field label="Month" error={form.errors.month}>
                        <input type="month" className={inputCls} value={form.data.month} onChange={(e) => form.setData('month', e.target.value)} />
                    </Field>
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Generate payroll')}
                    </button>
                </Card>
            </form>

            <Table
                headers={['Month', 'Staff', 'Total net pay', 'Status', '']}
                empty={runs.length === 0 ? 'No payroll has been generated yet.' : null}
            >
                {runs.map((r) => (
                    <tr key={r.id} className={`hover:bg-slate-50 ${r.id === selected ? 'bg-fuchsia-50' : ''}`}>
                        <td className="px-4 py-3">{r.month}</td>
                        <td className="px-4 py-3">{r.staff_count}</td>
                        <td className="px-4 py-3">{money(r.total_net)}</td>
                        <td className="px-4 py-3">
                            <Badge tone={r.status === 'paid' ? 'green' : 'amber'}>{r.status === 'paid' ? t('Paid') : t('Draft')}</Badge>
                        </td>
                        <td className="space-x-2 px-4 py-3 text-end whitespace-nowrap">
                            <button
                                onClick={() => router.get('/app/payroll', { run: r.id }, { preserveState: true, replace: true })}
                                className="rounded-lg border px-3 py-1 text-xs hover:bg-slate-50"
                            >
                                {t('View')}
                            </button>
                            {r.status !== 'paid' && (
                                <>
                                    <button
                                        onClick={() =>
                                            confirm(t('Mark this payroll as paid?')) &&
                                            router.put(`/app/payroll/${r.id}/pay`, {}, { preserveScroll: true })
                                        }
                                        className="rounded-lg bg-fuchsia-700 px-3 py-1 text-xs text-white"
                                    >
                                        {t('Mark paid')}
                                    </button>
                                    <button
                                        onClick={() =>
                                            confirm(t('Delete this entry?')) && router.delete(`/app/payroll/${r.id}`, { preserveScroll: true })
                                        }
                                        className="rounded-lg border px-3 py-1 text-xs text-red-600 hover:bg-slate-50"
                                    >
                                        {t('Delete')}
                                    </button>
                                </>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>

            {run && (
                <>
                    <h2 className="font-semibold">{t('Payslips for {month}', { month: run.month })}</h2>
                    <Table headers={['Staff', 'Basic', 'Earnings', 'Deductions', 'Net pay']} empty={payslips.length === 0 ? 'No payslips.' : null}>
                        {payslips.map((p) => (
                            <tr key={p.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3">{p.name}</td>
                                <td className="px-4 py-3">{money(p.basic)}</td>
                                <td className="px-4 py-3">{money(p.earnings)}</td>
                                <td className="px-4 py-3">{money(p.deductions)}</td>
                                <td className="px-4 py-3 font-semibold">{money(p.net)}</td>
                            </tr>
                        ))}
                    </Table>
                </>
            )}
        </OfficePage>
    );
}
