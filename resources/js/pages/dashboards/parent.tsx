import { Bar, DashShell, Notice, NoticeList, Panel, Stat, StatGrid } from '@/components/dash';

type Child = { name: string; class: string; attendance: number; result: string };
type Props = { stats: Stat[]; children: Child[]; notices: Notice[] };

export default function ParentDashboard({ stats, children, notices }: Props) {
    return (
        <DashShell title="Parent Dashboard" subtitle="Follow your children's progress">
            <StatGrid stats={stats} />
            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title="My children">
                    {children.map((c) => (
                        <div key={c.name} className="mb-4">
                            <div className="flex justify-between text-sm font-semibold">
                                <span>{c.name}</span>
                                <span className="text-fuchsia-700">{c.result}</span>
                            </div>
                            <div className="text-muted-foreground mb-2 text-xs">{c.class}</div>
                            <Bar label="Attendance" value={c.attendance} />
                        </div>
                    ))}
                </Panel>
                <Panel title="Notices from school">
                    <NoticeList notices={notices} />
                </Panel>
            </div>
        </DashShell>
    );
}
