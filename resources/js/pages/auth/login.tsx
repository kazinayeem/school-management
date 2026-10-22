import { Head, useForm } from '@inertiajs/react';
import { Copy, Eye, EyeOff, GraduationCap, LoaderCircle, Lock, ShieldCheck, User, Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';

type Role = 'admin' | 'staff' | 'student' | 'parent';

const roles: { key: Role; label: string; icon: typeof User; demo: string; who: string }[] = [
    { key: 'admin', label: 'Admin', icon: ShieldCheck, demo: 'superadmin', who: 'Super Admin' },
    { key: 'staff', label: 'Staff', icon: Users, demo: 'staff', who: 'John Smith' },
    { key: 'student', label: 'Student', icon: GraduationCap, demo: 'student', who: 'Ethan Parker' },
    { key: 'parent', label: 'Parent', icon: User, demo: 'parent', who: 'Amelia Parker' },
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
    const current = roles.find((role) => role.key === data.role) ?? roles[0];

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const fillDemo = () => {
        setData((form) => ({ ...form, login: current.demo, password: 'Demo@1234' }));
    };

    const copyDemo = async () => {
        fillDemo();
        try {
            await navigator.clipboard.writeText(`${current.demo} / Demo@1234`);
        } catch {
            /* clipboard can be blocked; the fields are still filled */
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-[#f5f7fc] p-4 md:p-8" style={{ backgroundImage: 'radial-gradient(circle at 10% 10%, #fcf3fa 0, transparent 32%)' }}>
            <Head title="Log in" />
            <div className="grid w-full max-w-[1140px] overflow-hidden rounded-[28px] border border-indigo-100 bg-white shadow-[0_30px_80px_rgba(137,14,117,0.08)] lg:grid-cols-[540px_1fr]">
                <div className="flex flex-col justify-center bg-gradient-to-br from-white to-[#fbfbff] px-6 py-8 md:px-0 md:py-[38px]">
                    <div className="mx-auto w-full max-w-[460px]">
                        <div className="mb-8 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#f8e9f6] text-[#890e75]">
                                    <GraduationCap className="h-6 w-6" />
                                </span>
                                <span className="text-xl font-extrabold tracking-tight text-slate-900">Borno School</span>
                                <span className="rounded-full bg-[#f8e9f6] px-2 py-0.5 text-[11px] font-semibold text-[#890e75]">Demo</span>
                            </div>
                            <span className="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-500">EN</span>
                        </div>

                        <p className="mb-3 text-center text-xs font-bold tracking-[0.18em] text-[#890e75]">SELECT YOUR ROLE</p>
                        <div className="mb-6 grid grid-cols-4 gap-2">
                            {roles.map(({ key, label, icon: Icon }) => {
                                const active = data.role === key;

                                return (
                                    <button
                                        type="button"
                                        key={key}
                                        onClick={() => setData('role', key)}
                                        className={`relative flex flex-col items-center gap-1 rounded-2xl border px-2 py-3 text-sm font-semibold ${
                                            active ? 'border-[#b31299] bg-[#fdf4fb] text-[#890e75] shadow-[0_8px_20px_rgba(179,18,153,0.12)]' : 'border-slate-200 text-slate-400'
                                        }`}
                                    >
                                        {active && <span className="absolute top-2 right-2 h-1.5 w-1.5 rounded-full bg-[#b31299]" />}
                                        <span className={`flex h-8 w-8 items-center justify-center rounded-full ${active ? 'bg-white' : 'bg-slate-50'}`}>
                                            <Icon className="h-4 w-4" />
                                        </span>
                                        {label}
                                    </button>
                                );
                            })}
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-semibold" htmlFor="login">
                                    Username or email
                                </label>
                                <div className="relative">
                                    <User className="absolute top-3 left-3 h-4 w-4 text-slate-400" />
                                    <input
                                        id="login"
                                        value={data.login}
                                        onChange={(event) => setData('login', event.target.value)}
                                        placeholder="Your Username or Email*"
                                        autoFocus
                                        required
                                        className="h-11 w-full rounded-2xl border border-slate-200 bg-[#f7f8fc] pr-3 pl-9 text-sm outline-none focus:border-[#b31299]"
                                    />
                                </div>
                                <InputError message={errors.login} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-semibold" htmlFor="password">
                                    Password
                                </label>
                                <div className="relative">
                                    <Lock className="absolute top-3 left-3 h-4 w-4 text-slate-400" />
                                    <input
                                        id="password"
                                        type={show ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(event) => setData('password', event.target.value)}
                                        placeholder="Your Password*"
                                        required
                                        className="h-11 w-full rounded-2xl border border-slate-200 bg-[#f7f8fc] pr-10 pl-9 text-sm outline-none focus:border-[#b31299]"
                                    />
                                    <button type="button" aria-label={show ? 'Hide password' : 'Show password'} onClick={() => setShow(!show)} className="absolute top-3 right-3 text-slate-400">
                                        {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center gap-2 rounded-2xl border border-dashed border-fuchsia-200 bg-[#fff7fc] px-3 py-2 text-xs">
                                <button type="button" onClick={fillDemo} className="flex-1 text-left text-slate-600">
                                    <span className="font-bold text-[#890e75]">Demo:</span> {current.demo} · •••••••• · {current.who}
                                </button>
                                <button type="button" aria-label="Fill demo credentials" onClick={copyDemo} className="text-[#890e75]">
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>

                            <div className="flex items-center justify-between text-sm">
                                <button type="button" onClick={() => setData('remember', !data.remember)} className="flex items-center gap-2 text-slate-600">
                                    <span className={`relative h-5 w-9 rounded-full ${data.remember ? 'bg-[#b31299]' : 'bg-slate-200'}`}>
                                        <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${data.remember ? 'left-4' : 'left-0.5'}`} />
                                    </span>
                                    Remember me
                                </button>
                                {canResetPassword && (
                                    <TextLink href={route('password.request')} className="font-semibold text-[#890e75] no-underline">
                                        Forgot password?
                                    </TextLink>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="flex h-12 w-full items-center justify-center gap-2 rounded-2xl text-base font-semibold text-white shadow-[0_14px_28px_rgba(179,18,153,0.29)] disabled:opacity-60"
                                style={{ backgroundImage: 'linear-gradient(100deg, #890e75, #ec46d1)' }}
                            >
                                {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Lock className="h-4 w-4" />}
                                {processing ? 'Signing in...' : 'Login'}
                                {!processing && <span aria-hidden>›</span>}
                            </button>
                        </form>
                        {status && <div className="mt-4 text-center text-sm text-green-600">{status}</div>}
                    </div>
                </div>

                <div className="relative hidden overflow-hidden text-white lg:flex lg:flex-col" style={{ backgroundImage: 'linear-gradient(145deg, #890e75 0%, #b31299 50%, #ec46d1 100%)' }}>
                    <div className="absolute -top-16 right-10 h-72 w-72 rounded-full bg-white/20 blur-2xl" />
                    <div className="relative flex h-full flex-col justify-between p-12">
                        <div>
                            <p className="text-sm font-semibold text-white/80">School Management Simplified</p>
                            <h2 className="mt-6 text-5xl leading-tight font-extrabold">
                                Continue
                                <br />
                                <em className="font-serif">Managing.</em>
                            </h2>
                            <p className="mt-4 max-w-sm text-sm text-white/85">Pick up right where you left off. Sign in to your school's all-in-one management platform.</p>
                            <div className="mt-8 flex items-center gap-3 text-sm">
                                <span className="h-2 w-2 rounded-full bg-emerald-300" />
                                Systems online
                                <strong className="ml-auto">99.9%</strong>
                            </div>
                        </div>
                        <div>
                            <div className="mb-6 flex items-center gap-2">
                                {['A', 'S'].map((letter) => (
                                    <span key={letter} className="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-sm font-bold">
                                        {letter}
                                    </span>
                                ))}
                                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-white text-sm font-bold text-[#890e75]">+8</span>
                                <span className="ml-2 text-sm">Team connected</span>
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                {[
                                    ['24/7', 'Secure access'],
                                    ['10k+', 'Active students'],
                                    ['99.9%', 'Platform uptime'],
                                ].map(([value, label]) => (
                                    <div key={label} className="rounded-2xl bg-white/15 p-4 backdrop-blur">
                                        <div className="text-xl font-bold">{value}</div>
                                        <div className="text-xs text-white/80">{label}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
