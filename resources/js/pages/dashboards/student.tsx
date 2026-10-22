import { Bar, DashShell, Panel, Stat, StatGrid } from '@/components/dash';

type Props = { stats: Stat[]; subjects: { name: string; marks: number }[]; homework: { title: string; due: string }[] };

export default function StudentDashboard({ stats, subjects, homework }: Props) {
    return (
        <DashShell title="Student Dashboard" subtitle="Your attendance, results and homework">
            <StatGrid stats={stats} />
            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title="Mid-term marks">
                    {subjects.map((s) => (
                        <Bar key={s.name} label={s.name} value={s.marks} right={`${s.marks}/100`} />
                    ))}
                </Panel>
                <Panel title="Homework due">
                    <ul className="divide-y text-sm">
                        {homework.map((h) => (
                            <li key={h.title} className="flex justify-between py-2">
                                <span>{h.title}</span>
                                <span className="text-muted-foreground">{h.due}</span>
                            </li>
                        ))}
                    </ul>
                </Panel>
            </div>
        </DashShell>
    );
}
