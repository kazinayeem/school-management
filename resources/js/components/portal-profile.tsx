import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export function ProfileForm({
    profile,
    profileAction,
    passwordAction,
    showClass = true,
}: {
    profile: { name: string; email: string; phone: string | null; class?: string; section?: string | null; roll_no?: string | null; session?: string };
    profileAction: string;
    passwordAction: string;
    showClass?: boolean;
}) {
    const details = useForm({ name: profile.name, email: profile.email, phone: profile.phone ?? '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

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
            <form onSubmit={save} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="mb-4 font-semibold">Personal details</h2>
                <div className="grid gap-3 sm:grid-cols-2">
                    <label className="block text-xs font-medium text-slate-600 sm:col-span-2">
                        Name
                        <input value={details.data.name} onChange={(event) => details.setData('name', event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-indigo-500" />
                    </label>
                    <label className="block text-xs font-medium text-slate-600">
                        Email
                        <input type="email" value={details.data.email} onChange={(event) => details.setData('email', event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-indigo-500" />
                    </label>
                    <label className="block text-xs font-medium text-slate-600">
                        Phone
                        <input value={details.data.phone} onChange={(event) => details.setData('phone', event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-indigo-500" />
                    </label>
                    {showClass && (
                        <p className="text-sm text-slate-500 sm:col-span-2">
                            {profile.class}
                            {profile.section ? ` · Section ${profile.section}` : ''}
                            {profile.roll_no ? ` · Roll ${profile.roll_no}` : ''} · Session {profile.session}
                        </p>
                    )}
                </div>
                {details.errors.name && <p className="mt-2 text-sm text-rose-600">{details.errors.name}</p>}
                {details.errors.email && <p className="mt-2 text-sm text-rose-600">{details.errors.email}</p>}
                <button disabled={details.processing} className="mt-4 h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">
                    Save changes
                </button>
            </form>
            <form onSubmit={change} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="mb-4 font-semibold">Change password</h2>
                <div className="grid gap-3">
                    <label className="block text-xs font-medium text-slate-600">
                        Current password
                        <input type="password" value={password.data.current_password} onChange={(event) => password.setData('current_password', event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-indigo-500" />
                    </label>
                    <label className="block text-xs font-medium text-slate-600">
                        New password
                        <input type="password" value={password.data.password} onChange={(event) => password.setData('password', event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-indigo-500" />
                    </label>
                    <label className="block text-xs font-medium text-slate-600">
                        Confirm new password
                        <input type="password" value={password.data.password_confirmation} onChange={(event) => password.setData('password_confirmation', event.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-indigo-500" />
                    </label>
                </div>
                {password.errors.current_password && <p className="mt-2 text-sm text-rose-600">{password.errors.current_password}</p>}
                {password.errors.password && <p className="mt-2 text-sm text-rose-600">{password.errors.password}</p>}
                <button disabled={password.processing} className="mt-4 h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">
                    Change password
                </button>
            </form>
        </div>
    );
}
