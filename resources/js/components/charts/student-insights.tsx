import { BarsChart, brand, ChartCard, DonutChart, secondary, StatCard, TrendChart } from '@/components/charts/charts';
import { Reveal } from '@/components/motion';
import { useNavigating } from '@/components/list-kit';
import { useI18n } from '@/lib/i18n';

export type StudentCharts = {
    attendance?: { trend: Record<string, unknown>[]; split: { name: string; value: number }[]; percent: number | null };
    progress?: { exam: string; gpa: number; percent: number | null; class_average: number | null }[];
    subjects?: { exam: string; rows: { subject: string; mine: number; class: number | null; grade: string }[] };
    fees?: { paid: number; due: number; next_due: string | null };
    homework?: { name: string; value: number }[];
    upcoming_exams?: { exam: string; subject: string; exam_date: string; starts_at: string | null; room: string | null }[];
    events?: { date: string; title: string; type: string }[];
};

const money = (n: number) => `Tk ${n.toLocaleString()}`;

/** One child's own charts. The numbers are already limited to that child by the server. */
export function StudentInsights({ charts, name }: { charts?: StudentCharts; name?: string }) {
    const { t } = useI18n();
    const busy = useNavigating();
    const loading = !charts || busy;
    const att = charts?.attendance;
    const fees = charts?.fees;
    const homework = charts?.homework ?? [];
    const done = homework.filter((h) => h.name === 'submitted' || h.name === 'marked').reduce((n, h) => n + h.value, 0);
    const totalHw = homework.reduce((n, h) => n + h.value, 0);

    return (
        <section aria-label={t('Progress')} className="space-y-4">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard label="Attendance" value={att?.percent != null ? `${att.percent}%` : '—'} tone={att?.percent != null && att.percent < 75 ? 'red' : 'green'} note="Last 12 weeks" />
                <StatCard label="Latest GPA" value={charts?.progress?.length ? charts.progress[charts.progress.length - 1].gpa.toFixed(2) : '—'} tone="pink" note={charts?.progress?.length ? charts.progress[charts.progress.length - 1].exam : undefined} delay={0.04} />
                <StatCard label="Fees due" value={fees ? money(fees.due) : '—'} tone={fees && fees.due > 0 ? 'amber' : 'green'} note={fees?.next_due ? `${t('Next due')} ${fees.next_due}` : undefined} delay={0.08} />
                <StatCard label="Homework done" value={totalHw ? `${Math.round((done / totalHw) * 100)}%` : '—'} note={totalHw ? `${done} of ${totalHw}` : undefined} delay={0.12} />
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <ChartCard className="xl:col-span-2" title="Attendance by week" subtitle={name} loading={loading} empty={!att || att.trend.length === 0}>
                    {att && <TrendChart data={att.trend} x="date" percent area series={[{ key: 'percent', label: 'Attendance %', color: '#16a34a' }]} summary={t('Weekly attendance percentage')} />}
                </ChartCard>
                <ChartCard title="Present, late and absent" loading={loading} empty={!att || att.split.every((s) => s.value === 0)} delay={0.05}>
                    {att && <DonutChart data={att.split.filter((s) => s.value > 0)} centre={{ value: att.percent != null ? `${att.percent}%` : '—', label: 'attendance' }} summary={t('Attendance split by status')} />}
                </ChartCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <ChartCard title="Progress across exams" subtitle="GPA, and marks compared with the class average" loading={loading} empty={!charts?.progress || charts.progress.length === 0}>
                    {charts?.progress && (
                        <TrendChart data={charts.progress} x="exam" series={[{ key: 'percent', label: 'My marks %', color: brand() }, { key: 'class_average', label: 'Class average %', color: '#94a3b8' }]} percent summary={t('Marks percentage in each exam against the class average')} />
                    )}
                </ChartCard>
                <ChartCard title="Marks by subject" subtitle={charts?.subjects?.exam} loading={loading} empty={!charts?.subjects || charts.subjects.rows.length === 0} delay={0.05}>
                    {charts?.subjects && (
                        <BarsChart data={charts.subjects.rows} x="subject" percent series={[{ key: 'mine', label: 'My marks %', color: brand() }, { key: 'class', label: 'Class average %', color: '#cbd5e1' }]} summary={t('Marks in each subject against the class average')} />
                    )}
                </ChartCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <ChartCard title="Fees paid and due" loading={loading} empty={!fees || fees.paid + fees.due === 0} delay={0.05}>
                    {fees && <DonutChart data={[{ name: 'paid', value: fees.paid }, { name: 'due', value: fees.due }].filter((d) => d.value > 0)} centre={{ value: money(fees.due), label: 'still due' }} summary={t('Fees paid compared with fees due')} />}
                </ChartCard>
                <ChartCard title="Homework" loading={loading} empty={homework.length === 0} delay={0.1}>
                    <DonutChart data={homework} centre={{ value: totalHw, label: 'items' }} summary={t('Homework by status')} />
                </ChartCard>
                <Reveal delay={0.15}>
                    <section className="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="mb-3 font-semibold">{t('Coming up')}</h2>
                        {(charts?.upcoming_exams?.length ?? 0) + (charts?.events?.length ?? 0) === 0 ? (
                            <p className="text-sm text-slate-400">{t('Nothing scheduled yet.')}</p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {charts?.upcoming_exams?.map((u, i) => (
                                    <li key={`e${i}`} className="flex items-baseline gap-3">
                                        <span className="w-20 shrink-0 text-xs font-semibold text-brand">{u.exam_date}</span>
                                        <span>
                                            {u.subject} <span className="text-xs text-slate-400">· {u.exam}{u.room ? ` · ${u.room}` : ''}</span>
                                        </span>
                                    </li>
                                ))}
                                {charts?.events?.map((e, i) => (
                                    <li key={`v${i}`} className="flex items-baseline gap-3">
                                        <span className="w-20 shrink-0 text-xs font-semibold text-sky-600">{e.date}</span>
                                        <span>{e.title}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </Reveal>
            </div>
        </section>
    );
}
