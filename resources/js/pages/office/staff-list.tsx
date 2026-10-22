import { Badge, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { Pencil, Power } from 'lucide-react';
import { useState } from 'react';

type Row = {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    is_active: number;
    employee_no: string | null;
    designation: string | null;
    department: string | null;
};

export default function StaffList({
    navigation,
    roleLabel,
    rows,
    counts,
    filters,
    me,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    counts: { active: number; inactive: number };
    filters: { search: string; status: string };
    me: number;
}) {
    const { t } = useI18n();
    const [q, setQ] = useState(filters.search);
    const go = (next: Record<string, string>) => router.get('/app/staff', { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Staff List"
            subtitle="Teachers and staff, their details and whether they can sign in."
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
                        placeholder={t('Search name, username, number or role')}
                    />
                </form>
                <select className={`${inputCls} w-44`} value={filters.status} onChange={(e) => go({ status: e.target.value })}>
                    <option value="">{t('All staff')}</option>
                    <option value="active">{t('Active')}</option>
                    <option value="inactive">{t('Inactive')}</option>
                </select>
                <p className="text-sm text-slate-500">{t('{active} active, {inactive} inactive', counts)}</p>
                <Link href="/app/staff/new" className={`${primaryBtn} ms-auto`}>
                    {t('Add Staff')}
                </Link>
            </div>

            <Table
                headers={['Employee no.', 'Name', 'Role', 'Department', 'Phone', 'Username', 'Status', '']}
                empty={rows.length === 0 ? 'No staff found.' : null}
            >
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 font-mono text-xs">{r.employee_no}</td>
                        <td className="px-4 py-3 font-medium">{r.name}</td>
                        <td className="px-4 py-3">{r.designation}</td>
                        <td className="px-4 py-3">{r.department}</td>
                        <td className="px-4 py-3" dir="ltr">
                            {r.phone}
                        </td>
                        <td className="px-4 py-3">{r.username}</td>
                        <td className="px-4 py-3">
                            <Badge tone={r.is_active ? 'green' : 'slate'}>{r.is_active ? t('Active') : t('Inactive')}</Badge>
                        </td>
                        <td className="space-x-3 px-4 py-3 text-end whitespace-nowrap">
                            <Link aria-label={t('Edit')} href={`/app/staff/${r.id}`} className="text-slate-500 hover:text-fuchsia-700">
                                <Pencil className="inline h-4 w-4" />
                            </Link>
                            {r.id !== me && (
                                <button
                                    aria-label={r.is_active ? t('Deactivate') : t('Activate')}
                                    onClick={() => router.put(`/app/staff/${r.id}/status`, {}, { preserveScroll: true })}
                                    className="text-slate-500 hover:text-amber-600"
                                >
                                    <Power className="inline h-4 w-4" />
                                </button>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
