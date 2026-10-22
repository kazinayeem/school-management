import { DashShell } from '@/components/dash';

export default function Section({ title }: { title: string }) {
    return (
        <DashShell title={title} subtitle="This module is coming soon.">
            <div className="border-sidebar-border/70 text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                {title} will appear here.
            </div>
        </DashShell>
    );
}
