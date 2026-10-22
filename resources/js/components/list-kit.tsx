import { AnimatePresence, motion } from 'motion/react';
import { runExport, type ExportRequest } from '@/lib/export';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { ChevronDown, Download, FileSpreadsheet, FileText, Printer, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

export type PaginationMeta = { page: number; limit: number; total: number; totalPages: number; from: number; to: number; sort?: string; dir?: string };

const LIMITS = [10, 20, 50];

/** True while Inertia is fetching the next page, so tables can dim instead of jumping. */
export function useNavigating(): boolean {
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        const a = router.on('start', () => setBusy(true));
        const b = router.on('finish', () => setBusy(false));

        return () => {
            a();
            b();
        };
    }, []);

    return busy;
}

/** Go to the same list with other filters, page, size or sort. A filter change returns to page 1. */
export function useListNav(filters: Record<string, string | number | null | undefined>) {
    return useCallback(
        (next: Record<string, string | number | null | undefined>) => {
            const merged: Record<string, string | number | null | undefined> = { ...filters, ...next };
            if (!('page' in next)) merged.page = 1;
            const clean = Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== '' && v !== null && v !== undefined));
            router.get(window.location.pathname, clean as Record<string, string | number>, { preserveState: true, preserveScroll: true });
        },
        [filters],
    );
}

function pageList(page: number, last: number): (number | '…')[] {
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const set = new Set([1, 2, page - 1, page, page + 1, last - 1, last].filter((n) => n >= 1 && n <= last));
    const out: (number | '…')[] = [];
    let prev = 0;
    [...set].sort((a, b) => a - b).forEach((n) => {
        if (n - prev > 1) out.push('…');
        out.push(n);
        prev = n;
    });

    return out;
}

export function Pagination({ meta, onPage, onLimit, label = 'records' }: { meta: PaginationMeta; onPage: (p: number) => void; onLimit: (n: number) => void; label?: string }) {
    const { t } = useI18n();
    const btn = 'min-w-9 rounded-lg border px-2.5 py-1.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-40';

    return (
        <nav aria-label={t('Pagination')} className="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p className="text-slate-500" aria-live="polite">
                {meta.total === 0 ? t('No {label} found.', { label: t(label) }) : t('Showing {from}–{to} of {total} {label}', { from: meta.from, to: meta.to, total: meta.total, label: t(label) })}
            </p>
            <div className="flex flex-wrap items-center gap-3">
                <label className="flex items-center gap-2 text-slate-500">
                    {t('Rows per page')}
                    <select className="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-slate-800" value={meta.limit} onChange={(e) => onLimit(Number(e.target.value))}>
                        {LIMITS.map((n) => (
                            <option key={n}>{n}</option>
                        ))}
                    </select>
                </label>
                {meta.totalPages > 1 && (
                    <div className="flex flex-wrap items-center gap-1">
                        <button type="button" className={`${btn} border-slate-200 hover:bg-slate-50`} disabled={meta.page <= 1} onClick={() => onPage(meta.page - 1)}>
                            {t('Previous')}
                        </button>
                        {pageList(meta.page, meta.totalPages).map((n, i) =>
                            n === '…' ? (
                                <span key={`gap${i}`} className="px-1 text-slate-400">
                                    …
                                </span>
                            ) : (
                                <button key={n} type="button" aria-current={n === meta.page ? 'page' : undefined} className={`${btn} ${n === meta.page ? 'border-fuchsia-700 bg-fuchsia-700 text-white' : 'border-slate-200 hover:bg-slate-50'}`} onClick={() => onPage(n)}>
                                    {n}
                                </button>
                            ),
                        )}
                        <button type="button" className={`${btn} border-slate-200 hover:bg-slate-50`} disabled={meta.page >= meta.totalPages} onClick={() => onPage(meta.page + 1)}>
                            {t('Next')}
                        </button>
                    </div>
                )}
            </div>
        </nav>
    );
}

export type Selection = {
    isSelected: (id: number) => boolean;
    toggle: (id: number) => void;
    pageAllSelected: boolean;
    pageSomeSelected: boolean;
    togglePage: () => void;
    selectAllMatching: () => void;
    clear: () => void;
    count: number;
    mode: 'none' | 'ids' | 'all';
    /** What to send the server for "selected" scope. */
    payload: () => { scope: 'selected'; ids: number[] } | { scope: 'all'; exclude: number[] };
};

/**
 * Ticked rows that survive paging. "Select all matching" does not load every row: it
 * remembers the mode and only the rows the user later un-ticks, and the server expands it.
 * The selection is dropped when the filters change.
 */
export function useBulkSelection(pageIds: number[], total: number, resetKey: string): Selection {
    const [mode, setMode] = useState<'none' | 'ids' | 'all'>('none');
    const [ids, setIds] = useState<Set<number>>(new Set());
    const [excluded, setExcluded] = useState<Set<number>>(new Set());
    const lastKey = useRef(resetKey);

    useEffect(() => {
        if (lastKey.current !== resetKey) {
            lastKey.current = resetKey;
            setMode('none');
            setIds(new Set());
            setExcluded(new Set());
        }
    }, [resetKey]);

    const isSelected = (id: number) => (mode === 'all' ? !excluded.has(id) : ids.has(id));
    const toggle = (id: number) => {
        if (mode === 'all') {
            setExcluded((s) => {
                const n = new Set(s);
                n.has(id) ? n.delete(id) : n.add(id);

                return n;
            });
        } else {
            setMode('ids');
            setIds((s) => {
                const n = new Set(s);
                n.has(id) ? n.delete(id) : n.add(id);
                if (n.size === 0) setMode('none');

                return n;
            });
        }
    };
    const pageAllSelected = pageIds.length > 0 && pageIds.every(isSelected);
    const pageSomeSelected = pageIds.some(isSelected);
    const togglePage = () => {
        if (mode === 'all') {
            setExcluded((s) => {
                const n = new Set(s);
                pageIds.forEach((id) => (pageAllSelected ? n.add(id) : n.delete(id)));

                return n;
            });
        } else {
            setMode('ids');
            setIds((s) => {
                const n = new Set(s);
                pageIds.forEach((id) => (pageAllSelected ? n.delete(id) : n.add(id)));
                if (n.size === 0) setMode('none');

                return n;
            });
        }
    };
    const count = mode === 'all' ? Math.max(0, total - excluded.size) : ids.size;

    return {
        isSelected, toggle, pageAllSelected, pageSomeSelected, togglePage, count, mode,
        selectAllMatching: () => {
            setMode('all');
            setExcluded(new Set());
        },
        clear: () => {
            setMode('none');
            setIds(new Set());
            setExcluded(new Set());
        },
        payload: () => (mode === 'all' ? { scope: 'all' as const, exclude: [...excluded] } : { scope: 'selected' as const, ids: [...ids] }),
    };
}

/** The checkbox in a table header, with a mixed state when only some of the page is ticked. */
export function HeaderCheckbox({ sel, label }: { sel: Selection; label: string }) {
    const ref = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (ref.current) ref.current.indeterminate = sel.pageSomeSelected && !sel.pageAllSelected;
    }, [sel.pageSomeSelected, sel.pageAllSelected]);

    return <input ref={ref} type="checkbox" aria-label={label} checked={sel.pageAllSelected} onChange={sel.togglePage} className="h-4 w-4 rounded border-slate-300 accent-fuchsia-700" />;
}

export function RowCheckbox({ sel, id, label }: { sel: Selection; id: number; label: string }) {
    return <input type="checkbox" aria-label={label} checked={sel.isSelected(id)} onChange={() => sel.toggle(id)} className="h-4 w-4 rounded border-slate-300 accent-fuchsia-700" />;
}

/** The banner above a table: what is ticked, and the offer to tick every matching row. */
export function SelectionBanner({ sel, pageCount, total, noun }: { sel: Selection; pageCount: number; total: number; noun: string }) {
    const { t } = useI18n();
    if (sel.count === 0) return null;
    const canOfferAll = sel.mode !== 'all' && sel.pageAllSelected && total > pageCount;

    return (
        <div role="status" className="flex flex-wrap items-center gap-3 rounded-xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-2.5 text-sm text-slate-800">
            <strong>{t('{n} {noun} selected', { n: sel.count.toLocaleString(), noun: t(noun) })}</strong>
            {sel.mode === 'all' && <span className="text-slate-600">{t('across every page that matches the filters')}</span>}
            {canOfferAll && (
                <button type="button" className="font-semibold text-fuchsia-700 underline" onClick={sel.selectAllMatching}>
                    {t('Select all {total} matching {noun}', { total: total.toLocaleString(), noun: t(noun) })}
                </button>
            )}
            <button type="button" className="ms-auto flex items-center gap-1 text-slate-600 hover:text-slate-900" onClick={sel.clear}>
                <X className="h-4 w-4" /> {t('Clear selection')}
            </button>
        </div>
    );
}

/** Export, download PDF and print for a list: the ticked rows, this page, or everything that matches. */
export function ExportMenu({
    dataset,
    columns,
    params,
    sel,
    total,
    noun,
}: {
    dataset: string;
    columns: Record<string, string>;
    params: Record<string, string | number | null | undefined>;
    sel: Selection;
    total: number;
    noun: string;
}) {
    const { t } = useI18n();
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null);
    const [scope, setScope] = useState<'page' | 'selected' | 'all'>('all');
    const [chosen, setChosen] = useState<string[]>(Object.keys(columns));
    const box = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setScope(sel.count > 0 ? 'selected' : 'all');
    }, [sel.count > 0]);
    useEffect(() => {
        const close = (e: MouseEvent) => !box.current?.contains(e.target as Node) && setOpen(false);
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const scopes = useMemo(
        () => [
            ...(sel.count > 0 ? [{ id: 'selected' as const, label: t('Selected ({n})', { n: sel.count.toLocaleString() }) }] : []),
            { id: 'page' as const, label: t('This page only') },
            { id: 'all' as const, label: t('All {total} matching {noun}', { total: total.toLocaleString(), noun: t(noun) }) },
        ],
        [sel.count, total, noun],
    );

    const run = async (format: ExportRequest['format']) => {
        if (busy) return;
        const count = scope === 'selected' ? sel.count : scope === 'page' ? Math.min(Number(params.limit ?? 20), total) : total;
        if (count > 500 && (format === 'pdf' || format === 'html') && !confirm(t('This will build a document with {n} rows. Continue?', { n: count.toLocaleString() }))) return;
        setBusy(true);
        setMessage(null);
        const extra = scope === 'selected' ? sel.payload() : scope === 'all' ? { scope: 'all' as const, exclude: sel.mode === 'all' ? (sel.payload() as { exclude: number[] }).exclude : [] } : { scope: 'page' as const };
        const error = await runExport({ dataset, format, columns: chosen, params, ...(extra as object) } as ExportRequest);
        setBusy(false);
        setMessage(error ? { kind: 'error', text: t(error) } : { kind: 'ok', text: format === 'html' ? t('Sent to the print dialog.') : t('Download started.') });
        if (!error) setTimeout(() => setMessage(null), 3500);
    };

    const item = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm hover:bg-fuchsia-50 disabled:opacity-50';

    return (
        <div className="relative" ref={box}>
            <button type="button" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen(!open)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <Download className="h-4 w-4" /> {t('Export')} <ChevronDown className={`h-4 w-4 transition ${open ? 'rotate-180' : ''}`} />
            </button>
            <AnimatePresence>
            {open && (
                <motion.div initial={{ opacity: 0, y: -4, scale: 0.98 }} animate={{ opacity: 1, y: 0, scale: 1 }} exit={{ opacity: 0, y: -4 }} transition={{ duration: 0.14 }} role="menu" className="absolute end-0 z-30 mt-2 w-80 origin-top-right rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                    <p className="px-1 pb-1 text-xs font-semibold tracking-wide text-slate-400 uppercase">{t('What to export')}</p>
                    {scopes.map((s) => (
                        <label key={s.id} className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                            <input type="radio" name={`scope-${dataset}`} checked={scope === s.id} onChange={() => setScope(s.id)} className="accent-fuchsia-700" /> {s.label}
                        </label>
                    ))}
                    <details className="mt-2 rounded-lg border border-slate-100 px-2 py-1.5 text-sm">
                        <summary className="cursor-pointer text-slate-600">{t('Columns ({n} of {total})', { n: chosen.length, total: Object.keys(columns).length })}</summary>
                        <div className="mt-2 grid max-h-40 grid-cols-2 gap-1 overflow-auto">
                            {Object.entries(columns).map(([k, label]) => (
                                <label key={k} className="flex items-center gap-1.5 text-xs">
                                    <input type="checkbox" className="accent-fuchsia-700" checked={chosen.includes(k)} onChange={(e) => setChosen(e.target.checked ? [...chosen, k] : chosen.filter((c) => c !== k))} /> {t(label)}
                                </label>
                            ))}
                        </div>
                    </details>
                    <div className="mt-2 border-t border-slate-100 pt-2">
                        <button type="button" role="menuitem" disabled={busy || chosen.length === 0} className={item} onClick={() => run('csv')}>
                            <FileText className="h-4 w-4 text-slate-500" /> {t('Export CSV')}
                        </button>
                        <button type="button" role="menuitem" disabled={busy || chosen.length === 0} className={item} onClick={() => run('xlsx')}>
                            <FileSpreadsheet className="h-4 w-4 text-emerald-600" /> {t('Export Excel (.xlsx)')}
                        </button>
                        <button type="button" role="menuitem" disabled={busy || chosen.length === 0} className={item} onClick={() => run('pdf')}>
                            <FileText className="h-4 w-4 text-rose-600" /> {t('Download PDF')}
                        </button>
                        <button type="button" role="menuitem" disabled={busy || chosen.length === 0} className={item} onClick={() => run('html')}>
                            <Printer className="h-4 w-4 text-slate-500" /> {t('Print')}
                        </button>
                    </div>
                    {busy && <p className="px-2 pt-1 text-xs text-slate-500">{t('Preparing the file…')}</p>}
                </motion.div>
            )}
            </AnimatePresence>
            {message && (
                <p role={message.kind === 'error' ? 'alert' : 'status'} className={`absolute end-0 top-full z-20 mt-12 w-72 rounded-lg px-3 py-2 text-xs shadow ${message.kind === 'error' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'}`}>
                    {message.text}
                </p>
            )}
        </div>
    );
}

/** A sortable column heading. */
export function SortHeader({ field, label, meta, onSort, className = '' }: { field: string; label: string; meta: PaginationMeta; onSort: (field: string, dir: 'asc' | 'desc') => void; className?: string }) {
    const { t } = useI18n();
    const active = meta.sort === field;

    return (
        <th scope="col" aria-sort={active ? (meta.dir === 'desc' ? 'descending' : 'ascending') : 'none'} className={className}>
            <button type="button" className="inline-flex items-center gap-1 font-semibold hover:text-fuchsia-700" onClick={() => onSort(field, active && meta.dir === 'asc' ? 'desc' : 'asc')}>
                {t(label)} <span className="text-[10px]">{active ? (meta.dir === 'desc' ? '▼' : '▲') : ''}</span>
            </button>
        </th>
    );
}
