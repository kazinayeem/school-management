import { Badge, Card, Field, ghostBtn, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Power } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Child = { id: number; name: string; admission_no: string | null; class_name: string | null; section: string | null };
type Parent = { id: number; name: string; username: string; email: string; phone: string | null; is_active: number; children: Child[] };

function ParentForm({ parent, onDone }: { parent: Parent | null; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm({
        name: parent?.name ?? '',
        username: parent?.username ?? '',
        email: parent?.email ?? '',
        phone: parent?.phone ?? '',
        password: '',
        children: (parent?.children ?? []).map((c) => c.admission_no).join(', '),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: onDone };
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
                        <input type="password" autoComplete="new-password" className={inputCls} value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                    </Field>
                    <Field label="Children's admission numbers" error={form.errors.children}>
                        <input className={inputCls} dir="ltr" value={form.data.children} onChange={(e) => form.setData('children', e.target.value)} placeholder="2026-00001, 2026-00002" />
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

export default function Parents({ navigation, roleLabel, rows, search }: { navigation: Nav; roleLabel: string; rows: Parent[]; search: string }) {
    const { t } = useI18n();
    const [editing, setEditing] = useState<Parent | 'new' | null>(null);
    const [q, setQ] = useState(search);

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Parent Accounts" subtitle="Parents who can sign in to follow their children's progress." wide>
            <div className="flex flex-wrap items-end gap-4">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/app/system/parents', { search: q }, { preserveState: true, replace: true });
                    }}
                >
                    <input className={`${inputCls} w-72`} value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Search name, username or phone')} />
                </form>
                <button onClick={() => setEditing('new')} className={`${primaryBtn} ms-auto`}>
                    {t('New parent account')}
                </button>
            </div>
            {editing && <ParentForm key={editing === 'new' ? 'new' : editing.id} parent={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />}

            <Table headers={['Name', 'Username', 'Phone', 'Children', 'Status', '']} empty={rows.length === 0 ? 'No parent accounts found.' : null}>
                {rows.map((p) => (
                    <tr key={p.id} className="hover:bg-slate-50">
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
                                    <div key={c.id}>
                                        {c.name} <span className="text-xs text-slate-400">{[c.class_name ? t(c.class_name) : '', c.section].filter(Boolean).join(' ')}</span>
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
                            <button aria-label={p.is_active ? t('Deactivate') : t('Activate')} onClick={() => router.put(`/app/system/users/${p.id}/status`, {}, { preserveScroll: true })} className="text-slate-500 hover:text-amber-600">
                                <Power className="inline h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
