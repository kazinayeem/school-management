import { PhotoUpload } from '@/components/avatar';
import { Card, Field, ghostBtn, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Staff = {
    role?: string;
    photo?: string | null;
    photo_url?: string | null;
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    is_active: number;
    employee_no: string | null;
    designation: string | null;
    department: string | null;
    joined_on: string | null;
    address: string | null;
};

export default function StaffForm({ navigation, roleLabel, staff }: { navigation: Nav; roleLabel: string; staff: Staff | null }) {
    const { t } = useI18n();
    const form = useForm({
        name: staff?.name ?? '',
        role: staff?.role ?? 'teacher',
        username: staff?.username ?? '',
        email: staff?.email ?? '',
        phone: staff?.phone ?? '',
        password: '',
        designation: staff?.designation ?? 'Teacher',
        department: staff?.department ?? '',
        joined_on: staff?.joined_on ?? '',
        address: staff?.address ?? '',
        is_active: staff ? Boolean(staff.is_active) : true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (staff) form.put(`/app/staff/${staff.id}`);
        else form.post('/app/staff');
    };
    const text = (name: keyof typeof form.data, label: string, type = 'text') => (
        <Field label={label} error={form.errors[name as keyof typeof form.errors]}>
            <input
                type={type}
                className={inputCls}
                value={String(form.data[name] ?? '')}
                onChange={(e) => form.setData(name, e.target.value as never)}
            />
        </Field>
    );

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title={staff ? 'Edit Staff' : 'Add Staff'}
            subtitle={staff ? `${staff.employee_no ?? ''} · ${staff.name}` : 'Create a login for a teacher or other staff member.'}
        >
            <form onSubmit={submit}>
                <Card className="space-y-5">
                    {staff ? (
                        <PhotoUpload userId={staff.id} name={staff.name} src={staff.photo_url} />
                    ) : (
                        <p className="text-xs text-slate-500">{t('Save first, then you can add a photo.')}</p>
                    )}
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Account type" error={form.errors.role}>
                            <select className={inputCls} value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                                <option value="teacher">{t('Teacher')}</option>
                                <option value="staff">{t('Staff (office)')}</option>
                            </select>
                        </Field>
                        {text('name', 'Full name')}
                        {text('designation', 'Role')}
                        {text('department', 'Department')}
                        {text('phone', 'Phone')}
                        {text('joined_on', 'Joining date', 'date')}
                        {text('address', 'Address')}
                        {text('username', staff ? 'Username' : 'Username (optional)')}
                        {text('email', 'Email')}
                        <Field label={staff ? 'New password (leave empty to keep)' : 'Password'} error={form.errors.password}>
                            <input
                                type="password"
                                autoComplete="new-password"
                                className={inputCls}
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </Field>
                        <label className="flex items-center gap-2 pt-6 text-sm font-medium">
                            <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                            {t('Can sign in')}
                        </label>
                    </div>
                    {form.errors.is_active && <p className="text-sm text-red-600">{t(form.errors.is_active)}</p>}
                    <div className="flex gap-3">
                        <button disabled={form.processing} className={primaryBtn}>
                            {t('Save')}
                        </button>
                        <Link href="/app/staff" className={ghostBtn}>
                            {t('Cancel')}
                        </Link>
                    </div>
                </Card>
            </form>
        </OfficePage>
    );
}
