import { MadeBy } from '@/components/made-by';
import { Head } from '@inertiajs/react';
import { useI18n } from '@/lib/i18n';

type Props = {
    slip: { id: number; month: string; paid_on: string; basic: number; earnings: number; deductions: number; net: number };
    me: { name: string; employee_no: string | null; designation: string | null; department: string | null };
    school: { name: string; currency: string };
};

export default function MyPayslip({ slip, me, school, base = '/app/me' }: Props & { base?: string }) {
    const { t } = useI18n();
    const m = (n: number) => `${school.currency} ${Number(n).toLocaleString()}`;
    const rows: [string, number, boolean?][] = [
        ['Basic salary', slip.basic],
        ['Allowances', slip.earnings],
        ['Deductions', slip.deductions, true],
    ];

    return (
        <div className="min-h-screen bg-slate-100 p-4 print:bg-white print:p-0">
            <Head title={`${t('Payslip')} ${slip.month}`} />
            <div className="no-print mx-auto mb-4 flex max-w-xl justify-between">
                <a href={base} className="text-sm text-slate-600">← {t('Back')}</a>
                <span className="flex gap-2"><a href={`${base}/payslips/${slip.id}/pdf`} className="rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white">{t('Download PDF')}</a><button onClick={() => window.print()} className="rounded-lg border border-slate-300 px-4 py-2 text-sm">{t('Print')}</button></span>
            </div>
            <article className="mx-auto max-w-xl rounded-2xl bg-white p-8 text-sm shadow print:shadow-none">
                <header className="border-b pb-4 text-center">
                    <h1 className="text-xl font-bold">{school.name}</h1>
                    <p className="text-xs tracking-widest text-slate-500 uppercase">{t('Payslip')} · {slip.month}</p>
                </header>
                <dl className="my-5 grid grid-cols-2 gap-3">
                    <div><dt className="text-xs text-slate-400">{t('Name')}</dt><dd className="font-medium">{me.name}</dd></div>
                    <div><dt className="text-xs text-slate-400">{t('Employee no.')}</dt><dd>{me.employee_no ?? '—'}</dd></div>
                    <div><dt className="text-xs text-slate-400">{t('Designation')}</dt><dd>{me.designation ?? '—'}</dd></div>
                    <div><dt className="text-xs text-slate-400">{t('Paid on')}</dt><dd>{slip.paid_on}</dd></div>
                </dl>
                <table className="w-full">
                    <tbody>
                        {rows.map(([k, v, minus]) => (
                            <tr key={k} className="border-b">
                                <td className="py-2">{t(k)}</td>
                                <td className="py-2 text-end">{minus ? '− ' : ''}{m(v)}</td>
                            </tr>
                        ))}
                        <tr>
                            <td className="py-3 font-bold">{t('Net pay')}</td>
                            <td className="py-3 text-end text-lg font-bold">{m(slip.net)}</td>
                        </tr>
                    </tbody>
                </table>
            </article>
            <MadeBy className="mt-3" />
        </div>
    );
}
