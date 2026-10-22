import { CommsPage, RoleChip, Tabs, when, type Shell } from '@/components/comms/ui';
import { Avatar } from '@/components/avatar';
import { Pagination, useListNav, useNavigating, type PaginationMeta } from '@/components/list-kit';
import { useI18n } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/react';
import { Archive, Inbox, PenSquare, Search, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Row = { id: number; subject: string; unread: number; at: string; archived: boolean; mine: boolean; people: { id: number; name: string; role: string; photo: string | null }[]; preview: string; last_sender: 'me' | 'them' };

export default function Messages({ shell, box, rows, filters, pagination, counts }: { shell: Shell; box: string; rows: Row[]; filters: { box: string; q: string; limit: number }; pagination: PaginationMeta; counts: { unread: number } }) {
    const { t } = useI18n();
    const go = useListNav(filters);
    const busy = useNavigating();
    const [q, setQ] = useState(filters.q);
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }
        const id = setTimeout(() => q !== filters.q && go({ q }), 350);

        return () => clearTimeout(id);
    }, [q]);
    void usePage;

    const base = shell.base;
    const empty = { inbox: 'Your inbox is empty.', sent: 'You have not started any conversations yet.', archived: 'Nothing is archived.' }[box] ?? '';

    return (
        <CommsPage shell={shell} title="Messages" subtitle="Private conversations with teachers, parents, students and the school office.">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Tabs
                    active={box}
                    items={[
                        { key: 'inbox', label: 'Inbox', href: `${base}/messages`, count: counts.unread },
                        { key: 'sent', label: 'Sent', href: `${base}/messages?box=sent` },
                        { key: 'archived', label: 'Archived', href: `${base}/messages?box=archived` },
                    ]}
                />
                <Link href={`${base}/messages/compose`} className="inline-flex items-center gap-2 rounded-xl bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800">
                    <PenSquare className="h-4 w-4" /> {t('New message')}
                </Link>
            </div>

            <label className="relative block max-w-md">
                <Search className="pointer-events-none absolute start-3 top-2.5 h-4 w-4 text-slate-400" />
                <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Search subject, message or person')} aria-label={t('Search messages')} className="w-full rounded-xl border border-slate-200 bg-white py-2 ps-9 pe-3 text-sm outline-none focus:border-fuchsia-500" />
            </label>

            <div className={`overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity ${busy ? 'opacity-60' : ''}`} aria-busy={busy}>
                {rows.length === 0 ? (
                    <div className="flex flex-col items-center px-6 py-16 text-center text-slate-400">
                        {box === 'sent' ? <Send className="mb-3 h-8 w-8" /> : box === 'archived' ? <Archive className="mb-3 h-8 w-8" /> : <Inbox className="mb-3 h-8 w-8" />}
                        <p>{filters.q ? t('No conversations match your search.') : t(empty)}</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((r) => (
                            <li key={r.id}>
                                <Link href={`${base}/messages/${r.id}`} className={`flex items-start gap-3 px-4 py-3.5 transition hover:bg-slate-50 ${r.unread ? 'bg-fuchsia-50/50' : ''}`}>
                                    <Avatar name={r.people[0]?.name ?? '?'} src={r.people[0]?.photo} className="mt-0.5 h-10 w-10 text-sm" />
                                    <span className="min-w-0 flex-1">
                                        <span className="flex flex-wrap items-center gap-x-2">
                                            <span className={`truncate ${r.unread ? 'font-bold' : 'font-medium'}`}>{r.people.map((p) => p.name).join(', ') || t('Only you')}</span>
                                            {r.people.length === 1 && <RoleChip role={r.people[0].role} />}
                                            <span className="ms-auto text-xs text-slate-400">{when(r.at)}</span>
                                        </span>
                                        <span className={`block truncate text-sm ${r.unread ? 'font-semibold text-slate-900' : 'text-slate-700'}`}>{r.subject}</span>
                                        <span className="block truncate text-xs text-slate-500">
                                            {r.last_sender === 'me' && <span className="text-slate-400">{t('You')}: </span>}
                                            {r.preview}
                                        </span>
                                    </span>
                                    {r.unread > 0 && <span className="mt-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-fuchsia-700 px-1.5 text-[11px] font-bold text-white">{r.unread}</span>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <Pagination meta={pagination} label="conversations" onPage={(page) => go({ page })} onLimit={(limit) => go({ limit, page: 1 })} />
        </CommsPage>
    );
}
