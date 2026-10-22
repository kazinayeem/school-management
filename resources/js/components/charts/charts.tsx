import { AnimatedNumber, Reveal } from '@/components/motion';
import { useI18n } from '@/lib/i18n';
import { useReducedMotion } from 'motion/react';
import { type ReactNode } from 'react';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

/** One colour per meaning, used everywhere: present is green, absent is red, and so on. */
export const COLORS: Record<string, string> = {
    present: '#16a34a', late: '#f59e0b', absent: '#dc2626', leave: '#0ea5e9',
    paid: '#16a34a', due: '#f59e0b', outstanding: '#f59e0b', billed: '#94a3b8', collected: '#16a34a',
    received: '#0ea5e9', under_review: '#f59e0b', admitted: '#16a34a', rejected: '#dc2626',
    new: '#f59e0b', replied: '#16a34a', read: '#0ea5e9',
    'on loan': '#0ea5e9', overdue: '#dc2626', returned: '#16a34a',
    todo: '#f59e0b', submitted: '#0ea5e9', marked: '#16a34a', pending: '#f59e0b', success: '#16a34a', failed: '#dc2626',
    B: '#0ea5e9',
};

/** The school's own colours, read from the page so a changed colour shows in the charts too. */
const css = (name: string, fallback: string) => (typeof window === 'undefined' ? fallback : getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback);
export const brand = () => css('--brand-primary', '#b31299');
export const secondary = () => css('--brand-secondary', '#7c3aed');
export const PALETTE = ['#b31299', '#0ea5e9', '#16a34a', '#f59e0b', '#7c3aed', '#dc2626', '#0d9488', '#64748b'];
const colour = (name: string, i: number) => (name === 'A' ? brand() : COLORS[name] ?? (i === 0 && !COLORS[name] ? brand() : PALETTE[i % PALETTE.length]));

const tooltipStyle = { borderRadius: 12, border: '1px solid var(--chart-tooltip-border, #e2e8f0)', background: 'var(--chart-tooltip, #fff)', color: 'var(--chart-tooltip-text, #0f172a)', boxShadow: '0 8px 24px rgba(15,23,42,.12)', fontSize: 12 };
const axis = { fontSize: 11, fill: 'currentColor' };

export function Skeleton({ className = '' }: { className?: string }) {
    return <div className={`animate-pulse rounded-lg bg-slate-200/70 ${className}`} aria-hidden />;
}

export function ChartEmpty({ message }: { message?: string }) {
    const { t } = useI18n();

    return (
        <div className="flex h-full min-h-40 flex-col items-center justify-center rounded-xl bg-slate-50 px-4 text-center text-sm text-slate-400">
            <span className="mb-2 text-2xl" aria-hidden>
                ▁▃▅
            </span>
            {t(message ?? 'No data for this period yet.')}
        </div>
    );
}

/** The frame every chart sits in: a heading, an optional control, and a skeleton while the next numbers load. */
export function ChartCard({ title, subtitle, loading = false, empty = false, height = 240, action, children, className = '', delay = 0 }: { title: string; subtitle?: string; loading?: boolean; empty?: boolean; height?: number; action?: ReactNode; children: ReactNode; className?: string; delay?: number }) {
    const { t } = useI18n();

    return (
        <Reveal delay={delay} className={className}>
            <section className="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md" aria-busy={loading}>
                <div className="mb-3 flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h2 className="font-semibold text-slate-900">{t(title)}</h2>
                        {subtitle && <p className="mt-0.5 text-xs text-slate-500">{t(subtitle)}</p>}
                    </div>
                    {action}
                </div>
                <div style={{ height }} className="relative">
                    {loading ? <Skeleton className="h-full w-full" /> : empty ? <ChartEmpty /> : children}
                </div>
            </section>
        </Reveal>
    );
}

/** A headline number with a short label. The number counts up when it changes. */
export function StatCard({ label, value, note, tone = 'slate', delay = 0 }: { label: string; value: string | number; note?: string; tone?: 'slate' | 'green' | 'amber' | 'red' | 'pink'; delay?: number }) {
    const { t } = useI18n();
    const tones = { slate: 'text-slate-900', green: 'text-emerald-600', amber: 'text-amber-600', red: 'text-rose-600', pink: 'text-brand' };

    return (
        <Reveal delay={delay}>
            <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <p className="text-xs font-medium text-slate-500">{t(label)}</p>
                <AnimatedNumber value={value} className={`mt-1 block text-2xl font-bold ${tones[tone]}`} />
                {note && <p className="mt-0.5 text-[11px] text-slate-400">{t(note)}</p>}
            </div>
        </Reveal>
    );
}

type Series = { key: string; label: string; color?: string };

/** Line or area over time. `percent` style axes run 0 to 100. */
export function TrendChart({ data, x, series, area = false, percent = false, summary }: { data: Record<string, unknown>[]; x: string; series: Series[]; area?: boolean; percent?: boolean; summary: string }) {
    const { t } = useI18n();
    const reduce = useReducedMotion();
    const Chart = area ? AreaChart : LineChart;

    return (
        <div role="img" aria-label={summary} className="h-full w-full text-slate-500">
            <ResponsiveContainer width="100%" height="100%">
                <Chart data={data} margin={{ top: 6, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="currentColor" strokeOpacity={0.15} vertical={false} />
                    <XAxis dataKey={x} tick={axis} tickLine={false} axisLine={false} minTickGap={24} />
                    <YAxis tick={axis} tickLine={false} axisLine={false} domain={percent ? [0, 100] : [0, 'auto']} tickFormatter={percent ? (v) => `${v}%` : undefined} allowDecimals={false} width={48} />
                    <Tooltip contentStyle={tooltipStyle} formatter={(v, name) => [percent ? `${v}%` : Number(v).toLocaleString(), t(String(name))]} />
                    {series.length > 1 && <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} formatter={(v) => t(String(v))} />}
                    {series.map((s, i) =>
                        area ? (
                            <Area key={s.key} type="monotone" dataKey={s.key} name={s.label} stroke={s.color ?? colour(s.key, i)} fill={s.color ?? colour(s.key, i)} fillOpacity={0.15} strokeWidth={2} isAnimationActive={!reduce} animationDuration={600} connectNulls />
                        ) : (
                            <Line key={s.key} type="monotone" dataKey={s.key} name={s.label} stroke={s.color ?? colour(s.key, i)} strokeWidth={2.4} dot={data.length < 20} isAnimationActive={!reduce} animationDuration={600} connectNulls />
                        ),
                    )}
                </Chart>
            </ResponsiveContainer>
        </div>
    );
}

/** Bars: vertical, horizontal or stacked. */
export function BarsChart({ data, x, series, horizontal = false, stacked = false, percent = false, summary }: { data: Record<string, unknown>[]; x: string; series: Series[]; horizontal?: boolean; stacked?: boolean; percent?: boolean; summary: string }) {
    const { t } = useI18n();
    const reduce = useReducedMotion();

    return (
        <div role="img" aria-label={summary} className="h-full w-full text-slate-500">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} layout={horizontal ? 'vertical' : 'horizontal'} margin={{ top: 6, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="currentColor" strokeOpacity={0.15} horizontal={!horizontal} vertical={horizontal} />
                    {horizontal ? (
                        <>
                            <XAxis type="number" tick={axis} tickLine={false} axisLine={false} domain={percent ? [0, 100] : [0, 'auto']} allowDecimals={false} />
                            <YAxis type="category" dataKey={x} tick={axis} tickLine={false} axisLine={false} width={96} />
                        </>
                    ) : (
                        <>
                            <XAxis dataKey={x} tick={axis} tickLine={false} axisLine={false} interval={0} />
                            <YAxis tick={axis} tickLine={false} axisLine={false} domain={percent ? [0, 100] : [0, 'auto']} allowDecimals={false} width={48} />
                        </>
                    )}
                    <Tooltip contentStyle={tooltipStyle} cursor={{ fill: 'rgba(179,18,153,.06)' }} formatter={(v, name) => [percent ? `${v}%` : Number(v).toLocaleString(), t(String(name))]} />
                    {series.length > 1 && <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} formatter={(v) => t(String(v))} />}
                    {series.map((s, i) => (
                        <Bar key={s.key} dataKey={s.key} name={s.label} stackId={stacked ? 's' : undefined} fill={s.color ?? colour(s.key, i)} radius={stacked ? 0 : [6, 6, 0, 0]} isAnimationActive={!reduce} animationDuration={600} maxBarSize={36} />
                    ))}
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

/** A donut for a handful of categories, with the total in the middle. */
export function DonutChart({ data, summary, centre }: { data: { name: string; value: number }[]; summary: string; centre?: { value: string | number; label: string } }) {
    const { t } = useI18n();
    const reduce = useReducedMotion();
    const total = data.reduce((n, d) => n + d.value, 0);

    return (
        <div role="img" aria-label={summary} className="relative h-full w-full">
            <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                    <Pie data={data} dataKey="value" nameKey="name" innerRadius="58%" outerRadius="86%" paddingAngle={data.length > 1 ? 2 : 0} stroke="none" isAnimationActive={!reduce} animationDuration={700}>
                        {data.map((d, i) => (
                            <Cell key={d.name} fill={colour(d.name, i)} />
                        ))}
                    </Pie>
                    <Tooltip contentStyle={tooltipStyle} formatter={(v, name) => [`${Number(v).toLocaleString()} (${total ? Math.round((Number(v) / total) * 100) : 0}%)`, t(String(name).replace('_', ' '))]} />
                    <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} formatter={(v) => t(String(v).replace('_', ' '))} />
                </PieChart>
            </ResponsiveContainer>
            {centre && (
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center pb-6 text-center">
                    <span className="text-xl font-bold text-slate-900">{centre.value}</span>
                    <span className="text-[11px] text-slate-500">{t(centre.label)}</span>
                </div>
            )}
        </div>
    );
}
