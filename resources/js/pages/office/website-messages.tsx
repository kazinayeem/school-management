import { Badge, inputCls, Nav, OfficePage } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Mail, Phone, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Message = { id: number; name: string; email: string; phone: string | null; message: string; status: string; created_at: string };
const tone = { new: 'amber', read: 'slate', replied: 'green' } as const;
const label = { new: 'New', read: 'Read', replied: 'Replied' } as const;

export default function WebsiteMessages({
    navigation,
    roleLabel,
    rows,
    counts,
    filters,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Message[];
    counts: Record<string, number>;
    filters: { status: string; search: string };
}) {
    const { t } = useI18n();
    const [q, setQ] = useState(filters.search);
    const [open, setOpen] = useState<number | null>(null);
    const go = (next: Record<string, string>) => router.get('/app/website/messages', { ...filters, ...next }, { preserveState: true, replace: true });
    const mark = (id: number, status: string) => router.put(`/app/website/messages/${id}`, { status }, { preserveScroll: true });

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Messages" subtitle="Enquiries sent from the website's contact form." wide>
            <div className="grid gap-3 sm:grid-cols-3">
                {(['new', 'read', 'replied'] as const).map((s) => (
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
                    go({ search: q });
                }}
            >
                <input
                    className={`${inputCls} max-w-sm`}
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={t('Search name, email, phone or message')}
                />
            </form>

            {rows.length === 0 ? (
                <p className="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center text-sm text-slate-400">
                    {t('No messages found.')}
                </p>
            ) : (
                <ul className="space-y-3">
                    {rows.map((m) => (
                        <li
                            key={m.id}
                            className={`rounded-2xl border bg-white p-4 shadow-sm ${m.status === 'new' ? 'border-amber-300' : 'border-slate-200'}`}
                        >
                            <div className="flex flex-wrap items-center gap-3">
                                <button
                                    className="text-start font-semibold"
                                    onClick={() => {
                                        setOpen(open === m.id ? null : m.id);
                                        if (m.status === 'new') mark(m.id, 'read');
                                    }}
                                >
                                    {m.name}
                                </button>
                                <Badge tone={tone[m.status as keyof typeof tone] ?? 'slate'}>
                                    {t(label[m.status as keyof typeof label] ?? m.status)}
                                </Badge>
                                <span className="text-xs text-slate-400">{m.created_at.slice(0, 16)}</span>
                                <button
                                    aria-label={t('Delete')}
                                    className="ms-auto text-slate-400 hover:text-red-600"
                                    onClick={() =>
                                        confirm(t('Delete this entry?')) && router.delete(`/app/website/messages/${m.id}`, { preserveScroll: true })
                                    }
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                            <p className={`mt-2 text-sm text-slate-600 ${open === m.id ? 'whitespace-pre-line' : 'line-clamp-2'}`}>{m.message}</p>
                            {open === m.id && (
                                <div className="mt-3 flex flex-wrap items-center gap-4 border-t pt-3 text-sm">
                                    <a href={`mailto:${m.email}`} className="flex items-center gap-1 text-fuchsia-700" dir="ltr">
                                        <Mail className="h-4 w-4" /> {m.email}
                                    </a>
                                    {m.phone && (
                                        <a href={`tel:${m.phone}`} className="flex items-center gap-1 text-fuchsia-700" dir="ltr">
                                            <Phone className="h-4 w-4" /> {m.phone}
                                        </a>
                                    )}
                                    <div className="ms-auto flex gap-2">
                                        {m.status !== 'replied' && (
                                            <button
                                                onClick={() => mark(m.id, 'replied')}
                                                className="rounded-lg bg-fuchsia-700 px-3 py-1 text-xs font-semibold text-white"
                                            >
                                                {t('Mark replied')}
                                            </button>
                                        )}
                                        {m.status !== 'new' && (
                                            <button
                                                onClick={() => mark(m.id, 'new')}
                                                className="rounded-lg border px-3 py-1 text-xs hover:bg-slate-50"
                                            >
                                                {t('Mark unread')}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </OfficePage>
    );
}
