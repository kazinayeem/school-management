import { Bar, DashShell, Notice, NoticeList, Panel, Stat, StatGrid } from '@/components/dash';

type Props = { stats: Stat[]; users: number; classes: { name: string; students: number; attendance: number }[]; notices: Notice[] };

export default function AdminDashboard({ stats, users, classes, notices }: Props) {
    return (
        <DashShell title="Admin Dashboard" subtitle={`School overview · ${users} system users`}>
            <StatGrid stats={stats} />
            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title="Attendance by class">
                    {classes.map((c) => (
                        <Bar key={c.name} label={`${c.name} (${c.students} students)`} value={c.attendance} />
                    ))}
                </Panel>
                <Panel title="Recent notices">
                    <NoticeList notices={notices} />
                </Panel>
            </div>
        </DashShell>
    );
}
