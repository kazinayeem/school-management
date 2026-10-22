import { CommsPage, when, type Shell } from '@/components/comms/ui';
import { Pagination, useListNav, type PaginationMeta } from '@/components/list-kit';
import { useI18n } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { Bell, CheckCheck, LifeBuoy, Mail } from 'lucide-react';

type Row = { id: number; kind: string; title: string; body: string | null; url: string; read_at: string | null; created_at: string };

export default function Notifications({ shell, rows, filters, pagination }: { shell: Shell; rows: Row[]; filters: { unread: boolean; limit: number }; pagination: PaginationMeta }) {
    const { t } = useI18n();
    const go = useListNav({ unread: filters.unread ? 1 : '', limit: filters.limit });
    const unread = rows.some((r) => !r.read_at);

    return (
        <CommsPage shell={shell} title="Notifications" subtitle="New messages, ticket replies and status changes meant for you.">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={filters.unread} onChange={(e) => go({ unread: e.target.checked ? 1 : '' })} /> {t('Show unread only')}</label>
                <button disabled={!unread} onClick={() => router.post(`${shell.base}/notifications/read-all`, {}, { preserveScroll: true })} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40"><CheckCheck className="h-4 w-4" /> {t('Mark all as read')}</button>
            </div>
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                {rows.length === 0 ? (
                    <div className="flex flex-col items-center px-6 py-16 text-slate-400"><Bell className="mb-3 h-8 w-8" /><p>{t('You are all caught up.')}</p></div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((n) => (
                            <li key={n.id}>
                                <Link href={`${shell.base}/notifications/${n.id}/open`} className={`flex items-start gap-3 px-4 py-3 hover:bg-slate-50 ${n.read_at ? '' : 'bg-fuchsia-50/50'}`}>
                                    <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-fuchsia-100 text-fuchsia-700">{n.kind === 'message' ? <Mail className="h-4 w-4" /> : <LifeBuoy className="h-4 w-4" />}</span>
                                    <span className="min-w-0 flex-1">
                                        <span className={`block truncate text-sm ${n.read_at ? 'font-medium' : 'font-bold'}`}>{n.title}</span>
                                        {n.body && <span className="block truncate text-xs text-slate-500">{n.body}</span>}
                                        <span className="block text-xs text-slate-400">{when(n.created_at)}</span>
                                    </span>
                                    {!n.read_at && <span className="mt-2 h-2 w-2 rounded-full bg-fuchsia-600" aria-label={t('Unread')} />}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <Pagination meta={pagination} label="notifications" onPage={(page) => go({ page })} onLimit={(limit) => go({ limit, page: 1 })} />
        </CommsPage>
    );
}
