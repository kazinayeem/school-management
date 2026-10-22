import { Badge, Card, Field, ghostBtn, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { KeyRound, Power } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import { ExportMenu, HeaderCheckbox, Pagination, RowCheckbox, SelectionBanner, useBulkSelection, useListNav, useNavigating, type PaginationMeta } from '@/components/list-kit';

type Row = { id: number; name: string; username: string; email: string; role: string; is_active: number };

function PasswordForm({ user, onDone }: { user: Row; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm({ password: '' });

    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                form.put(`/app/system/users/${user.id}/password`, { preserveScroll: true, onSuccess: onDone });
            }}
        >
            <Card className="flex flex-wrap items-end gap-4 border-amber-200">
                <Field label={t('New password for {name}', { name: user.name })} error={form.errors.password}>
                    <input
                        type="password"
                        autoComplete="new-password"
                        className={inputCls}
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                    />
                </Field>
                <button disabled={form.processing} className={primaryBtn}>
                    {t('Change password')}
                </button>
                <button type="button" onClick={onDone} className={ghostBtn}>
                    {t('Cancel')}
                </button>
                <p className="w-full text-xs text-slate-500">{t('They will be signed out everywhere.')}</p>
            </Card>
        </form>
    );
}

function AdminForm({ onDone }: { onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm({ name: '', username: '', email: '', password: '' });

    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                form.post('/app/system/users', { preserveScroll: true, onSuccess: onDone });
            }}
        >
            <Card className="space-y-4 border-fuchsia-200">
                <h2 className="font-semibold">{t('Add administrator')}</h2>
                <div className="grid gap-4 md:grid-cols-4">
                    <Field label="Full name" error={form.errors.name}>
                        <input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Username" error={form.errors.username}>
                        <input className={inputCls} value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <input className={inputCls} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    </Field>
                    <Field label="Password" error={form.errors.password}>
                        <input
                            type="password"
                            autoComplete="new-password"
                            className={inputCls}
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                    </Field>
                </div>
                <div className="flex gap-3">
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Save')}
                    </button>
                    <button type="button" onClick={onDone} className={ghostBtn}>
                        {t('Cancel')}
                    </button>
                </div>
            </Card>
        </form>
    );
}

export default function Users({
    navigation,
    roleLabel,
    rows,
    counts,
    filters,
    me,
    pagination,
    exportColumns,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    counts: Record<string, number>;
    filters: { search: string; role: string; status: string; limit: number; sort: string; dir: string };
    me: number;
    pagination: PaginationMeta;
    exportColumns: Record<string, string>;
}) {
    const { t } = useI18n();
    const [q, setQ] = useState(filters.search);
    const [resetting, setResetting] = useState<Row | null>(null);
    const [adding, setAdding] = useState(false);
    const go = useListNav(filters);
    const busy = useNavigating();
    const sel = useBulkSelection(rows.map((r) => r.id), pagination.total, `${filters.search}|${filters.role}|${filters.status}`);
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }
        const id = setTimeout(() => q !== filters.search && go({ search: q }), 350);

        return () => clearTimeout(id);
    }, [q]);
    const roleName = (r: string) =>
        t(r === 'admin' ? 'Admin' : r === 'teacher' ? 'Teacher' : r === 'staff' ? 'Staff' : r === 'student' ? 'Student' : 'Parent');

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="User Accounts"
            subtitle="Everyone who can sign in. Turn accounts off, reset passwords, add administrators."
            wide
        >
            <div className="grid gap-3 sm:grid-cols-5">
                {(['admin', 'teacher', 'staff', 'student', 'parent'] as const).map((r) => (
                    <button
                        key={r}
                        onClick={() => go({ role: filters.role === r ? '' : r })}
                        className={`rounded-xl border bg-white p-4 text-start shadow-sm ${filters.role === r ? 'border-fuchsia-500' : 'border-slate-200'}`}
                    >
                        <div className="text-2xl font-bold">{counts[r] ?? 0}</div>
                        <div className="text-xs text-slate-500">{roleName(r)}</div>
                    </button>
                ))}
            </div>

            <div className="flex flex-wrap items-end gap-4">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        go({ search: q });
                    }}
                >
                    <input
                        className={`${inputCls} w-72`}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('Search name, username or email')}
                    />
                </form>
                <select className={`${inputCls} w-44`} value={filters.status} onChange={(e) => go({ status: e.target.value })}>
                    <option value="">{t('All statuses')}</option>
                    <option value="active">{t('Active')}</option>
                    <option value="inactive">{t('Inactive')}</option>
                </select>
                <span className="ms-auto flex gap-2">
                    <ExportMenu dataset="users" columns={exportColumns} params={{ ...filters }} sel={sel} total={pagination.total} noun="accounts" />
                    <button onClick={() => setAdding(true)} className={primaryBtn}>
                        {t('Add administrator')}
                    </button>
                </span>
            </div>
            <SelectionBanner sel={sel} pageCount={rows.length} total={pagination.total} noun="accounts" />

            {adding && <AdminForm onDone={() => setAdding(false)} />}
            {resetting && <PasswordForm key={resetting.id} user={resetting} onDone={() => setResetting(null)} />}

            <Table busy={busy} headers={[<HeaderCheckbox key="all" sel={sel} label={t('Select all on this page')} />, 'Name', 'Username', 'Email', 'Role', 'Status', '']} empty={rows.length === 0 ? 'No users found.' : null}>
                {rows.map((u) => (
                    <tr key={u.id} className={`hover:bg-slate-50 ${sel.isSelected(u.id) ? 'bg-fuchsia-50/50' : ''}`}>
                        <td className="px-4 py-3">
                            <RowCheckbox sel={sel} id={u.id} label={`${t('Select')} ${u.name}`} />
                        </td>
                        <td className="px-4 py-3 font-medium">{u.name}</td>
                        <td className="px-4 py-3">{u.username}</td>
                        <td className="px-4 py-3">{u.email}</td>
                        <td className="px-4 py-3">{roleName(u.role)}</td>
                        <td className="px-4 py-3">
                            <Badge tone={u.is_active ? 'green' : 'slate'}>{u.is_active ? t('Active') : t('Inactive')}</Badge>
                        </td>
                        <td className="space-x-3 px-4 py-3 text-end whitespace-nowrap">
                            <button
                                aria-label={t('Reset password')}
                                onClick={() => setResetting(u)}
                                className="text-slate-500 hover:text-fuchsia-700"
                            >
                                <KeyRound className="inline h-4 w-4" />
                            </button>
                            {u.id !== me && (
                                <button
                                    aria-label={u.is_active ? t('Deactivate') : t('Activate')}
                                    onClick={() => router.put(`/app/system/users/${u.id}/status`, {}, { preserveScroll: true })}
                                    className="text-slate-500 hover:text-amber-600"
                                >
                                    <Power className="inline h-4 w-4" />
                                </button>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
            <Pagination meta={pagination} label="accounts" onPage={(page) => go({ page })} onLimit={(limit) => go({ limit, page: 1 })} />
        </OfficePage>
    );
}
