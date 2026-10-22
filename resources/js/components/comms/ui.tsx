import { Avatar } from '@/components/avatar';
import { OfficePage } from '@/components/office-ui';
import { PortalShell } from '@/components/portal-shell';
import { useI18n } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { FileText, Paperclip, X } from 'lucide-react';
import { type ReactNode, useRef, useState } from 'react';

export type Shell =
    | { kind: 'office'; navigation: any; roleLabel: string; base: string }
    | { kind: 'portal'; caption: string; roleLabel: string; notices: { title: string; body: string; date: string }[]; base: string };

/** The same page inside the office layout or the student and parent portal, depending on who is signed in. */
export function CommsPage({ shell, title, subtitle, children }: { shell: Shell; title: string; subtitle?: string; children: ReactNode }) {
    const { t } = useI18n();
    if (shell.kind === 'office') {
        return (
            <OfficePage navigation={shell.navigation} roleLabel={shell.roleLabel} title={title} subtitle={subtitle ?? ''} wide>
                {children}
            </OfficePage>
        );
    }

    return (
        <PortalShell caption={shell.caption} roleLabel={shell.roleLabel} title={title} notices={shell.notices}>
            <Head title={t(title)} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">{t(title)}</h1>
                    {subtitle && <p className="mt-1 text-sm text-slate-500">{t(subtitle)}</p>}
                </div>
                {children}
            </div>
        </PortalShell>
    );
}

export function Tabs({ items, active }: { items: { key: string; label: string; href: string; count?: number }[]; active: string }) {
    const { t } = useI18n();

    return (
        <nav className="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1" aria-label={t('Sections')}>
            {items.map((i) => (
                <Link key={i.key} href={i.href} aria-current={active === i.key ? 'page' : undefined} className={`flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-medium transition ${active === i.key ? 'bg-white text-fuchsia-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}>
                    {t(i.label)}
                    {!!i.count && <span className="rounded-full bg-rose-600 px-1.5 text-[11px] font-bold text-white">{i.count}</span>}
                </Link>
            ))}
        </nav>
    );
}

const statusTone: Record<string, string> = {
    submitted: 'bg-sky-100 text-sky-700', open: 'bg-sky-100 text-sky-700', acknowledged: 'bg-indigo-100 text-indigo-700', in_progress: 'bg-amber-100 text-amber-700',
    waiting: 'bg-purple-100 text-purple-700', resolved: 'bg-emerald-100 text-emerald-700', closed: 'bg-slate-200 text-slate-600', reopened: 'bg-rose-100 text-rose-700',
};
const priorityTone: Record<string, string> = { low: 'text-slate-500', normal: 'text-sky-600', high: 'text-amber-600', urgent: 'text-rose-600' };

export function StatusBadge({ status, labels }: { status: string; labels: Record<string, string> }) {
    const { t } = useI18n();

    return <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ${statusTone[status] ?? 'bg-slate-100 text-slate-600'}`}>{t(labels[status] ?? status)}</span>;
}

export function PriorityMark({ priority, labels }: { priority: string; labels: Record<string, string> }) {
    const { t } = useI18n();

    return (
        <span className={`inline-flex items-center gap-1 text-xs font-semibold ${priorityTone[priority] ?? ''}`}>
            <span className="text-base leading-none" aria-hidden>{priority === 'urgent' ? '▲▲' : priority === 'high' ? '▲' : priority === 'low' ? '▽' : '●'}</span>
            {t(labels[priority] ?? priority)}
        </span>
    );
}

export function RoleChip({ role }: { role: string }) {
    const { t } = useI18n();
    const names: Record<string, string> = { admin: 'Admin', teacher: 'Teacher', staff: 'Staff', student: 'Student', parent: 'Parent' };

    return <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">{t(names[role] ?? role)}</span>;
}

export function Person({ p, size = 'h-8 w-8 text-xs' }: { p: { name: string; role: string; photo?: string | null }; size?: string }) {
    return (
        <span className="inline-flex items-center gap-2">
            <Avatar name={p.name} src={p.photo} className={size} />
            <span className="font-medium">{p.name}</span>
            <RoleChip role={p.role} />
        </span>
    );
}

export function when(iso: string | null | undefined): string {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T') + (iso.includes('Z') || iso.includes('+') ? '' : 'Z'));

    return isNaN(d.getTime()) ? iso : d.toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const size = (n: number) => (n > 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

export function AttachmentList({ files }: { files: { id: number; name: string; size: number; mime: string; url: string }[] }) {
    const { t } = useI18n();
    if (!files?.length) return null;

    return (
        <ul className="mt-3 flex flex-wrap gap-2">
            {files.map((f) => (
                <li key={f.id}>
                    <a href={f.url} target="_blank" rel="noreferrer" className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs hover:border-fuchsia-300">
                        <FileText className="h-4 w-4 text-fuchsia-700" />
                        <span className="max-w-[200px] truncate font-medium">{f.name}</span>
                        <span className="text-slate-400">{size(f.size)}</span>
                        <span className="sr-only">{t('Open')}</span>
                    </a>
                </li>
            ))}
        </ul>
    );
}

/** Choose files to attach. Type and size are checked here and again on the server. */
export function FilePicker({ files, onChange, limits, error }: { files: File[]; onChange: (f: File[]) => void; limits: { files: number; kb: number; types: string[] }; error?: string }) {
    const { t } = useI18n();
    const ref = useRef<HTMLInputElement>(null);
    const [problem, setProblem] = useState('');

    const add = (picked: FileList | null) => {
        setProblem('');
        const next = [...files];
        for (const f of Array.from(picked ?? [])) {
            const ext = f.name.split('.').pop()?.toLowerCase() ?? '';
            if (!limits.types.includes(ext)) return setProblem(t('{name}: this type of file cannot be attached.', { name: f.name }));
            if (f.size > limits.kb * 1024) return setProblem(t('{name} is larger than {mb} MB.', { name: f.name, mb: Math.round(limits.kb / 1024) }));
            if (next.length >= limits.files) return setProblem(t('You can attach up to {n} files.', { n: limits.files }));
            next.push(f);
        }
        onChange(next);
        if (ref.current) ref.current.value = '';
    };

    return (
        <div>
            <input ref={ref} type="file" multiple accept={limits.types.map((x) => `.${x}`).join(',')} className="hidden" onChange={(e) => add(e.target.files)} />
            <button type="button" onClick={() => ref.current?.click()} className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">
                <Paperclip className="h-4 w-4" /> {t('Attach files')}
            </button>
            <span className="ms-3 text-xs text-slate-400">{t('PDF, images, Word or text. Up to {n} files, {mb} MB each.', { n: limits.files, mb: Math.round(limits.kb / 1024) })}</span>
            {files.length > 0 && (
                <ul className="mt-2 flex flex-wrap gap-2">
                    {files.map((f, i) => (
                        <li key={i} className="flex items-center gap-2 rounded-lg bg-slate-100 px-2.5 py-1 text-xs">
                            <span className="max-w-[180px] truncate">{f.name}</span>
                            <button type="button" aria-label={t('Remove {name}', { name: f.name })} onClick={() => onChange(files.filter((_, k) => k !== i))}>
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {(problem || error) && <p role="alert" className="mt-1 text-xs text-rose-600">{problem || error}</p>}
        </div>
    );
}
