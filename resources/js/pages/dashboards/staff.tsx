import { DashShell, Notice, NoticeList, Panel, Stat, StatGrid } from '@/components/dash';

type Props = { stats: Stat[]; schedule: { time: string; class: string; room: string }[]; notices: Notice[] };

export default function StaffDashboard({ stats, schedule, notices }: Props) {
    return (
        <DashShell title="Staff Dashboard" subtitle="Your teaching day at a glance">
            <StatGrid stats={stats} />
            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title="Today's schedule">
                    <ul className="divide-y text-sm">
                        {schedule.map((s) => (
                            <li key={s.time} className="flex items-center gap-4 py-2">
                                <span className="w-14 font-semibold text-fuchsia-700">{s.time}</span>
                                <span className="flex-1">{s.class}</span>
                                <span className="text-muted-foreground">{s.room}</span>
                            </li>
                        ))}
                    </ul>
                </Panel>
                <Panel title="Notices">
                    <NoticeList notices={notices} />
                </Panel>
            </div>
        </DashShell>
    );
}
