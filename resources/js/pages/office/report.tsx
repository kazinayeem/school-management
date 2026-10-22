import { Card, ghostBtn, inputCls, money, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Printer, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Filter = { name: string; label: string; type: 'date' | 'select' | 'text'; options?: { value: string; label: string }[]; with?: Filter };
type Column = { key: string; label: string; money?: boolean; num?: boolean };
type Action = { label: string; url?: string; href?: string; data?: Record<string, string>; confirm?: string };
type Section = { title: string | null; columns: Column[]; rows: Record<string, any>[]; footer?: Record<string, any> };

export default function Report({
    navigation,
    roleLabel,
    title,
    subtitle,
    filters,
    values,
    sections,
    summary,
    notes = [],
    actions = [],
}: {
    navigation: Nav;
    roleLabel: string;
    title: string;
    subtitle: string;
    filters: Filter[];
    values: Record<string, string>;
    sections: Section[];
    summary: { label: string; value: number }[];
    notes?: string[];
    actions?: Action[];
}) {
    const { t } = useI18n();
    const [state, setState] = useState<Record<string, string>>(values);
    const apply = (next: Record<string, string>) => {
        setState(next);
        router.get(window.location.pathname, next, { preserveState: true, replace: true, preserveScroll: true });
    };

    const controls = filters.flatMap((f) => (f.with ? [f, f.with] : [f]));

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title={title} subtitle={subtitle} wide>
            <Card className="flex flex-wrap items-end gap-4">
                {controls.map((f) => (
                    <div key={f.name}>
                        <label className="mb-1 block text-xs font-medium text-slate-500">{t(f.label)}</label>
                        {f.type === 'select' ? (
                            <select className={inputCls} value={state[f.name] ?? ''} onChange={(e) => apply({ ...state, [f.name]: e.target.value })}>
                                {f.options?.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {t(o.label)}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <input
                                type={f.type}
                                className={inputCls}
                                value={state[f.name] ?? ''}
                                onChange={(e) =>
                                    f.type === 'text'
                                        ? setState({ ...state, [f.name]: e.target.value })
                                        : apply({ ...state, [f.name]: e.target.value })
                                }
                                onKeyDown={(e) => e.key === 'Enter' && f.type === 'text' && apply(state)}
                            />
                        )}
                    </div>
                ))}
                {actions.map((a) =>
                    a.href ? (
                        <a key={a.label} href={a.href} className={ghostBtn}>
                            {t(a.label)}
                        </a>
                    ) : (
                        <button
                            key={a.label}
                            onClick={() =>
                                (!a.confirm || confirm(t(a.confirm, a.data ?? {}))) && router.post(a.url!, a.data ?? {}, { preserveScroll: true })
                            }
                            className={primaryBtn}
                        >
                            {t(a.label)}
                        </button>
                    ),
                )}
                <button onClick={() => window.print()} className={`${ghostBtn} ms-auto flex items-center gap-1`}>
                    <Printer className="h-4 w-4" /> {t('Print')}
                </button>
            </Card>

            {summary.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {summary.map((s) => (
                        <Card key={s.label}>
                            <div className="text-xs text-slate-500">{t(s.label)}</div>
                            <div className="mt-1 text-xl font-bold">{money(s.value)}</div>
                        </Card>
                    ))}
                </div>
            )}

            {notes.length > 0 && (
                <ul className="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
                    {notes.map((n) => (
                        <li key={n}>{n}</li>
                    ))}
                </ul>
            )}

            {sections.map((section, idx) => (
                <div key={idx} className="space-y-2">
                    {section.title && <h2 className="font-semibold">{t(section.title)}</h2>}
                    <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                        {section.rows.length === 0 ? (
                            <p className="px-6 py-14 text-center text-sm text-slate-400">{t('Nothing to show for this period.')}</p>
                        ) : (
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                    <tr>
                                        {section.columns.map((c) => (
                                            <th key={c.key} className={`px-4 py-3 font-semibold ${c.money || c.num ? 'text-end' : 'text-start'}`}>
                                                {t(c.label)}
                                            </th>
                                        ))}
                                        {section.rows.some((r) => r.delete_url) && <th />}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {section.rows.map((row, i) => (
                                        <tr key={i} className="hover:bg-slate-50">
                                            {section.columns.map((c) => (
                                                <td
                                                    key={c.key}
                                                    className={`px-4 py-3 ${c.money || c.num ? 'text-end tabular-nums' : ''} ${c.key === 'flag' && row[c.key] ? 'font-medium text-red-600' : ''}`}
                                                >
                                                    {c.money
                                                        ? money(row[c.key])
                                                        : c.key === 'type' || c.key === 'flag'
                                                          ? t(String(row[c.key] ?? ''))
                                                          : row[c.key]}
                                                </td>
                                            ))}
                                            {row.delete_url && (
                                                <td className="px-4 py-3 text-end">
                                                    <button
                                                        aria-label={t('Delete')}
                                                        className="text-slate-500 hover:text-red-600"
                                                        onClick={() =>
                                                            confirm(t('Delete this entry?')) &&
                                                            router.delete(row.delete_url, { preserveScroll: true })
                                                        }
                                                    >
                                                        <Trash2 className="inline h-4 w-4" />
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                                {section.footer && (
                                    <tfoot className="bg-slate-50 font-semibold">
                                        <tr>
                                            {section.columns.map((c) => (
                                                <td key={c.key} className={`px-4 py-3 ${c.money ? 'text-end tabular-nums' : ''}`}>
                                                    {c.money
                                                        ? money(section.footer![c.key])
                                                        : section.footer![c.key]
                                                          ? t(String(section.footer![c.key]))
                                                          : ''}
                                                </td>
                                            ))}
                                            {section.rows.some((r) => r.delete_url) && <td />}
                                        </tr>
                                    </tfoot>
                                )}
                            </table>
                        )}
                    </div>
                </div>
            ))}
        </OfficePage>
    );
}
