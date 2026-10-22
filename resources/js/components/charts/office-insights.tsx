import { BarsChart, brand, ChartCard, DonutChart, secondary, StatCard, TrendChart } from '@/components/charts/charts';
import { Reveal } from '@/components/motion';
import { useNavigating } from '@/components/list-kit';
import { useI18n } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';

export type OfficeCharts = {
    filters: { range: number; ranges: number[]; class: string; classes: string[]; from: string; to: string };
    people?: { students: number; teachers: number; staff: number; parents: number };
    students_by_class?: Record<string, string | number>[];
    section_keys?: string[];
    attendance?: { trend: Record<string, unknown>[]; split: { name: string; value: number }[]; percent: number | null };
    exam?: { name: string; grades: { grade: string; count: number }[]; subjects: { subject: string; average: number; fail_rate: number }[] };
    gpa_by_class?: { name: string; gpa: number; students: number }[];
    fees?: { months: Record<string, unknown>[]; collections: { date: string; amount: number }[]; collected_in_range: number; methods: { name: string; value: number }[]; outstanding: number; pending_approvals: number };
    admissions?: { status: { name: string; value: number }[]; months: { month: string; applications: number }[] };
    messages?: { name: string; value: number }[];
    library?: { status: { name: string; value: number }[]; issued: { date: string; loans: number }[] };
    transport?: { name: string; value: number }[];
    homework?: { name: string; value: number }[];
    upcoming?: { exam: string; subject: string; class_name: string; exam_date: string }[];
    today_classes?: { label: string; time_range: string; class_name: string; section: string; subject: string; room: string | null }[];
};

const sum = (rows: { value: number }[] = []) => rows.reduce((n, r) => n + r.value, 0);
const money = (n: number) => `Tk ${n.toLocaleString()}`;

export function OfficeInsights({ charts, teacher }: { charts?: OfficeCharts; teacher: boolean }) {
    const { t } = useI18n();
    const busy = useNavigating();
    const url = usePage().url;
    const loading = !charts || busy;
    const f = charts?.filters;

    const go = useCallback(
        (next: Record<string, string | number>) => {
            const q = new URL(url, window.location.origin).searchParams;
            const params: Record<string, string> = Object.fromEntries(q.entries());
            Object.entries(next).forEach(([k, v]) => (v === '' ? delete params[k] : (params[k] = String(v))));
            router.get(window.location.pathname, params, { preserveState: true, preserveScroll: true, only: ['charts'], replace: true });
        },
        [url],
    );

    const sel = 'rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700';
    const att = charts?.attendance;
    const fees = charts?.fees;

    return (
        <section aria-label={t('Insights')} className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">{t(teacher ? 'Your classes at a glance' : 'School insights')}</h2>
                    <p className="text-sm text-slate-500">{t('Live numbers from the school records.')}</p>
                </div>
                {f && (
                    <div className="flex flex-wrap gap-2">
                        <label className="sr-only" htmlFor="insight-range">{t('Period')}</label>
                        <select id="insight-range" className={sel} value={f.range} onChange={(e) => go({ range: e.target.value })}>
                            {f.ranges.map((r) => (
                                <option key={r} value={r}>
                                    {t('Last {n} days', { n: r })}
                                </option>
                            ))}
                        </select>
                        {f.classes.length > 1 && (
                            <>
                                <label className="sr-only" htmlFor="insight-class">{t('Class')}</label>
                                <select id="insight-class" className={sel} value={f.class} onChange={(e) => go({ class: e.target.value })}>
                                    <option value="">{t('All classes')}</option>
                                    {f.classes.map((c) => (
                                        <option key={c} value={c}>
                                            {t(c)}
                                        </option>
                                    ))}
                                </select>
                            </>
                        )}
                    </div>
                )}
            </div>

            {charts?.people && (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard label="Students" value={charts.people.students} tone="pink" />
                    <StatCard label="Teachers" value={charts.people.teachers} />
                    <StatCard label="Office staff" value={charts.people.staff} />
                    <StatCard label="Parents" value={charts.people.parents} delay={0.05} />
                </div>
            )}

            <div className="grid gap-4 xl:grid-cols-3">
                {(att || loading) && (
                    <ChartCard className="xl:col-span-2" title="Attendance trend" subtitle={f ? `${f.from} → ${f.to} · % of students present or late` : undefined} loading={loading} empty={!att || att.trend.length === 0}>
                        {att && <TrendChart data={att.trend} x="date" percent area series={[{ key: 'percent', label: 'Attendance %', color: '#16a34a' }]} summary={t('Daily attendance percentage. Average {n}%.', { n: att.percent ?? 0 })} />}
                    </ChartCard>
                )}
                {(att || loading) && (
                    <ChartCard title="Present, late and absent" subtitle={att?.percent != null ? `${att.percent}% attendance` : undefined} loading={loading} empty={!att || sum(att.split) === 0} delay={0.05}>
                        {att && <DonutChart data={att.split.filter((s) => s.value > 0)} centre={{ value: att.percent != null ? `${att.percent}%` : '—', label: 'attendance' }} summary={t('Attendance split by status')} />}
                    </ChartCard>
                )}
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                {charts?.students_by_class && (
                    <ChartCard className="xl:col-span-2" title="Students by class and section" loading={loading} empty={charts.students_by_class.length === 0}>
                        <BarsChart data={charts.students_by_class} x="name" stacked series={(charts.section_keys ?? []).map((k) => ({ key: k, label: `${t('Section')} ${k}` }))} summary={t('Number of students in each class, split by section')} />
                    </ChartCard>
                )}
                {charts?.exam && (
                    <ChartCard title="Grade distribution" subtitle={charts.exam.name} loading={loading} empty={charts.exam.grades.length === 0} delay={0.05}>
                        <DonutChart data={charts.exam.grades.map((g) => ({ name: g.grade, value: g.count }))} centre={{ value: charts.exam.grades.reduce((n, g) => n + g.count, 0), label: 'results' }} summary={t('How many results fall in each grade')} />
                    </ChartCard>
                )}
            </div>

            {charts?.exam && (
                <div className="grid gap-4 xl:grid-cols-2">
                    <ChartCard title={teacher ? 'Average mark by your subject' : 'Average mark by subject'} subtitle={`${charts.exam.name} · %`} loading={loading} empty={charts.exam.subjects.length === 0}>
                        <BarsChart data={charts.exam.subjects} x="subject" horizontal percent series={[{ key: 'average', label: 'Average mark %', color: brand() }]} summary={t('Average mark per subject')} />
                    </ChartCard>
                    {charts.gpa_by_class && (
                        <ChartCard title="Average GPA by class" loading={loading} empty={charts.gpa_by_class.length === 0} delay={0.05}>
                            <BarsChart data={charts.gpa_by_class} x="name" series={[{ key: 'gpa', label: 'Average GPA', color: '#0ea5e9' }]} summary={t('Average GPA of each class')} />
                        </ChartCard>
                    )}
                </div>
            )}

            {fees && (
                <>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatCard label={t('Collected in {n} days', { n: f?.range ?? 30 })} value={money(fees.collected_in_range)} tone="green" />
                        <StatCard label="Outstanding dues" value={money(fees.outstanding)} tone="amber" />
                        <StatCard label="Payments to approve" value={fees.pending_approvals} tone={fees.pending_approvals > 0 ? 'red' : 'slate'} note="Submitted by families" />
                    </div>
                    <div className="grid gap-4 xl:grid-cols-3">
                        <ChartCard className="xl:col-span-2" title="Fees: billed, collected and outstanding" subtitle="By due month" loading={loading} empty={fees.months.length === 0}>
                            <BarsChart data={fees.months} x="month" series={[{ key: 'collected', label: 'Collected', color: '#16a34a' }, { key: 'outstanding', label: 'Outstanding', color: '#f59e0b' }]} stacked summary={t('Collected and outstanding fees for each month')} />
                        </ChartCard>
                        <ChartCard title="How families paid" subtitle={t('Last {n} days', { n: f?.range ?? 30 })} loading={loading} empty={fees.methods.length === 0} delay={0.05}>
                            <DonutChart data={fees.methods} centre={{ value: money(fees.collected_in_range), label: 'received' }} summary={t('Payments received by method')} />
                        </ChartCard>
                    </div>
                    <ChartCard title="Daily collections" subtitle={t('Last {n} days', { n: f?.range ?? 30 })} loading={loading} empty={fees.collections.length === 0} height={200}>
                        <TrendChart data={fees.collections} x="date" area series={[{ key: 'amount', label: 'Collected', color: brand() }]} summary={t('Fees collected each day')} />
                    </ChartCard>
                </>
            )}

            <div className="grid gap-4 xl:grid-cols-3">
                {charts?.admissions && (
                    <>
                        <ChartCard title="Admission applications" subtitle="By month" loading={loading} empty={charts.admissions.months.length === 0}>
                            <BarsChart data={charts.admissions.months} x="month" series={[{ key: 'applications', label: 'Applications', color: secondary() }]} summary={t('Applications received each month')} />
                        </ChartCard>
                        <ChartCard title="Application status" loading={loading} empty={charts.admissions.status.length === 0} delay={0.05}>
                            <DonutChart data={charts.admissions.status} centre={{ value: sum(charts.admissions.status), label: 'applications' }} summary={t('Applications by status')} />
                        </ChartCard>
                    </>
                )}
                {charts?.messages && (
                    <ChartCard title="Website enquiries" subtitle="No ticket system yet: these are contact-form messages" loading={loading} empty={charts.messages.length === 0} delay={0.1}>
                        <DonutChart data={charts.messages} centre={{ value: sum(charts.messages), label: 'messages' }} summary={t('Website messages by status')} />
                    </ChartCard>
                )}
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                {charts?.library && (
                    <>
                        <ChartCard title="Books issued" subtitle={t('Last {n} days', { n: f?.range ?? 30 })} loading={loading} empty={charts.library.issued.length === 0}>
                            <TrendChart data={charts.library.issued} x="date" series={[{ key: 'loans', label: 'Loans', color: '#0d9488' }]} summary={t('Books issued each day')} />
                        </ChartCard>
                        <ChartCard title="Loans by status" loading={loading} empty={charts.library.status.length === 0} delay={0.05}>
                            <DonutChart data={charts.library.status} centre={{ value: sum(charts.library.status), label: 'loans' }} summary={t('Library loans by status')} />
                        </ChartCard>
                    </>
                )}
                {charts?.transport && (
                    <ChartCard title="Students on each bus route" loading={loading} empty={charts.transport.length === 0} delay={0.1}>
                        <BarsChart data={charts.transport} x="name" horizontal series={[{ key: 'value', label: 'Students', color: '#0ea5e9' }]} summary={t('Students riding each route')} />
                    </ChartCard>
                )}
                {charts?.homework && (
                    <ChartCard title={teacher ? 'Homework you set' : 'Homework'} subtitle="Handed in and checked" loading={loading} empty={charts.homework.length === 0} delay={0.1}>
                        <DonutChart data={charts.homework.map((h) => ({ ...h, name: h.name }))} centre={{ value: sum(charts.homework), label: 'items' }} summary={t('Homework by status')} />
                    </ChartCard>
                )}
            </div>

            {(charts?.upcoming?.length || charts?.today_classes?.length) ? (
                <div className="grid gap-4 xl:grid-cols-2">
                    {charts?.today_classes && charts.today_classes.length > 0 && (
                        <Reveal>
                            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-3 font-semibold">{t('Your lessons today')}</h2>
                                <ul className="divide-y divide-slate-100 text-sm">
                                    {charts.today_classes.map((c, i) => (
                                        <li key={i} className="flex flex-wrap items-center gap-3 py-2">
                                            <span className="w-28 text-xs text-slate-400">{c.time_range}</span>
                                            <span className="font-medium">{c.subject}</span>
                                            <span className="text-slate-500">{t(c.class_name)} · {c.section}</span>
                                            {c.room && <span className="ms-auto text-xs text-slate-400">{c.room}</span>}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        </Reveal>
                    )}
                    {charts?.upcoming && charts.upcoming.length > 0 && (
                        <Reveal delay={0.05}>
                            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-3 font-semibold">{t('Coming up: exams')}</h2>
                                <ul className="divide-y divide-slate-100 text-sm">
                                    {charts.upcoming.map((u, i) => (
                                        <li key={i} className="flex flex-wrap items-center gap-3 py-2">
                                            <span className="w-24 text-xs font-semibold text-brand">{u.exam_date}</span>
                                            <span className="font-medium">{u.subject}</span>
                                            <span className="text-slate-500">{t(u.class_name)}</span>
                                            <span className="ms-auto text-xs text-slate-400">{u.exam}</span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        </Reveal>
                    )}
                </div>
            ) : null}
        </section>
    );
}
