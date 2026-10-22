import { PhotoUpload } from '@/components/avatar';
import { useI18n } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';

export type ProfileValues = {
    name: string;
    email: string;
    phone: string | null;
    alt_phone?: string | null;
    address?: string | null;
    emergency_name?: string | null;
    emergency_phone?: string | null;
    blood_group?: string | null;
    date_of_birth?: string | null;
    username?: string | null;
    class?: string;
    section?: string | null;
    roll_no?: string | null;
    session?: string;
};

const box = 'mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-indigo-500';
const bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

function Row({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block text-xs font-medium text-slate-600">
            {label}
            {children}
            {error && <span className="mt-1 block text-sm text-rose-600">{error}</span>}
        </label>
    );
}

/** One profile form for every role: contact details, emergency contact, photo and password. */
export function ProfileForm({
    profile,
    profileAction = '/profile',
    passwordAction = '/profile/password',
    showClass = true,
}: {
    profile: ProfileValues;
    profileAction?: string;
    passwordAction?: string;
    showClass?: boolean;
}) {
    const { t } = useI18n();
    const auth = (usePage().props as { auth?: { user?: { id: number; name: string; photo_url?: string | null } } }).auth?.user;
    const details = useForm({
        name: profile.name,
        email: profile.email,
        phone: profile.phone ?? '',
        alt_phone: profile.alt_phone ?? '',
        address: profile.address ?? '',
        emergency_name: profile.emergency_name ?? '',
        emergency_phone: profile.emergency_phone ?? '',
        blood_group: profile.blood_group ?? '',
        date_of_birth: profile.date_of_birth ?? '',
    });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const set = (k: keyof typeof details.data) => (e: { target: { value: string } }) => details.setData(k, e.target.value);

    const save = (event: FormEvent) => {
        event.preventDefault();
        details.post(profileAction, { preserveScroll: true });
    };
    const change = (event: FormEvent) => {
        event.preventDefault();
        password.post(passwordAction, { preserveScroll: true, onSuccess: () => password.reset() });
    };

    return (
        <div className="mx-auto max-w-3xl space-y-5">
            {auth && (
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 font-semibold">{t('Profile picture')}</h2>
                    <PhotoUpload userId={auth.id} name={auth.name} src={auth.photo_url} />
                </div>
            )}
            <form onSubmit={save} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="mb-4 font-semibold">{t('Personal details')}</h2>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <Row label={t('Name')} error={details.errors.name}>
                            <input value={details.data.name} onChange={set('name')} className={box} />
                        </Row>
                    </div>
                    {profile.username && (
                        <Row label={t('Username')}>
                            <input value={profile.username} disabled className={`${box} bg-slate-50 text-slate-500`} />
                        </Row>
                    )}
                    <Row label={t('Email')} error={details.errors.email}>
                        <input type="email" value={details.data.email} onChange={set('email')} className={box} />
                    </Row>
                    <Row label={t('Phone')} error={details.errors.phone}>
                        <input value={details.data.phone} onChange={set('phone')} className={box} dir="ltr" />
                    </Row>
                    <Row label={t('Alternative number')} error={details.errors.alt_phone}>
                        <input value={details.data.alt_phone} onChange={set('alt_phone')} className={box} dir="ltr" />
                    </Row>
                    <Row label={t('Date of birth')} error={details.errors.date_of_birth}>
                        <input type="date" value={details.data.date_of_birth} onChange={set('date_of_birth')} className={box} />
                    </Row>
                    <Row label={t('Blood group')} error={details.errors.blood_group}>
                        <select value={details.data.blood_group} onChange={set('blood_group')} className={box}>
                            <option value="">—</option>
                            {bloodGroups.map((b) => (
                                <option key={b}>{b}</option>
                            ))}
                        </select>
                    </Row>
                    <div className="sm:col-span-2">
                        <Row label={t('Address')} error={details.errors.address}>
                            <input value={details.data.address} onChange={set('address')} className={box} />
                        </Row>
                    </div>
                    <Row label={t('Emergency contact name')} error={details.errors.emergency_name}>
                        <input value={details.data.emergency_name} onChange={set('emergency_name')} className={box} />
                    </Row>
                    <Row label={t('Emergency contact number')} error={details.errors.emergency_phone}>
                        <input value={details.data.emergency_phone} onChange={set('emergency_phone')} className={box} dir="ltr" />
                    </Row>
                    {showClass && profile.class && (
                        <p className="text-sm text-slate-500 sm:col-span-2">
                            {profile.class}
                            {profile.section ? ` · ${t('Section')} ${profile.section}` : ''}
                            {profile.roll_no ? ` · ${t('Roll')} ${profile.roll_no}` : ''} · {t('Session')} {profile.session}
                        </p>
                    )}
                </div>
                <button disabled={details.processing} className="mt-4 h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">
                    {t('Save changes')}
                </button>
            </form>
            <form onSubmit={change} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="mb-4 font-semibold">{t('Change password')}</h2>
                <div className="grid gap-3">
                    <Row label={t('Current password')} error={password.errors.current_password}>
                        <input type="password" autoComplete="current-password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} className={box} />
                    </Row>
                    <Row label={t('New password')} error={password.errors.password}>
                        <input type="password" autoComplete="new-password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} className={box} />
                    </Row>
                    <Row label={t('Confirm new password')}>
                        <input type="password" autoComplete="new-password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} className={box} />
                    </Row>
                </div>
                <button disabled={password.processing} className="mt-4 h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">
                    {t('Change password')}
                </button>
            </form>
        </div>
    );
}
