import { AttachmentList, CommsPage, Person, when, type Shell } from '@/components/comms/ui';
import { Rich, RichEditor } from '@/components/comms/rich-editor';
import { FilePicker } from '@/components/comms/ui';
import { useI18n } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { Archive, ArrowLeft, Loader2, MailOpen, Send } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

type Msg = { id: number; body: string; at: string; mine: boolean; sender: { id: number; name: string; role: string; photo: string | null }; files: { id: number; name: string; size: number; mime: string; url: string }[] };

export default function Conversation({ shell, conversation, people, messages, more, limits }: { shell: Shell; conversation: { id: number; subject: string; archived: boolean }; people: { id: number; name: string; role: string; photo: string | null; me: boolean }[]; messages: Msg[]; more: number | null; limits: { files: number; kb: number; types: string[] } }) {
    const { t } = useI18n();
    const form = useForm<{ body: string; files: File[] }>({ body: '', files: [] });
    const errors = form.errors as Record<string, string>;
    const [earlier, setEarlier] = useState<Msg[]>([]);
    const [cursor, setCursor] = useState<number | null | undefined>(undefined);
    const [loadingMore, setLoadingMore] = useState(false);
    const next = cursor === undefined ? more : cursor;
    const loadEarlier = async () => {
        setLoadingMore(true);
        const before = earlier[0]?.id ?? messages[0]?.id;
        const res = await fetch(`${shell.base}/messages/${conversation.id}/older?before=${before}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (res.ok) {
            const j = await res.json();
            setEarlier((e) => [...j.messages, ...e]);
            setCursor(j.more);
        }
        setLoadingMore(false);
    };

    // New replies from the other side appear without leaving the page.
    useEffect(() => {
        const id = setInterval(() => {
            if (document.visibilityState === 'visible' && !form.isDirty) router.reload({ only: ['messages', 'more', 'people'] });
        }, 15000);

        return () => clearInterval(id);
    }, [form.isDirty]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (form.processing) return;
        form.post(`${shell.base}/messages/${conversation.id}/reply`, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
    };
    const state = (action: string) => router.post(`${shell.base}/messages/${conversation.id}/state`, { action }, { preserveScroll: true });

    return (
        <CommsPage shell={shell} title={conversation.subject}>
            <div className="flex flex-wrap items-center gap-2">
                <Link href={`${shell.base}/messages`} className="inline-flex items-center gap-1 text-sm font-medium text-fuchsia-700"><ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('Back to messages')}</Link>
                <span className="ms-auto flex gap-2">
                    <button onClick={() => state('unread')} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-50"><MailOpen className="h-4 w-4" /> {t('Mark unread')}</button>
                    <button onClick={() => (conversation.archived ? state('unarchive') : confirm(t('Archive this conversation?')) && state('archive'))} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-50"><Archive className="h-4 w-4" /> {conversation.archived ? t('Move to inbox') : t('Archive')}</button>
                </span>
            </div>
            <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                <span>{t('In this conversation')}:</span>
                {people.map((p) => <Person key={p.id} p={p} size="h-6 w-6 text-[10px]" />)}
            </p>

            {next && (
                <button onClick={loadEarlier} disabled={loadingMore} className="mx-auto block rounded-lg border border-slate-200 bg-white px-4 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50">{loadingMore ? t('Loading…') : t('Show earlier messages')}</button>
            )}

            <ol className="space-y-4" aria-live="polite">
                {[...earlier, ...messages].map((m) => (
                    <li key={m.id} className={`flex ${m.mine ? 'justify-end' : ''}`}>
                        <article className={`w-full max-w-3xl rounded-2xl border p-4 shadow-sm ${m.mine ? 'border-fuchsia-200 bg-fuchsia-50/60' : 'border-slate-200 bg-white'}`}>
                            <header className="mb-2 flex flex-wrap items-center gap-2 text-sm">
                                <Person p={m.sender} />
                                <time className="ms-auto text-xs text-slate-400" dateTime={m.at}>{when(m.at)}</time>
                            </header>
                            <Rich html={m.body} className="text-sm" />
                            <AttachmentList files={m.files} />
                        </article>
                    </li>
                ))}
            </ol>

            <form onSubmit={submit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <RichEditor label={t('Your reply')} value={form.data.body} onChange={(v) => form.setData('body', v)} error={errors.body} minHeight={120} />
                <FilePicker files={form.data.files} onChange={(f) => form.setData('files', f)} limits={limits} error={errors['files.0'] ?? errors.files} />
                <button disabled={form.processing || !form.data.body} className="inline-flex items-center gap-2 rounded-xl bg-fuchsia-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-50">
                    {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />} {form.processing ? t('Sending…') : t('Send reply')}
                </button>
            </form>
        </CommsPage>
    );
}
