import { Avatar } from '@/components/avatar';
import { Badge, Card, Field, ghostBtn, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { PickedStudent, StudentPicker } from '@/components/student-picker';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Power } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import { ExportMenu, HeaderCheckbox, Pagination, RowCheckbox, SelectionBanner, useBulkSelection, useListNav, useNavigating, type PaginationMeta } from '@/components/list-kit';

type Child = {
    id: number;
    name: string;
    admission_no: string | null;
    roll_no?: string | null;
    class_name: string | null;
    section: string | null;
    photo_url?: string | null;
};
type Parent = { id: number; name: string; username: string; email: string; phone: string | null; is_active: number; children: Child[] };

function ParentForm({ parent, classes, onDone }: { parent: Parent | null; classes: string[]; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm({
        name: parent?.name ?? '',
        username: parent?.username ?? '',
        email: parent?.email ?? '',
        phone: parent?.phone ?? '',
        password: '',
        children: '',
    });
    const [kids, setKids] = useState<PickedStudent[]>(
        (parent?.children ?? []).map((c) => ({
            id: c.id,
            name: c.name,
            admission_no: c.admission_no,
            roll_no: c.roll_no ?? null,
            class_name: c.class_name,
            section: c.section,
            photo: c.photo_url ?? null,
        })),
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: onDone };
        form.transform((d) => ({
            ...d,
            children: kids
                .map((k) => k.admission_no)
                .filter(Boolean)
                .join(', '),
        }));
        if (parent) form.put(`/app/system/parents/${parent.id}`, done);
        else form.post('/app/system/parents', done);
    };

    return (
        <form onSubmit={submit}>
            <Card className="space-y-4 border-fuchsia-200">
                <h2 className="font-semibold">{parent ? t('Edit parent account') : t('New parent account')}</h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Full name" error={form.errors.name}>
                        <input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Phone" error={form.errors.phone}>
                        <input className={inputCls} dir="ltr" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                    </Field>
                    <Field label={parent ? 'Username' : 'Username (optional)'} error={form.errors.username}>
                        <input className={inputCls} value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <input className={inputCls} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    </Field>
                    <Field label={parent ? 'New password (leave empty to keep)' : 'Password'} error={form.errors.password}>
                        <input
                            type="password"
                            autoComplete="new-password"
                            className={inputCls}
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                    </Field>
                    <StudentPicker selected={kids} onChange={setKids} />
                    {form.errors.children && <p className="text-xs text-red-600 md:col-span-2">{t(form.errors.children)}</p>}
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

export default function Parents({
    navigation,
    roleLabel,
    rows,
    search,
    classes,
    pagination,
    filters,
    exportColumns,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Parent[];
    search: string;
    classes: string[];
    pagination: PaginationMeta;
    filters: { limit: number; sort: string; dir: string };
    exportColumns: Record<string, string>;
}) {
    const { t } = useI18n();
    const [editing, setEditing] = useState<Parent | 'new' | null>(null);
    const [q, setQ] = useState(search);
    const base = { search, ...filters };
    const go = useListNav(base);
    const busy = useNavigating();
    const sel = useBulkSelection(rows.map((r) => r.id), pagination.total, search);
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }
        const id = setTimeout(() => q !== search && go({ search: q }), 350);

        return () => clearTimeout(id);
    }, [q]);

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Parent Accounts"
            subtitle="Parents who can sign in to follow their children's progress."
            wide
        >
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
                        placeholder={t('Search name, username or phone')}
                    />
                </form>
                <span className="ms-auto flex gap-2">
                    <ExportMenu dataset="parents" columns={exportColumns} params={base} sel={sel} total={pagination.total} noun="parents" />
                    <button onClick={() => setEditing('new')} className={primaryBtn}>
                        {t('New parent account')}
                    </button>
                </span>
            </div>
            <SelectionBanner sel={sel} pageCount={rows.length} total={pagination.total} noun="parents" />
            {editing && (
                <ParentForm
                    key={editing === 'new' ? 'new' : editing.id}
                    parent={editing === 'new' ? null : editing}
                    classes={classes}
                    onDone={() => setEditing(null)}
                />
            )}

            <Table busy={busy} headers={[<HeaderCheckbox key="all" sel={sel} label={t('Select all on this page')} />, 'Name', 'Username', 'Phone', 'Children', 'Status', '']} empty={rows.length === 0 ? 'No parent accounts found.' : null}>
                {rows.map((p) => (
                    <tr key={p.id} className={`hover:bg-slate-50 ${sel.isSelected(p.id) ? 'bg-fuchsia-50/50' : ''}`}>
                        <td className="px-4 py-3">
                            <RowCheckbox sel={sel} id={p.id} label={`${t('Select')} ${p.name}`} />
                        </td>
                        <td className="px-4 py-3 font-medium">{p.name}</td>
                        <td className="px-4 py-3">{p.username}</td>
                        <td className="px-4 py-3" dir="ltr">
                            {p.phone}
                        </td>
                        <td className="px-4 py-3">
                            {p.children.length === 0 ? (
                                <span className="text-slate-400">—</span>
                            ) : (
                                p.children.map((c) => (
                                    <div key={c.id} className="flex items-center gap-2 py-0.5">
                                        <Avatar name={c.name} src={c.photo_url} className="h-6 w-6 text-[10px]" />
                                        <span>
                                            {c.name}{' '}
                                            <span className="text-xs text-slate-400">
                                                {[c.class_name ? t(c.class_name) : '', c.section].filter(Boolean).join(' ')}
                                            </span>
                                        </span>
                                    </div>
                                ))
                            )}
                        </td>
                        <td className="px-4 py-3">
                            <Badge tone={p.is_active ? 'green' : 'slate'}>{p.is_active ? t('Active') : t('Inactive')}</Badge>
                        </td>
                        <td className="space-x-3 px-4 py-3 text-end whitespace-nowrap">
                            <button aria-label={t('Edit')} onClick={() => setEditing(p)} className="text-slate-500 hover:text-fuchsia-700">
                                <Pencil className="inline h-4 w-4" />
                            </button>
                            <button
                                aria-label={p.is_active ? t('Deactivate') : t('Activate')}
                                onClick={() => router.put(`/app/system/users/${p.id}/status`, {}, { preserveScroll: true })}
                                className="text-slate-500 hover:text-amber-600"
                            >
                                <Power className="inline h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>
            <Pagination meta={pagination} label="parents" onPage={(page) => go({ page })} onLimit={(limit) => go({ limit, page: 1 })} />
        </OfficePage>
    );
}
