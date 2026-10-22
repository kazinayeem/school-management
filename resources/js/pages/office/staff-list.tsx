import { Avatar } from '@/components/avatar';
import { Badge, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { Briefcase, Pencil, Power } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ExportMenu, HeaderCheckbox, Pagination, RowCheckbox, SelectionBanner, useBulkSelection, useListNav, useNavigating, type PaginationMeta } from '@/components/list-kit';

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
    role: string;
    photo_url: string | null;
};

export default function StaffList({
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
    counts: { active: number; inactive: number; teachers: number; staff: number };
    filters: { search: string; status: string; type: string; limit: number; sort: string; dir: string };
    me: number;
    pagination: PaginationMeta;
    exportColumns: Record<string, string>;
}) {
    const { t } = useI18n();
    const [q, setQ] = useState(filters.search);
    const go = useListNav(filters);
    const busy = useNavigating();
    const sel = useBulkSelection(rows.map((r) => r.id), pagination.total, `${filters.search}|${filters.status}|${filters.type}`);
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }
        const id = setTimeout(() => q !== filters.search && go({ search: q }), 350);

        return () => clearTimeout(id);
    }, [q]);

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
                <select
                    className={`${inputCls} w-44`}
                    value={filters.type}
                    onChange={(e) => go({ type: e.target.value })}
                    aria-label={t('Account type')}
                >
                    <option value="">{t('Teachers and staff')}</option>
                    <option value="teacher">{t('Teachers')}</option>
                    <option value="staff">{t('Office staff')}</option>
                </select>
                <select className={`${inputCls} w-44`} value={filters.status} onChange={(e) => go({ status: e.target.value })}>
                    <option value="">{t('All statuses')}</option>
                    <option value="active">{t('Active')}</option>
                    <option value="inactive">{t('Inactive')}</option>
                </select>
                <p className="text-sm text-slate-500">{t('{active} active, {inactive} inactive', counts)}</p>
                <span className="ms-auto flex gap-2">
                    <ExportMenu dataset="staff" columns={exportColumns} params={{ ...filters }} sel={sel} total={pagination.total} noun="staff" />
                    <Link href="/app/staff/new" className={primaryBtn}>
                        {t('Add Staff')}
                    </Link>
                </span>
            </div>
            <SelectionBanner sel={sel} pageCount={rows.length} total={pagination.total} noun="staff" />

            <Table
                busy={busy}
                headers={[<HeaderCheckbox key="all" sel={sel} label={t('Select all on this page')} />, 'Employee no.', 'Name', 'Role', 'Department', 'Phone', 'Username', 'Status', '']}
                empty={rows.length === 0 ? 'No staff found.' : null}
            >
                {rows.map((r) => (
                    <tr key={r.id} className={`hover:bg-slate-50 ${sel.isSelected(r.id) ? 'bg-fuchsia-50/50' : ''}`}>
                        <td className="px-4 py-3">
                            <RowCheckbox sel={sel} id={r.id} label={`${t('Select')} ${r.name}`} />
                        </td>
                        <td className="px-4 py-3 font-mono text-xs">{r.employee_no}</td>
                        <td className="px-4 py-3 font-medium">
                            <span className="flex items-center gap-2">
                                <Avatar name={r.name} src={r.photo_url} className="h-8 w-8 text-xs" />
                                {r.name}
                            </span>
                        </td>
                        <td className="px-4 py-3">
                            {r.designation}{' '}
                            <Badge tone={r.role === 'teacher' ? 'green' : 'slate'}>{t(r.role === 'teacher' ? 'Teacher' : 'Staff')}</Badge>
                        </td>
                        <td className="px-4 py-3">{r.department}</td>
                        <td className="px-4 py-3" dir="ltr">
                            {r.phone}
                        </td>
                        <td className="px-4 py-3">{r.username}</td>
                        <td className="px-4 py-3">
                            <Badge tone={r.is_active ? 'green' : 'slate'}>{r.is_active ? t('Active') : t('Inactive')}</Badge>
                        </td>
                        <td className="space-x-3 px-4 py-3 text-end whitespace-nowrap">
                            <Link aria-label={t('HR record: attendance, leave and salary')} title={t('HR record')} href={`/app/staff/${r.id}/hr`} className="text-slate-500 hover:text-fuchsia-700">
                                <Briefcase className="inline h-4 w-4" />
                            </Link>
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
            <Pagination meta={pagination} label="staff" onPage={(page) => go({ page })} onLimit={(limit) => go({ limit, page: 1 })} />
        </OfficePage>
    );
}
