import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, GraduationCap, LoaderCircle, Lock, ShieldCheck, User, Users, BookOpen } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';

type Role = 'admin' | 'staff' | 'student' | 'parent';

const roles: { key: Role; label: string; icon: typeof User; demo: string }[] = [
    { key: 'admin', label: 'Admin', icon: ShieldCheck, demo: 'superadmin' },
    { key: 'staff', label: 'Staff', icon: Users, demo: 'staff' },
    { key: 'student', label: 'Student', icon: GraduationCap, demo: 'student' },
    { key: 'parent', label: 'Parent', icon: BookOpen, demo: 'parent' },
];

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        role: 'admin' as Role,
        remember: false as boolean,
    });
    const [show, setShow] = useState(false);
    const current = roles.find((r) => r.key === data.role)!;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const fillDemo = () => {
        setData((d) => ({ ...d, login: current.demo, password: 'Demo@1234' }));
    };

    return (
        <div className="grid min-h-screen bg-white lg:grid-cols-2">
            <Head title="Log in" />

            <div className="flex flex-col justify-center px-6 py-10 sm:px-14">
                <div className="mx-auto w-full max-w-md">
                    <div className="mb-8 flex items-center gap-2 text-xl font-bold text-fuchsia-800">
                        <GraduationCap className="h-7 w-7" /> Borno School
                    </div>

                    <p className="mb-3 text-center text-xs font-semibold tracking-widest text-fuchsia-700">SELECT YOUR ROLE</p>
                    <div className="mb-6 grid grid-cols-4 gap-2">
                        {roles.map(({ key, label, icon: Icon }) => (
                            <button
                                type="button"
                                key={key}
                                onClick={() => setData('role', key)}
                                className={`flex flex-col items-center gap-1 rounded-xl border px-2 py-3 text-sm font-medium transition ${
                                    data.role === key
                                        ? 'border-fuchsia-600 bg-fuchsia-50 text-fuchsia-800 shadow'
                                        : 'border-gray-200 text-gray-500 hover:border-fuchsia-300'
                                }`}
                            >
                                <Icon className="h-5 w-5" />
                                {label}
                            </button>
                        ))}
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-semibold text-gray-800" htmlFor="login">
                                Username or email
                            </label>
                            <div className="relative">
                                <User className="absolute top-3 left-3 h-4 w-4 text-gray-400" />
                                <input
                                    id="login"
                                    value={data.login}
                                    onChange={(e) => setData('login', e.target.value)}
                                    placeholder="Your Username or Email*"
                                    autoFocus
                                    className="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-3 pl-9 text-sm outline-none focus:border-fuchsia-500"
                                />
                            </div>
                            <InputError message={errors.login} />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-semibold text-gray-800" htmlFor="password">
                                Password
                            </label>
                            <div className="relative">
                                <Lock className="absolute top-3 left-3 h-4 w-4 text-gray-400" />
                                <input
                                    id="password"
                                    type={show ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="Your Password*"
                                    className="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-10 pl-9 text-sm outline-none focus:border-fuchsia-500"
                                />
                                <button type="button" onClick={() => setShow(!show)} className="absolute top-2.5 right-3 text-gray-400">
                                    {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                            <InputError message={errors.password} />
                        </div>

                        <button
                            type="button"
                            onClick={fillDemo}
                            className="flex w-full items-center gap-2 rounded-xl border border-fuchsia-100 bg-fuchsia-50 px-3 py-2 text-left text-xs text-gray-600"
                        >
                            <span className="font-semibold text-fuchsia-700">Demo:</span> {current.demo} · ••••••••
                        </button>

                        <div className="flex items-center justify-between text-sm">
                            <label className="flex items-center gap-2 text-gray-600">
                                <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                                Remember me
                            </label>
                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="text-fuchsia-700">
                                    Forgot password?
                                </TextLink>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-fuchsia-800 to-fuchsia-500 py-3 font-semibold text-white shadow-lg disabled:opacity-60"
                        >
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Login
                        </button>
                    </form>

                    {status && <div className="mt-4 text-center text-sm text-green-600">{status}</div>}
                </div>
            </div>

            <div className="hidden flex-col items-center justify-center bg-gradient-to-br from-fuchsia-900 via-fuchsia-700 to-fuchsia-500 p-12 text-center text-white lg:flex">
                <h2 className="text-5xl font-extrabold">Continue Managing.</h2>
                <p className="mt-4 max-w-sm text-fuchsia-100">Pick up right where you left off. Sign in to your school's all-in-one management platform.</p>
                <div className="mt-10 grid w-full max-w-md grid-cols-3 gap-3">
                    {[['24/7', 'Secure access'], ['10k+', 'Active students'], ['99.9%', 'Platform uptime']].map(([a, b]) => (
                        <div key={b} className="rounded-xl bg-white/15 p-4 backdrop-blur">
                            <div className="text-xl font-bold">{a}</div>
                            <div className="text-xs text-fuchsia-100">{b}</div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
