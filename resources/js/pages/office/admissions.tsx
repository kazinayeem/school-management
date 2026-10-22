import { FlashStatus } from '@/components/flash';
import { OfficeShell } from '@/components/office-shell';
import { useI18n } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Eye, Search, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Application = {
    id: number;
    tracking_no: string;
    class_name: string;
    first_name: string;
    last_name: string | null;
    gender: string;
    date_of_birth: string;
    guardian_name: string;
    mobile: string;
    status: string;
    admin_note: string | null;
};

const statuses = ['received', 'under_review', 'admitted', 'rejected'];
const label: Record<string, string> = { received: 'Received', under_review: 'Under review', admitted: 'Admitted', rejected: 'Rejected' };
const tone: Record<string, string> = {
    received: 'bg-slate-100 text-slate-600',
    under_review: 'bg-amber-100 text-amber-700',
    admitted: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
};

function Decision({ application, onDone }: { application: Application; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm({ status: 'admitted', section: 'A', admin_note: application.admin_note ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/app/admissions/${application.id}`, { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-4 rounded-2xl border border-fuchsia-200 bg-white p-5 shadow-sm">
            <h2 className="font-semibold">
                {application.first_name} {application.last_name} · {application.tracking_no}
            </h2>
            <dl className="grid gap-2 text-sm text-slate-600 sm:grid-cols-3">
                <div>
                    <dt className="text-xs text-slate-400">{t('Applying for')}</dt>
                    <dd>{t(application.class_name)}</dd>
                </div>
                <div>
                    <dt className="text-xs text-slate-400">{t('Guardian')}</dt>
                    <dd>{application.guardian_name}</dd>
                </div>
                <div>
                    <dt className="text-xs text-slate-400">{t('Mobile')}</dt>
                    <dd dir="ltr" className="text-start">
                        {application.mobile}
                    </dd>
                </div>
            </dl>
            <div className="grid gap-4 sm:grid-cols-3">
                <div>
                    <label className="mb-1 block text-sm font-medium">{t('Decision')}</label>
                    <select
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                        className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    >
                        <option value="under_review">{t('Under review')}</option>
                        <option value="admitted">{t('Admit')}</option>
                        <option value="rejected">{t('Reject')}</option>
                    </select>
                </div>
                {form.data.status === 'admitted' && (
                    <div>
                        <label className="mb-1 block text-sm font-medium">{t('Section')}</label>
                        <select
                            value={form.data.section}
                            onChange={(e) => form.setData('section', e.target.value)}
                            className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        >
                            <option>A</option>
                            <option>B</option>
                        </select>
                    </div>
                )}
                <div className="sm:col-span-3">
                    <label className="mb-1 block text-sm font-medium">{t('Note')}</label>
                    <input
                        value={form.data.admin_note}
                        onChange={(e) => form.setData('admin_note', e.target.value)}
                        className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </div>
            </div>
            {form.errors.status && <p className="text-sm text-red-600">{t(form.errors.status)}</p>}
            <div className="flex gap-3">
                <button
                    disabled={form.processing}
                    className="rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
                >
                    {t('Save decision')}
                </button>
                <button type="button" onClick={onDone} className="rounded-lg border px-5 py-2 text-sm">
                    {t('Cancel')}
                </button>
            </div>
        </form>
    );
}

export default function Admissions({
    navigation,
    roleLabel,
    rows,
    counts,
    filters,
}: {
    navigation: Parameters<typeof OfficeShell>[0]['navigation'];
    roleLabel: string;
    rows: Application[];
    counts: Record<string, number>;
    filters: { status: string; search: string };
}) {
    const { t } = useI18n();
    const [deciding, setDeciding] = useState<Application | null>(null);
    const [search, setSearch] = useState(filters.search);

    const go = (next: Partial<typeof filters>) => router.get('/app/admissions', { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <OfficeShell navigation={navigation} roleLabel={roleLabel}>
            <Head title={t('Applications')} />
            <div className="mx-auto max-w-[1200px] space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{t('Applications')}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">{t('Review online admission applications and admit students.')}</p>
                </div>
                <FlashStatus />

                <div className="grid gap-3 sm:grid-cols-4">
                    {statuses.map((s) => (
                        <button
                            key={s}
                            onClick={() => go({ status: filters.status === s ? '' : s })}
                            className={`rounded-xl border bg-white p-4 text-start shadow-sm ${filters.status === s ? 'border-fuchsia-500' : 'border-slate-200'}`}
                        >
                            <div className="text-2xl font-bold">{counts[s] ?? 0}</div>
                            <div className="text-xs text-slate-500">{t(label[s])}</div>
                        </button>
                    ))}
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        go({ search });
                    }}
                    className="relative max-w-sm"
                >
                    <Search className="absolute start-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('Search name, tracking no. or mobile')}
                        className="w-full rounded-lg border border-slate-200 py-2 ps-9 pe-3 text-sm outline-none focus:border-fuchsia-500"
                    />
                </form>

                {deciding && <Decision application={deciding} onDone={() => setDeciding(null)} />}

                <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {rows.length === 0 ? (
                        <p className="px-6 py-16 text-center text-sm text-slate-400">{t('No applications found.')}</p>
                    ) : (
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                <tr>
                                    {['Tracking no.', 'Applicant', 'Class', 'Guardian', 'Mobile', 'Status'].map((h) => (
                                        <th key={h} className="px-4 py-3 text-start font-semibold">
                                            {t(h)}
                                        </th>
                                    ))}
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3 font-mono text-xs">{r.tracking_no}</td>
                                        <td className="px-4 py-3">
                                            {r.first_name} {r.last_name}
                                        </td>
                                        <td className="px-4 py-3">{t(r.class_name)}</td>
                                        <td className="px-4 py-3">{r.guardian_name}</td>
                                        <td className="px-4 py-3" dir="ltr">
                                            {r.mobile}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs ${tone[r.status] ?? tone.received}`}>
                                                {t(label[r.status] ?? r.status)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-end">
                                            {r.status === 'admitted' ? (
                                                <Check className="inline h-4 w-4 text-green-600" />
                                            ) : r.status === 'rejected' ? (
                                                <X className="inline h-4 w-4 text-red-500" />
                                            ) : (
                                                <button
                                                    aria-label={t('Review')}
                                                    onClick={() => setDeciding(r)}
                                                    className="text-slate-500 hover:text-fuchsia-700"
                                                >
                                                    <Eye className="inline h-4 w-4" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </OfficeShell>
    );
}
