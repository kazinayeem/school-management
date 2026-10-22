import { CommsPage, PriorityMark, StatusBadge, Tabs, when, type Shell } from '@/components/comms/ui';
import { StatCard } from '@/components/charts/charts';
import { Pagination, SortHeader, useListNav, useNavigating, type PaginationMeta } from '@/components/list-kit';
import { useI18n } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { LifeBuoy, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Row = { id: number; reference: string; title: string; category: string; priority: string; status: string; created_at: string; last_activity_at: string; requester: string; assignee: string | null };
type Meta = { categories: Record<string, string>; priorities: Record<string, string>; statuses: Record<string, string> };
type Filters = { q: string; status: string; priority: string; category: string; assigned: string; view: string; from: string; to: string; limit: number; sort: string; dir: string };

export default function Tickets({ shell, rows, filters, pagination, stats, admin, staffSide, meta, assignees }: { shell: Shell; rows: Row[]; filters: Filters; pagination: PaginationMeta; stats: Record<string, number | null>; admin: boolean; staffSide: boolean; meta: Meta; assignees: { id: number; name: string; role: string }[] }) {
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
    const base = shell.base;
    const sel = 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm';
    const sortMeta = { ...pagination, sort: filters.sort, dir: filters.dir };

    return (
        <CommsPage shell={shell} title={admin ? 'Support tickets' : 'My tickets'} subtitle={admin ? 'Every complaint and request across the school: assign, prioritise and resolve.' : 'Complaints and requests you raised or were asked to handle.'}>
            {admin ? (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-6">
                    <StatCard label="Total" value={stats.total ?? 0} />
                    <StatCard label="Open" value={stats.open ?? 0} tone="pink" />
                    <StatCard label="Unassigned" value={stats.unassigned ?? 0} tone={(stats.unassigned ?? 0) > 0 ? 'amber' : 'slate'} />
                    <StatCard label="High & urgent" value={stats.urgent ?? 0} tone={(stats.urgent ?? 0) > 0 ? 'red' : 'slate'} />
                    <StatCard label="Waiting for a reply" value={stats.waiting ?? 0} />
                    <StatCard label="Resolved" value={stats.resolved ?? 0} tone="green" note={stats.avg_hours != null ? t('Average {n} h to resolve', { n: Math.round(Number(stats.avg_hours)) }) : undefined} />
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <StatCard label="Raised by me" value={stats.created ?? 0} />
                    <StatCard label="Still open" value={stats.open ?? 0} tone="pink" />
                    {staffSide && <StatCard label="Assigned to me" value={stats.assigned ?? 0} tone={(stats.assigned ?? 0) > 0 ? 'amber' : 'slate'} />}
                    <StatCard label="Waiting for my reply" value={stats.awaiting_me ?? 0} tone={(stats.awaiting_me ?? 0) > 0 ? 'amber' : 'slate'} />
                    <StatCard label="Resolved" value={stats.resolved ?? 0} tone="green" />
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                {(admin || staffSide) ? (
                    <Tabs active={filters.view} items={[...(admin ? [{ key: 'all', label: 'All tickets', href: `${base}/tickets?view=all` }] : []), { key: 'mine', label: 'Raised by me', href: `${base}/tickets?view=mine` }, ...(staffSide ? [{ key: 'assigned', label: 'Assigned to me', href: `${base}/tickets?view=assigned` }] : [])]} />
                ) : <span />}
                <Link href={`${base}/tickets/create`} className="inline-flex items-center gap-2 rounded-xl bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800"><Plus className="h-4 w-4" /> {t('New ticket')}</Link>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <label className="relative">
                    <Search className="pointer-events-none absolute start-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Search title, reference or person')} aria-label={t('Search tickets')} className={`${sel} w-72 ps-9`} />
                </label>
                <select aria-label={t('Status')} className={sel} value={filters.status} onChange={(e) => go({ status: e.target.value })}>
                    <option value="">{t('Any status')}</option>
                    <option value="open">{t('Not finished')}</option>
                    {Object.entries(meta.statuses).map(([k, v]) => <option key={k} value={k}>{t(v)}</option>)}
                </select>
                <select aria-label={t('Priority')} className={sel} value={filters.priority} onChange={(e) => go({ priority: e.target.value })}>
                    <option value="">{t('Any priority')}</option>
                    {Object.entries(meta.priorities).map(([k, v]) => <option key={k} value={k}>{t(v)}</option>)}
                </select>
                <select aria-label={t('Category')} className={sel} value={filters.category} onChange={(e) => go({ category: e.target.value })}>
                    <option value="">{t('Any category')}</option>
                    {Object.entries(meta.categories).map(([k, v]) => <option key={k} value={k}>{t(v)}</option>)}
                </select>
                {admin && (
                    <select aria-label={t('Assigned to')} className={sel} value={filters.assigned} onChange={(e) => go({ assigned: e.target.value })}>
                        <option value="">{t('Anyone')}</option>
                        <option value="none">{t('Unassigned')}</option>
                        {assignees.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                )}
                <input type="date" aria-label={t('From')} className={sel} value={filters.from} onChange={(e) => go({ from: e.target.value })} />
                <input type="date" aria-label={t('To')} className={sel} value={filters.to} onChange={(e) => go({ to: e.target.value })} />
            </div>

            <div className={`overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity ${busy ? 'opacity-60' : ''}`} aria-busy={busy}>
                {rows.length === 0 ? (
                    <div className="flex flex-col items-center px-6 py-16 text-center text-slate-400">
                        <LifeBuoy className="mb-3 h-8 w-8" />
                        <p>{t('No tickets match.')}</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[820px] text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="px-4 py-3 text-start">{t('Ticket')}</th>
                                <th className="px-4 py-3 text-start">{t('Category')}</th>
                                <SortHeader field="priority" label="Priority" meta={sortMeta} onSort={(f, d) => go({ sort: f, dir: d, page: 1 })} className="px-4 py-3 text-start" />
                                <SortHeader field="status" label="Status" meta={sortMeta} onSort={(f, d) => go({ sort: f, dir: d, page: 1 })} className="px-4 py-3 text-start" />
                                <th className="px-4 py-3 text-start">{t('Assigned to')}</th>
                                <SortHeader field="activity" label="Updated" meta={sortMeta} onSort={(f, d) => go({ sort: f, dir: d, page: 1 })} className="px-4 py-3 text-start" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((r) => (
                                <tr key={r.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">
                                        <Link href={`${base}/tickets/${r.id}`} className="font-semibold text-slate-900 hover:text-fuchsia-700">{r.title}</Link>
                                        <div className="text-xs text-slate-400"><span className="font-mono">{r.reference}</span> · {r.requester}</div>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{t(meta.categories[r.category] ?? r.category)}</td>
                                    <td className="px-4 py-3"><PriorityMark priority={r.priority} labels={meta.priorities} /></td>
                                    <td className="px-4 py-3"><StatusBadge status={r.status} labels={meta.statuses} /></td>
                                    <td className="px-4 py-3 text-slate-600">{r.assignee ?? <span className="text-slate-400">{t('Unassigned')}</span>}</td>
                                    <td className="px-4 py-3 text-xs text-slate-500">{when(r.last_activity_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
            <Pagination meta={pagination} label="tickets" onPage={(page) => go({ page })} onLimit={(limit) => go({ limit, page: 1 })} />
        </CommsPage>
    );
}
