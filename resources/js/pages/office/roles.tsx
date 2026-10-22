import { Badge, Card, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { FormEvent } from 'react';

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
    granted: string[];
    counts: Record<string, number>;
}) {
    const { t } = useI18n();
    const form = useForm<{ permissions: string[] }>({ permissions: granted });
    const toggle = (key: string) =>
        form.setData(
            'permissions',
            form.data.permissions.includes(key) ? form.data.permissions.filter((k) => k !== key) : [...form.data.permissions, key],
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/system/roles', { preserveScroll: true });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Roles & Permissions"
            subtitle="Choose which modules each kind of user can open."
        >
            <div className="grid gap-3 sm:grid-cols-4">
                {(['admin', 'staff', 'student', 'parent'] as const).map((r) => (
                    <Card key={r}>
                        <div className="text-2xl font-bold">{counts[r] ?? 0}</div>
                        <div className="text-xs text-slate-500 capitalize">{t(r.charAt(0).toUpperCase() + r.slice(1))}</div>
                    </Card>
                ))}
            </div>

            <form onSubmit={submit}>
                <Card className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">{t('Staff access')}</h2>
                        <Badge tone="slate">
                            <Lock className="me-1 inline h-3 w-3" />
                            {t('Admins always have full access')}
                        </Badge>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {modules.map((m) => (
                            <label key={m.key} className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" checked={form.data.permissions.includes(m.key)} onChange={() => toggle(m.key)} />
                                {t(m.label)}
                            </label>
                        ))}
                    </div>
                    <p className="text-xs text-slate-500">{t('Students and parents only see their own portal, so they have no module settings.')}</p>
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Save permissions')}
                    </button>
                </Card>
            </form>
        </OfficePage>
    );
}
