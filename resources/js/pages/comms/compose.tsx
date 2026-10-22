import { CommsPage, FilePicker, RoleChip, type Shell } from '@/components/comms/ui';
import { RichEditor } from '@/components/comms/rich-editor';
import { Avatar } from '@/components/avatar';
import { useI18n } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { Loader2, Send, X } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';

type Person = { id: number; name: string; role: string; photo?: string | null; detail?: string };

export default function Compose({ shell, roles, preset, subject, limits }: { shell: Shell; roles: { value: string; label: string }[]; preset: Person | null; subject: string; limits: { files: number; kb: number; types: string[] } }) {
    const { t } = useI18n();
    const form = useForm<{ subject: string; body: string; recipients: number[]; files: File[] }>({ subject, body: '', recipients: preset ? [preset.id] : [], files: [] });
    const [chosen, setChosen] = useState<Person[]>(preset ? [preset] : []);
    const [role, setRole] = useState(preset?.role ?? roles[0]?.value ?? 'admin');
    const [q, setQ] = useState('');
    const [found, setFound] = useState<Person[]>([]);
    const [searching, setSearching] = useState(false);
    const [error, setError] = useState('');
    const errors = form.errors as Record<string, string>;
    const seq = useRef(0);

    // Look people up as the sender types, ignoring answers that arrive late.
    useEffect(() => {
        const mine = ++seq.current;
        const id = setTimeout(async () => {
            setSearching(true);
            setError('');
            try {
                const res = await fetch(`${shell.base}/messages/recipients?role=${encodeURIComponent(role)}&q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) throw new Error();
                const json = await res.json();
                if (mine === seq.current) setFound(json.data);
            } catch {
                mine === seq.current && setError(t('Could not load the list. Try again.'));
            } finally {
                mine === seq.current && setSearching(false);
            }
        }, 250);

        return () => clearTimeout(id);
    }, [role, q]);

    const add = (p: Person) => {
        if (chosen.some((c) => c.id === p.id)) return;
        const next = [...chosen, p];
        setChosen(next);
        form.setData('recipients', next.map((c) => c.id));
    };
    const remove = (id: number) => {
        const next = chosen.filter((c) => c.id !== id);
        setChosen(next);
        form.setData('recipients', next.map((c) => c.id));
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (form.processing) return;
        form.post(`${shell.base}/messages`, { forceFormData: true, preserveScroll: true });
    };

    return (
        <CommsPage shell={shell} title="New message" subtitle="Choose who it is for, write your message, and send.">
            <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <span className="mb-1 block text-sm font-medium">{t('To')}</span>
                        <div className="mb-2 flex flex-wrap gap-2" role="tablist" aria-label={t('Recipient type')}>
                            {roles.map((r) => (
                                <button key={r.value} type="button" role="tab" aria-selected={role === r.value} onClick={() => { setRole(r.value); setQ(''); }} className={`rounded-full border px-3 py-1 text-xs font-semibold transition ${role === r.value ? 'border-fuchsia-600 bg-fuchsia-50 text-fuchsia-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'}`}>
                                    {t(r.label)}
                                </button>
                            ))}
                        </div>
                        <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Search by name, email or ID')} aria-label={t('Search people')} className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-fuchsia-500" />
                        <ul className="mt-2 max-h-52 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200" aria-busy={searching}>
                            {searching && found.length === 0 && <li className="flex items-center gap-2 px-3 py-3 text-sm text-slate-400"><Loader2 className="h-4 w-4 animate-spin" /> {t('Searching…')}</li>}
                            {!searching && found.length === 0 && <li className="px-3 py-3 text-sm text-slate-400">{error || t('Nobody found. You can only write to people connected to you.')}</li>}
                            {found.map((p) => (
                                <li key={p.id}>
                                    <button type="button" onClick={() => add(p)} disabled={chosen.some((c) => c.id === p.id)} className="flex w-full items-center gap-3 px-3 py-2 text-start text-sm hover:bg-fuchsia-50 disabled:opacity-40">
                                        <Avatar name={p.name} src={p.photo} className="h-8 w-8 text-xs" />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">{p.name}</span>
                                            <span className="block truncate text-xs text-slate-400">{p.detail}</span>
                                        </span>
                                        <span className="text-xs font-semibold text-fuchsia-700">{chosen.some((c) => c.id === p.id) ? t('Added') : t('Add')}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <span className="mb-1 block text-sm font-medium">{t('Sending to')}</span>
                        <div className="min-h-[88px] rounded-xl border border-dashed border-slate-300 p-2">
                            {chosen.length === 0 ? <p className="p-2 text-sm text-slate-400">{t('Nobody chosen yet.')}</p> : (
                                <ul className="flex flex-wrap gap-2">
                                    {chosen.map((c) => (
                                        <li key={c.id} className="flex items-center gap-2 rounded-full bg-fuchsia-50 py-1 ps-2 pe-1 text-sm">
                                            <span className="font-medium">{c.name}</span>
                                            <RoleChip role={c.role} />
                                            <button type="button" aria-label={t('Remove {name}', { name: c.name })} onClick={() => remove(c.id)} className="rounded-full p-0.5 hover:bg-fuchsia-100"><X className="h-3.5 w-3.5" /></button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        {errors.recipients && <p role="alert" className="mt-1 text-xs text-rose-600">{errors.recipients}</p>}
                    </div>
                </div>

                <label className="block">
                    <span className="mb-1 block text-sm font-medium">{t('Subject')}</span>
                    <input value={form.data.subject} maxLength={200} onChange={(e) => form.setData('subject', e.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-fuchsia-500" />
                    {errors.subject && <span role="alert" className="mt-1 block text-xs text-rose-600">{errors.subject}</span>}
                </label>

                <RichEditor label={t('Message')} value={form.data.body} onChange={(v) => form.setData('body', v)} error={errors.body} minHeight={200} />
                <FilePicker files={form.data.files} onChange={(f) => form.setData('files', f)} limits={limits} error={errors['files.0'] ?? errors.files} />

                <div className="flex flex-wrap gap-3">
                    <button disabled={form.processing || chosen.length === 0 || !form.data.subject.trim()} className="inline-flex items-center gap-2 rounded-xl bg-fuchsia-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-50">
                        {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />} {form.processing ? t('Sending…') : t('Send message')}
                    </button>
                    <Link href={`${shell.base}/messages`} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm hover:bg-slate-50">
                        {t('Cancel')}
                    </Link>
                </div>
            </form>
        </CommsPage>
    );
}
