import { PhotoUpload } from '@/components/avatar';
import { useUrlTab } from '@/hooks/use-url-tab';
import { UserFiles, type UserFile } from '@/components/user-files';
import { Badge, Card, Field, inputCls, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Props = {
    navigation: any;
    roleLabel: string;
    me: { name: string; photo: string | null; email: string; phone: string | null; role: string; employee_no: string | null; designation: string | null; department: string | null; joined_on: string | null; address: string | null };
    attendance: { rows: { date: string; status: string; note: string | null }[]; month: Record<string, number> };
    leave: { types: { name: string; allowed: number; used: number; balance: number; paid: boolean }[]; requests: { id: number; leave_type: string; start_date: string; end_date: string; days: number; reason: string; status: string }[] };
    pay: { basic: number; components: { name: string; kind: string; calc: string; value: number }[]; payslips: { id: number; month: string; paid_on: string; basic: number; earnings: number; deductions: number; net: number }[]; currency: string };
};

const tabs = ['profile', 'attendance', 'leave', 'pay'] as const;
const tabLabel = { profile: 'Profile', attendance: 'Attendance', leave: 'Leave', pay: 'Pay' } as const;
const tone = (s: string) => (s === 'present' || s === 'approved' ? 'green' : s === 'late' || s === 'pending' ? 'amber' : s === 'absent' || s === 'rejected' ? 'red' : 'slate');

export default function MyHr({ navigation, roleLabel, me, attendance, leave, pay, userId, files = [], base = '/app/me', readOnly = false }: Props & { userId: number; files?: UserFile[]; base?: string; readOnly?: boolean }) {
    const { t } = useI18n();
    const [tab, setTab] = useUrlTab(tabs);
    const form = useForm({ leave_type: leave.types[0]?.name ?? '', start_date: '', end_date: '', reason: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/me/leave', { preserveScroll: true, onSuccess: () => form.reset('reason', 'start_date', 'end_date') });
    };
    const m = (n: number) => `${pay.currency} ${Number(n).toLocaleString()}`;

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title={readOnly ? `${me.name} — HR` : 'My HR'} subtitle={readOnly ? 'Profile, attendance, leave and salary of this employee.' : 'Your own profile, attendance, leave and salary.'} wide>
            <Card className="flex flex-wrap items-center gap-4">
                <PhotoUpload userId={userId} name={me.name} src={me.photo} className="h-16 w-16 text-xl" />
                <div>
                    <h2 className="text-lg font-semibold">{me.name}</h2>
                    <p className="text-sm text-slate-500">
                        {[me.designation, me.department, me.employee_no].filter(Boolean).join(' · ')}
                    </p>
                </div>
                <div className="ms-auto flex flex-wrap gap-2 text-sm">
                    {Object.entries(attendance.month).map(([k, v]) => (
                        <Badge key={k} tone={tone(k)}>
                            {t(k)} {v}
                        </Badge>
                    ))}
                </div>
            </Card>

            <div className="flex flex-wrap gap-2">
                {tabs.map((x) => (
                    <button key={x} onClick={() => setTab(x)} className={`rounded-full px-4 py-1.5 text-sm font-medium ${tab === x ? 'bg-fuchsia-700 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'}`}>
                        {t(tabLabel[x])}
                    </button>
                ))}
            </div>

            {tab === 'profile' && (
                <Card>
                    <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        {[
                            ['Employee no.', me.employee_no],
                            ['Designation', me.designation],
                            ['Department', me.department],
                            ['Joined', me.joined_on],
                            ['Email', me.email],
                            ['Phone', me.phone],
                            ['Address', me.address],
                        ].map(([k, v]) => (
                            <div key={k as string}>
                                <dt className="text-xs text-slate-400">{t(k as string)}</dt>
                                <dd className="font-medium">{v || '—'}</dd>
                            </div>
                        ))}
                    </dl>
                    <h3 className="mt-6 mb-2 text-sm font-semibold">{t('Documents')}</h3>
                    <UserFiles files={files} />
                </Card>
            )}

            {tab === 'attendance' && (
                <Card>
                    <p className="mb-3 text-sm text-slate-500">{t('Your last 60 days. Ask the office if something is wrong.')}</p>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {attendance.rows.map((a) => (
                            <div key={a.date} className="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2 text-sm">
                                <span>{a.date}</span>
                                <Badge tone={tone(a.status)}>{t(a.status)}</Badge>
                            </div>
                        ))}
                        {attendance.rows.length === 0 && <p className="text-sm text-slate-500">{t('Nothing recorded yet.')}</p>}
                    </div>
                </Card>
            )}

            {tab === 'leave' && (
                <>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {leave.types.map((l) => (
                            <Card key={l.name}>
                                <p className="text-xs text-slate-500">{l.name}</p>
                                <p className="text-2xl font-semibold">{l.balance}</p>
                                <p className="text-xs text-slate-400">
                                    {t('left of')} {l.allowed} · {t('used')} {l.used}
                                </p>
                            </Card>
                        ))}
                    </div>
                    {!readOnly && <Card>
                        <form onSubmit={submit} className="grid gap-4 md:grid-cols-4">
                            <Field label={t('Leave type')} error={form.errors.leave_type}>
                                <select className={inputCls} value={form.data.leave_type} onChange={(e) => form.setData('leave_type', e.target.value)}>
                                    {leave.types.map((l) => (
                                        <option key={l.name}>{l.name}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label={t('From')} error={form.errors.start_date}>
                                <input type="date" className={inputCls} value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                            </Field>
                            <Field label={t('To')} error={form.errors.end_date}>
                                <input type="date" className={inputCls} value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                            </Field>
                            <Field label={t('Reason')} error={form.errors.reason}>
                                <input className={inputCls} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                            </Field>
                            <div className="md:col-span-4">
                                <button className={primaryBtn} disabled={form.processing}>
                                    {t('Request leave')}
                                </button>
                            </div>
                        </form>
                    </Card>}
                    <Card>
                        <ul className="divide-y divide-slate-100 text-sm">
                            {leave.requests.map((r) => (
                                <li key={r.id} className="flex flex-wrap items-center gap-3 py-2">
                                    <span className="font-medium">{r.leave_type}</span>
                                    <span className="text-slate-500">
                                        {r.start_date} → {r.end_date} ({r.days})
                                    </span>
                                    <span className="text-slate-400">{r.reason}</span>
                                    <span className="ms-auto">
                                        <Badge tone={tone(r.status)}>{t(r.status)}</Badge>
                                    </span>
                                </li>
                            ))}
                            {leave.requests.length === 0 && <li className="py-2 text-slate-500">{t('Nothing recorded yet.')}</li>}
                        </ul>
                    </Card>
                </>
            )}

            {tab === 'pay' && (
                <>
                    <Card>
                        <p className="text-xs text-slate-500">{t('Basic salary')}</p>
                        <p className="text-2xl font-semibold">{m(pay.basic)}</p>
                        <ul className="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            {pay.components.map((c) => (
                                <li key={c.name} className="rounded-xl border border-slate-100 px-3 py-2">
                                    <span className={c.kind === 'earning' ? 'text-emerald-700' : 'text-rose-700'}>{c.kind === 'earning' ? '+' : '−'}</span> {c.name}{' '}
                                    <span className="text-slate-400">{c.calc === 'percent' ? `${c.value}%` : m(c.value)}</span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card>
                        <h3 className="mb-2 font-semibold">{t('Payslips')}</h3>
                        <ul className="divide-y divide-slate-100 text-sm">
                            {pay.payslips.map((p) => (
                                <li key={p.id} className="flex flex-wrap items-center gap-3 py-2">
                                    <span className="font-medium">{p.month}</span>
                                    <span className="text-slate-500">
                                        {t('Paid on')} {p.paid_on}
                                    </span>
                                    <span className="ms-auto font-semibold">{m(p.net)}</span>
                                    <Link href={`${base}/payslips/${p.id}`} className="font-medium text-fuchsia-700">
                                        {t('View')}
                                    </Link>
                                </li>
                            ))}
                            {pay.payslips.length === 0 && <li className="py-2 text-slate-500">{t('No payslips yet.')}</li>}
                        </ul>
                    </Card>
                </>
            )}
        </OfficePage>
    );
}
