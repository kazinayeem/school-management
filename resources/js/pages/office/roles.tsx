import { Badge, Card, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Role = 'teacher' | 'staff';
const actions = ['view', 'edit', 'delete'] as const;
const actionLabel = { view: 'View', edit: 'Edit', delete: 'Delete' } as const;
const roleTitle = { teacher: 'Teachers', staff: 'Office staff' } as const;

function Matrix({ role, modules, granted }: { role: Role; modules: { key: string; label: string }[]; granted: string[] }) {
    const { t } = useI18n();
    const form = useForm<{ role: Role; permissions: string[] }>({ role, permissions: granted });
    const has = (m: string, a: string) => form.data.permissions.includes(`${m}.${a}`);

    // Ticking a stronger action ticks the weaker ones (delete needs edit needs view); clearing a weaker one clears the stronger.
    const toggle = (m: string, a: (typeof actions)[number]) => {
        const next = new Set(form.data.permissions);
        const i = actions.indexOf(a);
        if (has(m, a)) actions.slice(i).forEach((x) => next.delete(`${m}.${x}`));
        else actions.slice(0, i + 1).forEach((x) => next.add(`${m}.${x}`));
        form.setData('permissions', [...next]);
    };
    const setAll = (a: (typeof actions)[number] | null) =>
        form.setData('permissions', a === null ? [] : modules.flatMap((m) => actions.slice(0, actions.indexOf(a) + 1).map((x) => `${m.key}.${x}`)));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/system/roles', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="flex flex-wrap items-center gap-3 text-sm">
                <span className="text-slate-500">{t('Quick set')}:</span>
                <button type="button" onClick={() => setAll(null)} className="rounded-lg border px-3 py-1 hover:bg-slate-50">
                    {t('No access')}
                </button>
                <button type="button" onClick={() => setAll('view')} className="rounded-lg border px-3 py-1 hover:bg-slate-50">
                    {t('View everything')}
                </button>
                <button type="button" onClick={() => setAll('delete')} className="rounded-lg border px-3 py-1 hover:bg-slate-50">
                    {t('Full access')}
                </button>
            </div>
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full min-w-[480px] text-sm">
                    <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th className="px-4 py-3 text-start font-semibold">{t('Module')}</th>
                            {actions.map((a) => (
                                <th key={a} className="w-24 px-4 py-3 text-center font-semibold">
                                    {t(actionLabel[a])}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {modules.map((m) => (
                            <tr key={m.key} className="hover:bg-slate-50">
                                <td className="px-4 py-2.5">{t(m.label)}</td>
                                {actions.map((a) => (
                                    <td key={a} className="px-4 py-2.5 text-center">
                                        <input
                                            type="checkbox"
                                            aria-label={`${t(m.label)} ${t(actionLabel[a])}`}
                                            checked={has(m.key, a)}
                                            onChange={() => toggle(m.key, a)}
                                        />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="text-xs text-slate-500">{t('Edit includes adding and changing records. Delete needs Edit, and Edit needs View.')}</p>
            <button disabled={form.processing} className={primaryBtn}>
                {t('Save permissions')}
            </button>
        </form>
    );
}

export default function Roles({
    navigation,
    roleLabel,
    modules,
    granted,
    counts,
}: {
    navigation: Nav;
    roleLabel: string;
    modules: { key: string; label: string }[];
    granted: Record<Role, string[]>;
    counts: Record<string, number>;
}) {
    const { t } = useI18n();
    const [role, setRole] = useState<Role>('teacher');

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Roles & Permissions"
            subtitle="Choose what teachers and office staff can view, edit and delete in each module."
        >
            <div className="grid gap-3 sm:grid-cols-5">
                {(['admin', 'teacher', 'staff', 'student', 'parent'] as const).map((r) => (
                    <Card key={r}>
                        <div className="text-2xl font-bold">{counts[r] ?? 0}</div>
                        <div className="text-xs text-slate-500">{t(r.charAt(0).toUpperCase() + r.slice(1))}</div>
                    </Card>
                ))}
            </div>

            <Card className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div role="tablist" className="flex gap-2">
                        {(['teacher', 'staff'] as const).map((r) => (
                            <button
                                key={r}
                                role="tab"
                                aria-selected={role === r}
                                onClick={() => setRole(r)}
                                className={`rounded-lg px-4 py-2 text-sm font-semibold ${role === r ? 'bg-fuchsia-700 text-white' : 'border hover:bg-slate-50'}`}
                            >
                                {t(roleTitle[r])}
                            </button>
                        ))}
                    </div>
                    <Badge tone="slate">
                        <Lock className="me-1 inline h-3 w-3" />
                        {t('Admins always have full access')}
                    </Badge>
                </div>
                <Matrix key={role} role={role} modules={modules} granted={granted[role]} />
                <p className="text-xs text-slate-500">
                    {t('Some pages stay admin-only for everyone else: school structure, publishing results, settings, user accounts and backups.')}
                </p>
            </Card>
        </OfficePage>
    );
}
