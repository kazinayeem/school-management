import { Avatar } from '@/components/avatar';
import { Badge, Card, Nav, OfficePage } from '@/components/office-ui';
import { StudentSelect, type PickedStudent } from '@/components/student-picker';
import { useI18n } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';

type Item = { label: string; category: string; amount: number; discount: number; net: number; discount_note?: string | null; note?: string | null };
type Account = {
    student: { id: number; name: string; photo: string | null; class_name: string; section: string; roll_no: string; admission_no: string };
    plan: Item[];
    optional: { id: number; name: string; amount: number; frequency: string; on: boolean }[];
    waivers: { id: number; kind: string; value: number; applies_to: string; type: string; reason: string | null; active: number }[];
    invoices: { id: number; invoice_no: string; label: string; due_on: string; payable: number; paid: number; status: string; items: Item[] }[];
};

const tone = (s: string) => (s === 'paid' ? 'green' : s === 'partial' ? 'amber' : 'red');

export default function StudentFees({ navigation, roleLabel, account, frequencies }: { navigation: Nav; roleLabel: string; account: Account | null; frequencies: Record<string, string> }) {
    const { t } = useI18n();
    const s = account?.student;
    const picked: PickedStudent | null = s ? { id: s.id, name: s.name, admission_no: s.admission_no, class_name: s.class_name, section: s.section, roll_no: s.roll_no, photo: s.photo } : null;
    const due = account?.invoices.reduce((n, i) => n + Math.max(0, i.payable - i.paid), 0) ?? 0;
    const paid = account?.invoices.reduce((n, i) => n + i.paid, 0) ?? 0;
    const saved = account?.plan.reduce((n, i) => n + i.discount, 0) ?? 0;
    const monthly = account?.plan.filter((i) => i.category !== 'admission' && i.category !== 'exam' && i.category !== 'library' && i.category !== 'sports').reduce((n, i) => n + i.net, 0) ?? 0;

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Student Fee Account" subtitle="One student's fees, extras, discounts and invoices in one place." wide>
            <Card>
                <div className="max-w-xl">
                    <StudentSelect label="Find a student" value={picked} onChange={(p) => router.get('/app/fees/student', p ? { student: String(p.id) } : {})} />
                </div>
            </Card>

            {account && s && (
                <>
                    <Card className="flex flex-wrap items-center gap-4">
                        <Avatar name={s.name} src={s.photo} className="h-14 w-14" />
                        <div>
                            <h2 className="text-lg font-semibold">{s.name}</h2>
                            <p className="text-sm text-slate-500">
                                {t(s.class_name)} · {s.section} · {t('Roll')} {s.roll_no} · {s.admission_no}
                            </p>
                        </div>
                        <div className="ms-auto grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <div><p className="text-xs text-slate-400">{t('Monthly bill')}</p><p className="text-lg font-semibold">{monthly.toLocaleString()}</p></div>
                            <div><p className="text-xs text-slate-400">{t('Discount a month')}</p><p className="text-lg font-semibold text-emerald-700">{saved.toLocaleString()}</p></div>
                            <div><p className="text-xs text-slate-400">{t('Paid so far')}</p><p className="text-lg font-semibold">{paid.toLocaleString()}</p></div>
                            <div><p className="text-xs text-slate-400">{t('Due')}</p><p className={`text-lg font-semibold ${due > 0 ? 'text-amber-600' : ''}`}>{due.toLocaleString()}</p></div>
                        </div>
                    </Card>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card className="space-y-3">
                            <h3 className="font-semibold">{t('Fee plan')}</h3>
                            <table className="w-full text-sm">
                                <tbody className="divide-y divide-slate-100">
                                    {account.plan.map((i, k) => (
                                        <tr key={k}>
                                            <td className="py-2">
                                                {i.label}
                                                {i.discount_note && <span className="ms-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">{i.discount_note}</span>}
                                            </td>
                                            <td className="py-2 text-end text-slate-400">{i.discount > 0 ? <s>{i.amount.toLocaleString()}</s> : ''}</td>
                                            <td className="py-2 ps-3 text-end font-medium tabular-nums">{i.net.toLocaleString()}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>

                        <Card className="space-y-3">
                            <h3 className="font-semibold">{t('Extras this student joined')}</h3>
                            {account.optional.length === 0 && <p className="text-sm text-slate-500">{t('No optional fees for this class.')}</p>}
                            {account.optional.map((o) => (
                                <label key={o.id} className="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm">
                                    <input type="checkbox" checked={o.on} onChange={(e) => router.post(`/app/fees/student/${s.id}/assign`, { fee_head_id: o.id, on: e.target.checked }, { preserveScroll: true })} />
                                    <span className="flex-1">{o.name}</span>
                                    <span className="text-xs text-slate-400">{t(frequencies[o.frequency] ?? o.frequency)}</span>
                                    <span className="font-medium tabular-nums">{o.amount.toLocaleString()}</span>
                                </label>
                            ))}
                            <h3 className="pt-2 font-semibold">{t('Discounts')}</h3>
                            {account.waivers.length === 0 && <p className="text-sm text-slate-500">{t('No discounts.')}</p>}
                            {account.waivers.map((w) => (
                                <div key={w.id} className="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm">
                                    <span className="flex-1">
                                        {w.kind === 'percent' ? `${w.value}%` : w.value.toLocaleString()} · {t(w.applies_to === 'all' ? 'All fees' : w.applies_to)}
                                        {w.reason && <span className="block text-xs text-slate-500">{w.reason}</span>}
                                    </span>
                                    <Badge tone={w.active ? 'green' : 'slate'}>{w.active ? t('Active') : t('Paused')}</Badge>
                                </div>
                            ))}
                            <Link href="/app/fees/waivers" className="text-sm font-medium text-fuchsia-700">
                                {t('Manage discounts')} →
                            </Link>
                        </Card>
                    </div>

                    <Card className="space-y-3">
                        <h3 className="font-semibold">{t('Invoices')}</h3>
                        {account.invoices.map((inv) => (
                            <details key={inv.id} className="rounded-xl border border-slate-200 p-3 text-sm">
                                <summary className="flex cursor-pointer flex-wrap items-center gap-3">
                                    <span className="font-medium">{inv.label}</span>
                                    <span className="text-xs text-slate-400">{inv.invoice_no} · {t('Due')} {inv.due_on}</span>
                                    <span className="ms-auto tabular-nums">{inv.paid.toLocaleString()} / {inv.payable.toLocaleString()}</span>
                                    <Badge tone={tone(inv.status)}>{t(inv.status)}</Badge>
                                </summary>
                                <table className="mt-2 w-full">
                                    <tbody className="divide-y divide-slate-100">
                                        {inv.items.map((i, k) => (
                                            <tr key={k}>
                                                <td className="py-1.5">{i.label}{i.note && <span className="ms-2 text-xs text-emerald-700">{i.note}</span>}</td>
                                                <td className="py-1.5 text-end text-slate-400">{i.discount > 0 ? <s>{i.amount.toLocaleString()}</s> : ''}</td>
                                                <td className="py-1.5 ps-3 text-end tabular-nums">{i.net.toLocaleString()}</td>
                                            </tr>
                                        ))}
                                        {inv.items.length === 0 && <tr><td className="py-1.5 text-slate-400">{t('No line items on this invoice.')}</td></tr>}
                                    </tbody>
                                </table>
                            </details>
                        ))}
                        {account.invoices.length === 0 && <p className="text-sm text-slate-500">{t('No invoices yet.')}</p>}
                    </Card>
                </>
            )}
        </OfficePage>
    );
}
