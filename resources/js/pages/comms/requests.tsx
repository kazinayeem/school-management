import { CommsPage, type Shell } from '@/components/comms/ui';
import { useI18n } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Row = { id: number; reference: string; student_id: number; student: string; kind: string; purpose: string | null; needed_by: string | null; status: string; note: string | null; created_at: string; decided_at: string | null; download: string | null; class?: string; admission_no?: string; requester?: string };
const tone: Record<string, string> = { pending: 'bg-amber-100 text-amber-700', approved: 'bg-emerald-100 text-emerald-700', rejected: 'bg-rose-100 text-rose-700', cancelled: 'bg-slate-200 text-slate-600' };
const field = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-fuchsia-500';

function Badge({ s }: { s: string }) {
    const { t } = useI18n();

    return <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${tone[s]}`}>{t(s)}</span>;
}

function Apply({ shell, kinds, children }: { shell: Shell; kinds: Record<string, string>; children: { id: number; name: string }[] }) {
    const { t } = useI18n();
    const form = useForm({ kind: 'transcript', student_id: String(children[0]?.id ?? ''), purpose: '', needed_by: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`${shell.base}/requests`, { preserveScroll: true, onSuccess: () => form.reset('purpose', 'needed_by') });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2">
            <h2 className="font-semibold md:col-span-2">{t('Apply for a document')}</h2>
            {children.length > 1 && (
                <label className="block"><span className="mb-1 block text-sm font-medium">{t('Student')}</span>
                    <select className={field} value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>{children.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>
                </label>
            )}
            <label className="block"><span className="mb-1 block text-sm font-medium">{t('Document')}</span>
                <select className={field} value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>{Object.entries(kinds).map(([k, v]) => <option key={k} value={k}>{t(v)}</option>)}</select>
                {form.errors.kind && <span className="mt-1 block text-xs text-rose-600">{form.errors.kind}</span>}
            </label>
            <label className="block"><span className="mb-1 block text-sm font-medium">{t('Needed by (optional)')}</span>
                <input type="date" className={field} value={form.data.needed_by} onChange={(e) => form.setData('needed_by', e.target.value)} />
                {form.errors.needed_by && <span className="mt-1 block text-xs text-rose-600">{form.errors.needed_by}</span>}
            </label>
            <label className="block md:col-span-2"><span className="mb-1 block text-sm font-medium">{t('Why do you need it?')}</span>
                <input className={field} maxLength={300} value={form.data.purpose} onChange={(e) => form.setData('purpose', e.target.value)} placeholder={t('For example: admission to another school')} />
                {form.errors.purpose && <span className="mt-1 block text-xs text-rose-600">{form.errors.purpose}</span>}
            </label>
            <div className="md:col-span-2"><button disabled={form.processing} className="rounded-xl bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">{t('Send request')}</button></div>
        </form>
    );
}

function Decide({ row, templates, templateByKind }: { row: Row; templates: { id: number; name: string }[]; templateByKind: Record<string, number | null> }) {
    const { t } = useI18n();
    const [open, setOpen] = useState<null | 'approve' | 'reject'>(null);
    const form = useForm({ decision: 'approve', note: '', template_id: String(templateByKind[row.kind] ?? ''), date: new Date().toISOString().slice(0, 10) });
    const send = (decision: 'approve' | 'reject') => (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, decision }));
        form.post(`/app/certificates/requests/${row.id}`, { preserveScroll: true, onSuccess: () => setOpen(null) });
    };

    return (
        <div className="mt-3">
            {!open && (
                <div className="flex gap-2">
                    <button onClick={() => setOpen('approve')} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white">{t('Approve')}</button>
                    <button onClick={() => setOpen('reject')} className="rounded-lg border border-rose-300 px-3 py-1.5 text-sm font-semibold text-rose-600">{t('Decline')}</button>
                </div>
            )}
            {open && (
                <form onSubmit={send(open)} className="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
                    {open === 'approve' && row.kind !== 'transcript' && (
                        <>
                            <label className="block text-sm"><span className="mb-1 block font-medium">{t('Certificate template')}</span>
                                <select className={field} value={form.data.template_id} onChange={(e) => form.setData('template_id', e.target.value)}><option value="">{t('Choose…')}</option>{templates.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select>
                                {form.errors.template_id && <span className="mt-1 block text-xs text-rose-600">{form.errors.template_id}</span>}
                            </label>
                            <label className="block text-sm"><span className="mb-1 block font-medium">{t('Issue date')}</span><input type="date" className={field} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} /></label>
                        </>
                    )}
                    <label className="block text-sm sm:col-span-2"><span className="mb-1 block font-medium">{open === 'reject' ? t('Reason (shown to the family)') : t('Note (optional)')}</span>
                        <input className={field} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                        {form.errors.note && <span className="mt-1 block text-xs text-rose-600">{form.errors.note}</span>}
                    </label>
                    <div className="flex gap-2 sm:col-span-2">
                        <button disabled={form.processing} className="rounded-lg bg-fuchsia-700 px-4 py-1.5 text-sm font-semibold text-white">{open === 'approve' ? t('Approve and issue') : t('Decline request')}</button>
                        <button type="button" onClick={() => setOpen(null)} className="rounded-lg border border-slate-300 px-4 py-1.5 text-sm">{t('Cancel')}</button>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function Requests({ shell, kinds, children, rows, office, status = 'pending', counts = {}, q = '', templates = [], templateByKind = {} }: { shell: Shell; kinds: Record<string, string>; children: { id: number; name: string }[]; rows: Row[]; office: boolean; status?: string; counts?: Record<string, number>; q?: string; templates?: { id: number; name: string }[]; templateByKind?: Record<string, number | null> }) {
    const { t } = useI18n();
    const [search, setSearch] = useState(q);

    return (
        <CommsPage shell={shell} title={office ? 'Document requests' : 'Certificate requests'} subtitle={office ? 'Approve or decline requests for transcripts and certificates.' : 'Ask the school office for a transcript or certificate. Approved documents can be downloaded here.'}>
            {!office && <Apply shell={shell} kinds={kinds} children={children} />}
            {office && (
                <div className="flex flex-wrap items-center gap-2">
                    {['pending', 'approved', 'rejected', 'cancelled'].map((s) => (
                        <Link key={s} href={`/app/certificates/requests?status=${s}`} className={`rounded-full px-3 py-1.5 text-sm font-medium capitalize ${status === s ? 'bg-fuchsia-700 text-white' : 'bg-white ring-1 ring-slate-200'}`}>{t(s)} {counts[s] ? `(${counts[s]})` : ''}</Link>
                    ))}
                    <form className="ms-auto" onSubmit={(e) => { e.preventDefault(); router.get('/app/certificates/requests', { status, q: search }, { preserveState: true }); }}>
                        <input className={`${field} w-56`} value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('Search name, ID or reference')} />
                    </form>
                </div>
            )}
            <div className="space-y-3">
                {rows.length === 0 && <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm">{t('No requests yet.')}</p>}
                {rows.map((r) => (
                    <article key={r.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 className="font-semibold">{t(kinds[r.kind] ?? r.kind)} <span className="ms-1 text-xs font-normal text-slate-400">{r.reference}</span></h3>
                                <p className="text-sm text-slate-500">{r.student}{r.class ? ` · ${r.class}` : ''}{r.admission_no ? ` · ${r.admission_no}` : ''} · {r.created_at}{r.requester ? ` · ${t('by')} ${r.requester}` : ''}</p>
                            </div>
                            <Badge s={r.status} />
                        </div>
                        {r.purpose && <p className="mt-2 text-sm">{r.purpose}{r.needed_by ? ` · ${t('needed by')} ${r.needed_by}` : ''}</p>}
                        {r.note && <p className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{r.note}</p>}
                        {r.download && <a href={r.download} className="mt-3 inline-flex items-center gap-2 rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white"><Download className="h-4 w-4" />{t('Download PDF')}</a>}
                        {!office && r.status === 'pending' && <button onClick={() => router.post(`${shell.base}/requests/${r.id}/cancel`, {}, { preserveScroll: true })} className="mt-3 ms-2 text-sm text-slate-500 underline">{t('Cancel request')}</button>}
                        {office && r.status === 'pending' && <Decide row={r} templates={templates} templateByKind={templateByKind} />}
                    </article>
                ))}
            </div>
        </CommsPage>
    );
}
