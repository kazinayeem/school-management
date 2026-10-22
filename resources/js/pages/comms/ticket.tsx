import { AttachmentList, CommsPage, FilePicker, Person, PriorityMark, StatusBadge, when, type Shell } from '@/components/comms/ui';
import { Rich, RichEditor } from '@/components/comms/rich-editor';
import { useI18n } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Flame, History, Loader2, Lock, Send } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

type File_ = { id: number; name: string; size: number; mime: string; url: string };
type Props = {
    shell: Shell;
    ticket: { id: number; reference: string; title: string; category: string; priority: string; status: string; description: string; resolution: string | null; created_at: string; last_activity_at: string; resolved_at: string | null; escalated: boolean; recipient_role: string; requester: { id: number; name: string; role: string; photo: string | null } | null; assignee: { id: number; name: string; role: string } | null; student: { id: number; name: string; class: string } | null; teacher: { id: number; name: string } | null; files: File_[] };
    replies: { id: number; body: string; internal: boolean; at: string; mine: boolean; author: { id: number; name: string; role: string; photo: string | null }; files: File_[] }[];
    events: { id: number; type: string; from: string | null; to: string | null; note: string | null; who: string | null; at: string }[];
    can: { manage: boolean; assign: boolean; internal: boolean; reply: boolean; transitions: string[]; priority: boolean };
    meta: { categories: Record<string, string>; priorities: Record<string, string>; statuses: Record<string, string> };
    assignees: { id: number; name: string; role: string }[];
    limits: { files: number; kb: number; types: string[] };
};

export default function Ticket({ shell, ticket, replies, events, can, meta, assignees, limits }: Props) {
    const { t } = useI18n();
    const reply = useForm<{ body: string; internal: boolean; files: File[] }>({ body: '', internal: false, files: [] });
    const errors = reply.errors as Record<string, string>;
    const [resolution, setResolution] = useState(ticket.resolution ?? '');
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [resolveError, setResolveError] = useState('');

    useEffect(() => {
        const id = setInterval(() => {
            if (document.visibilityState === 'visible' && !reply.isDirty) router.reload({ only: ['ticket', 'replies', 'events', 'can'] });
        }, 20000);

        return () => clearInterval(id);
    }, [reply.isDirty]);

    const patch = (data: Record<string, unknown>, after?: () => void) => {
        if (busy) return;
        setBusy(true);
        setResolveError('');
        router.post(`${shell.base}/tickets/${ticket.id}`, data as never, {
            preserveScroll: true,
            onError: (e) => setResolveError(String(e.resolution ?? e.status ?? e.assigned_to ?? Object.values(e)[0] ?? '')),
            onSuccess: after,
            onFinish: () => setBusy(false),
        });
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (reply.processing) return;
        reply.post(`${shell.base}/tickets/${ticket.id}/reply`, { forceFormData: true, preserveScroll: true, onSuccess: () => reply.reset() });
    };
    const eventText = (e: Props['events'][number]) => {
        const label = (v: string | null) => (v ? t(meta.statuses[v] ?? meta.priorities[v] ?? v) : '—');
        switch (e.type) {
            case 'created': return t('Ticket created');
            case 'status': return t('Status: {a} → {b}', { a: label(e.from), b: label(e.to) });
            case 'priority': return t('Priority: {a} → {b}', { a: label(e.from), b: label(e.to) });
            case 'assigned': return e.to ? t('Assigned to {name}', { name: e.to }) : t('Unassigned');
            case 'escalated': return t('Escalated to urgent');
            default: return e.type;
        }
    };
    const closed = ticket.status === 'closed';

    return (
        <CommsPage shell={shell} title={ticket.title}>
            <div className="flex flex-wrap items-center gap-3">
                <Link href={`${shell.base}/tickets`} className="inline-flex items-center gap-1 text-sm font-medium text-fuchsia-700"><ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('All tickets')}</Link>
                <span className="font-mono text-sm text-slate-500">{ticket.reference}</span>
                <StatusBadge status={ticket.status} labels={meta.statuses} />
                <PriorityMark priority={ticket.priority} labels={meta.priorities} />
                {ticket.escalated && <span className="inline-flex items-center gap-1 text-xs font-semibold text-rose-600"><Flame className="h-3.5 w-3.5" /> {t('Escalated')}</span>}
            </div>

            <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                <div className="space-y-5">
                    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <header className="mb-3 flex flex-wrap items-center gap-2 text-sm">
                            {ticket.requester && <Person p={ticket.requester} />}
                            <time className="ms-auto text-xs text-slate-400">{when(ticket.created_at)}</time>
                        </header>
                        <Rich html={ticket.description} className="text-sm" />
                        <AttachmentList files={ticket.files} />
                    </article>

                    {ticket.resolution && (
                        <section className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <h2 className="mb-2 font-semibold text-emerald-800">{t('Resolution')}</h2>
                            <Rich html={ticket.resolution} className="text-sm" />
                        </section>
                    )}

                    <ol className="space-y-4" aria-label={t('Conversation')}>
                        {replies.map((r) => (
                            <li key={r.id}>
                                <article className={`rounded-2xl border p-4 shadow-sm ${r.internal ? 'border-amber-300 bg-amber-50' : r.mine ? 'border-fuchsia-200 bg-fuchsia-50/60' : 'border-slate-200 bg-white'}`}>
                                    <header className="mb-2 flex flex-wrap items-center gap-2 text-sm">
                                        <Person p={r.author} size="h-7 w-7 text-xs" />
                                        {r.internal && <span className="inline-flex items-center gap-1 rounded-full bg-amber-200 px-2 py-0.5 text-[11px] font-bold text-amber-900"><Lock className="h-3 w-3" /> {t('Internal note')}</span>}
                                        <time className="ms-auto text-xs text-slate-400">{when(r.at)}</time>
                                    </header>
                                    <Rich html={r.body} className="text-sm" />
                                    <AttachmentList files={r.files} />
                                </article>
                            </li>
                        ))}
                    </ol>

                    {can.reply && !closed ? (
                        <form onSubmit={submit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <RichEditor label={reply.data.internal ? t('Internal note (only the people handling this ticket see it)') : t('Your reply')} value={reply.data.body} onChange={(v) => reply.setData('body', v)} error={errors.body} minHeight={120} />
                            <FilePicker files={reply.data.files} onChange={(f) => reply.setData('files', f)} limits={limits} error={errors['files.0'] ?? errors.files} />
                            <div className="flex flex-wrap items-center gap-4">
                                <button disabled={reply.processing || !reply.data.body} className="inline-flex items-center gap-2 rounded-xl bg-fuchsia-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-50">
                                    {reply.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />} {reply.data.internal ? t('Add internal note') : t('Send reply')}
                                </button>
                                {can.internal && (
                                    <label className="flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" checked={reply.data.internal} onChange={(e) => reply.setData('internal', e.target.checked)} /> {t('Internal note')}
                                    </label>
                                )}
                            </div>
                        </form>
                    ) : (
                        closed && <p className="rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-500">{t('This ticket is closed.')}</p>
                    )}
                </div>

                <aside className="space-y-4">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm">
                        <h2 className="mb-3 font-semibold">{t('Details')}</h2>
                        <dl className="space-y-2.5">
                            <div><dt className="text-xs text-slate-400">{t('Category')}</dt><dd>{t(meta.categories[ticket.category] ?? ticket.category)}</dd></div>
                            <div><dt className="text-xs text-slate-400">{t('Assigned to')}</dt><dd>{ticket.assignee ? ticket.assignee.name : <span className="text-slate-400">{t('Not assigned yet')}</span>}</dd></div>
                            {ticket.student && <div><dt className="text-xs text-slate-400">{t('About student')}</dt><dd>{ticket.student.name} <span className="text-slate-400">· {ticket.student.class}</span></dd></div>}
                            {ticket.teacher && <div><dt className="text-xs text-slate-400">{t('About teacher')}</dt><dd>{ticket.teacher.name}</dd></div>}
                            <div><dt className="text-xs text-slate-400">{t('Last activity')}</dt><dd>{when(ticket.last_activity_at)}</dd></div>
                        </dl>
                    </section>

                    {(can.transitions.length > 0 || can.assign || can.priority) && (
                        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm">
                            <h2 className="font-semibold">{can.manage ? t('Manage ticket') : t('Actions')}</h2>
                            {can.transitions.length > 0 && (
                                <div>
                                    <span className="mb-1 block text-xs text-slate-500">{t('Change status')}</span>
                                    <div className="flex flex-wrap gap-2">
                                        {can.transitions.map((s) => (
                                            <button key={s} disabled={busy} onClick={() => (s === 'resolved' && can.manage ? undefined : patch({ status: s, note }))} className={`rounded-lg border px-3 py-1.5 text-xs font-semibold hover:bg-slate-50 disabled:opacity-50 ${s === 'resolved' && can.manage ? 'hidden' : 'border-slate-200'}`}>
                                                {t(meta.statuses[s] ?? s)}
                                            </button>
                                        ))}
                                    </div>
                                    {can.manage && can.transitions.includes('resolved') && (
                                        <div className="mt-3 space-y-2">
                                            <RichEditor label={t('Resolution summary')} value={resolution} onChange={setResolution} minHeight={90} />
                                            <button disabled={busy || !resolution} onClick={() => patch({ status: 'resolved', resolution, note })} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">{t('Mark as resolved')}</button>
                                        </div>
                                    )}
                                    <input value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} placeholder={t('Note for the history (optional)')} className="mt-3 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs" />
                                    {resolveError && <p role="alert" className="mt-1 text-xs text-rose-600">{resolveError}</p>}
                                </div>
                            )}
                            {can.priority && (
                                <label className="block">
                                    <span className="mb-1 block text-xs text-slate-500">{t('Priority')}</span>
                                    <select className="w-full rounded-lg border border-slate-200 px-3 py-1.5" value={ticket.priority} onChange={(e) => patch({ priority: e.target.value })}>
                                        {Object.entries(meta.priorities).map(([k, v]) => <option key={k} value={k}>{t(v)}</option>)}
                                    </select>
                                </label>
                            )}
                            {can.assign && (
                                <label className="block">
                                    <span className="mb-1 block text-xs text-slate-500">{t('Assign to')}</span>
                                    <select className="w-full rounded-lg border border-slate-200 px-3 py-1.5" value={ticket.assignee?.id ?? ''} onChange={(e) => (e.target.value ? patch({ assigned_to: e.target.value }) : patch({ unassign: true }))}>
                                        <option value="">{t('Unassigned')}</option>
                                        {assignees.map((a) => <option key={a.id} value={a.id}>{a.name} ({t(a.role === 'admin' ? 'Admin' : a.role === 'teacher' ? 'Teacher' : 'Staff')})</option>)}
                                    </select>
                                </label>
                            )}
                            {can.manage && !ticket.escalated && !closed && (
                                <button disabled={busy} onClick={() => confirm(t('Escalate this ticket to urgent and tell the administrators?')) && patch({ escalate: true, note })} className="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-600"><Flame className="h-3.5 w-3.5" /> {t('Escalate')}</button>
                            )}
                        </section>
                    )}

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm">
                        <h2 className="mb-3 flex items-center gap-2 font-semibold"><History className="h-4 w-4" /> {t('History')}</h2>
                        <ol className="space-y-2.5 border-s border-slate-200 ps-4">
                            {events.map((e) => (
                                <li key={e.id} className="relative">
                                    <span className="absolute -start-[21px] top-1.5 h-2 w-2 rounded-full bg-fuchsia-600" />
                                    <p>{eventText(e)}</p>
                                    {e.note && <p className="text-xs text-slate-500">“{e.note}”</p>}
                                    <p className="text-xs text-slate-400">{e.who} · {when(e.at)}</p>
                                </li>
                            ))}
                        </ol>
                    </section>
                </aside>
            </div>
        </CommsPage>
    );
}
