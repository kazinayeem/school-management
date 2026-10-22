import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronRight, Copy, Eye, EyeOff, GraduationCap, Languages as LanguagesIcon, Lock, ShieldCheck, User, Users, X } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

import { Branding, Language, SharedData } from '@/types';

type Role = 'admin' | 'staff' | 'student' | 'parent';

const roles: { value: Role; label: string; icon: typeof User }[] = [
    { value: 'admin', label: 'Admin', icon: ShieldCheck },
    { value: 'staff', label: 'Staff', icon: Users },
    { value: 'student', label: 'Student', icon: GraduationCap },
    { value: 'parent', label: 'Parent', icon: Users },
];

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { branding } = usePage<SharedData & { branding: Branding }>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        role: 'admin' as Role,
        remember: false as boolean,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [copied, setCopied] = useState(false);
    const [dismissedError, setDismissedError] = useState(false);
    const [logoFailed, setLogoFailed] = useState(false);
    const [languageOpen, setLanguageOpen] = useState(false);
    const [locale, setLocale] = useState('en');

    const current = branding.demo[data.role] ?? branding.demo.admin;
    const error = errors.login || errors.password || errors.role;

    useEffect(() => {
        setDismissedError(false);
    }, [errors.login, errors.password, errors.role]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const fillDemo = async () => {
        setData((form) => ({ ...form, login: current.username, password: current.password }));
        setCopied(true);
        try {
            await navigator.clipboard.writeText(`${current.username} / ${current.password}`);
        } catch {
            /* clipboard can be blocked; the fields are still filled */
        }
        window.setTimeout(() => setCopied(false), 1500);
    };

    const chooseLanguage = (language: Language) => {
        setLocale(language.code);
        setLanguageOpen(false);
        document.documentElement.lang = language.code;
        document.documentElement.dir = language.is_rtl ? 'rtl' : 'ltr';
    };

    const activeLanguage = branding.languages.find((language) => language.code === locale) ?? branding.languages[0];

    return (
        <div
            className="flex min-h-screen items-center justify-center p-4 md:p-8"
            style={{
                background: 'radial-gradient(circle at 10% 10%, var(--color-indigo-50) 0, transparent 32%), #f5f7fc',
            }}
        >
            <Head title="Log in" />

            <div
                className="relative grid w-full max-w-[1140px] grid-cols-1 overflow-hidden rounded-[28px] border border-indigo-600/10 bg-white antialiased lg:grid-cols-[540px_1fr]"
                style={{
                    minHeight: '630px',
                    boxShadow: '0 25px 60px rgba(15,23,42,.10), 0 10px 30px color-mix(in srgb, var(--color-indigo-600) 8%, transparent)',
                }}
            >
                <div className="flex flex-col justify-center overflow-y-auto bg-gradient-to-br from-white to-[#fbfbff] px-6 py-8 md:px-0 md:py-[38px]">
                    <div className="mx-auto w-full md:w-[min(460px,calc(100%-64px))]">
                        <div className="mb-[42px] flex items-center gap-2.5">
                            {!logoFailed ? (
                                <img
                                    src={branding.logo}
                                    alt={`${branding.name} logo`}
                                    className="h-[42px] w-auto max-w-[240px] shrink-0 object-contain object-left"
                                    onError={() => setLogoFailed(true)}
                                />
                            ) : (
                                <>
                                    <div
                                        className="flex h-[42px] w-[42px] shrink-0 items-center justify-center overflow-hidden rounded-xl"
                                        style={{
                                            background: 'linear-gradient(135deg, var(--color-indigo-600), var(--color-indigo-500))',
                                            boxShadow: '0 8px 20px color-mix(in srgb, var(--color-indigo-600) 30%, transparent)',
                                        }}
                                    >
                                        <GraduationCap className="h-[23px] w-[23px] text-white" strokeWidth={1.8} />
                                    </div>
                                    <span
                                        className="max-w-[230px] min-w-0 truncate text-[21px] font-extrabold"
                                        style={{ color: 'var(--color-indigo-600)' }}
                                        title={branding.name}
                                    >
                                        {branding.name || 'School Management System'}
                                    </span>
                                </>
                            )}

                            <span
                                className="shrink-0 rounded-lg px-2 py-1 text-[10px] font-medium"
                                style={{ background: 'var(--color-indigo-50)', color: 'var(--color-indigo-600)' }}
                            >
                                Demo
                            </span>

                            <div className="ms-auto shrink-0">
                                <div className="relative">
                                    <button
                                        type="button"
                                        aria-label="Change language"
                                        onClick={() => setLanguageOpen((open) => !open)}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
                                    >
                                        <LanguagesIcon className="h-4 w-4 shrink-0 text-indigo-600" />
                                        <span className="sm:hidden">{activeLanguage?.flag ?? locale.toUpperCase()}</span>
                                        <span className="hidden sm:inline">
                                            {activeLanguage?.flag} {activeLanguage?.name ?? locale.toUpperCase()}
                                        </span>
                                        <ChevronRight className="hidden h-3 w-3 shrink-0 text-slate-400 sm:block" />
                                    </button>

                                    {languageOpen && (
                                        <>
                                            <div className="fixed inset-0 z-40" onClick={() => setLanguageOpen(false)} />
                                            <div className="absolute end-0 top-full z-50 mt-1 w-44 max-w-[calc(100vw-1rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                                {branding.languages.map((language) => (
                                                    <button
                                                        key={language.code}
                                                        type="button"
                                                        onClick={() => chooseLanguage(language)}
                                                        className={`flex w-full items-center gap-2 px-3 py-2 text-start text-sm hover:bg-slate-50 ${
                                                            language.code === locale ? 'font-semibold text-indigo-700' : 'text-slate-600'
                                                        }`}
                                                    >
                                                        <span>{language.flag}</span>
                                                        <span className="flex-1">{language.name}</span>
                                                        {language.code === locale && <Check className="h-3.5 w-3.5 text-indigo-600" />}
                                                    </button>
                                                ))}
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="mb-[22px]" />

                        <form className="space-y-3.5" onSubmit={submit}>
                            <div className="mb-[4px]">
                                <p
                                    className="mb-3 text-center text-[11px] font-bold tracking-[0.08em] uppercase"
                                    style={{ color: 'var(--color-indigo-600)' }}
                                >
                                    Select Your Role
                                </p>
                                <div className="grid grid-cols-4 gap-2">
                                    {roles.map(({ value, label, icon: Icon }) => {
                                        const active = data.role === value;

                                        return (
                                            <label
                                                key={value}
                                                onClick={() => setData('role', value)}
                                                className="relative flex h-[78px] cursor-pointer flex-col items-center justify-center gap-1.5 rounded-[15px] border px-1.5 transition-all duration-200"
                                                style={
                                                    active
                                                        ? {
                                                              borderColor: 'var(--color-indigo-600)',
                                                              color: 'var(--color-indigo-600)',
                                                              background: 'linear-gradient(145deg, var(--color-indigo-50), #fff)',
                                                              boxShadow: '0 10px 28px color-mix(in srgb, var(--color-indigo-600) 12%, transparent)',
                                                          }
                                                        : { borderColor: '#e2e8f0', color: '#8693aa' }
                                                }
                                            >
                                                <input
                                                    type="radio"
                                                    value={value}
                                                    checked={active}
                                                    onChange={() => setData('role', value)}
                                                    className="absolute opacity-0"
                                                />
                                                {active && (
                                                    <span
                                                        className="absolute top-2 right-2 h-[7px] w-[7px] rounded-full"
                                                        style={{
                                                            background: 'var(--color-indigo-600)',
                                                            boxShadow: '0 0 0 4px var(--color-indigo-100)',
                                                        }}
                                                    />
                                                )}
                                                <span
                                                    className="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[10px]"
                                                    style={{ background: 'var(--color-indigo-50)' }}
                                                >
                                                    <Icon className="h-[17px] w-[17px]" strokeWidth={1.8} />
                                                </span>
                                                <strong className="text-center text-[14px] leading-tight font-semibold">{label}</strong>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="mt-4">
                                <label htmlFor="identity" className="mb-2 block text-sm font-bold" style={{ color: '#334155' }}>
                                    Username or email
                                </label>
                                <div
                                    className="flex h-[52px] items-center rounded-2xl border-[1.5px] px-4 transition-all duration-200 focus-within:bg-white"
                                    style={{ borderColor: '#e2e8f0', background: '#f8fafc' }}
                                >
                                    <User className="mr-3 h-5 w-5 shrink-0" style={{ color: '#94a3b8' }} strokeWidth={1.8} />
                                    <input
                                        id="identity"
                                        type="text"
                                        required
                                        autoComplete="username"
                                        placeholder="Your Username or Email*"
                                        value={data.login}
                                        onChange={(event) => setData('login', event.target.value)}
                                        className="flex-1 bg-transparent text-[15px] font-medium placeholder-[#94a3b8] outline-none"
                                        style={{ color: '#1e293b' }}
                                    />
                                </div>
                            </div>

                            <div>
                                <label htmlFor="password" className="mb-2 block text-sm font-bold" style={{ color: '#334155' }}>
                                    Password
                                </label>
                                <div
                                    className="flex h-[52px] items-center rounded-2xl border-[1.5px] px-4 transition-all duration-200 focus-within:bg-white"
                                    style={{ borderColor: '#e2e8f0', background: '#f8fafc' }}
                                >
                                    <Lock className="mr-3 h-5 w-5 shrink-0" style={{ color: '#94a3b8' }} strokeWidth={1.8} />
                                    <input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        required
                                        autoComplete="current-password"
                                        placeholder="Your Password*"
                                        value={data.password}
                                        onChange={(event) => setData('password', event.target.value)}
                                        className="flex-1 bg-transparent text-[15px] font-medium placeholder-[#94a3b8] outline-none"
                                        style={{ color: '#1e293b' }}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((shown) => !shown)}
                                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[11px] transition-colors"
                                        style={{ color: '#94a3b8' }}
                                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                                    >
                                        {showPassword ? (
                                            <EyeOff className="h-[19px] w-[19px]" strokeWidth={1.8} />
                                        ) : (
                                            <Eye className="h-[19px] w-[19px]" strokeWidth={1.8} />
                                        )}
                                    </button>
                                </div>
                            </div>

                            <div
                                className="flex items-center justify-between gap-2 rounded-[11px] border border-dashed px-3 py-2.5"
                                style={{ borderColor: 'var(--color-indigo-200)', background: 'var(--color-indigo-50)' }}
                            >
                                <div className="flex min-w-0 items-center gap-1.5 text-[14px]" style={{ color: '#62708a' }}>
                                    <span className="shrink-0 font-bold" style={{ color: 'var(--color-indigo-600)' }}>
                                        Demo:
                                    </span>
                                    <span className="truncate font-mono">{current.username}</span>
                                    <span className="opacity-40">•</span>
                                    <span className="truncate font-mono">••••••••</span>
                                    <span className="hidden opacity-40 sm:inline">•</span>
                                    <span className="hidden truncate sm:inline">{current.name}</span>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={fillDemo}
                                        className="rounded-md p-1 transition-colors"
                                        style={{ color: 'var(--color-indigo-600)' }}
                                        title="Fill demo credentials"
                                    >
                                        {copied ? <Check className="h-3.5 w-3.5 text-green-500" /> : <Copy className="h-3.5 w-3.5" />}
                                    </button>
                                </div>
                            </div>

                            <div className="flex items-center justify-between pt-0.5 text-[11px]">
                                <label className="flex cursor-pointer items-center gap-2 select-none" style={{ color: '#60708a' }}>
                                    <span
                                        className="relative h-[19px] w-[34px] cursor-pointer rounded-full transition-all duration-200"
                                        style={{ background: data.remember ? 'var(--color-indigo-600)' : '#dce2ec' }}
                                        onClick={() => setData('remember', !data.remember)}
                                    >
                                        <span
                                            className={`absolute top-0.5 left-0.5 h-[15px] w-[15px] rounded-full bg-white shadow-[0_2px_5px_rgba(0,0,0,0.2)] transition-all duration-200 ${
                                                data.remember ? 'translate-x-[15px]' : 'translate-x-0'
                                            }`}
                                        />
                                    </span>
                                    <span className="font-medium">Remember me</span>
                                </label>

                                {canResetPassword && (
                                    <Link href={route('password.request')} className="font-bold" style={{ color: 'var(--color-indigo-600)' }}>
                                        Forgot password?
                                    </Link>
                                )}
                            </div>

                            {error && !dismissedError && (
                                <div
                                    className="animate-shake flex items-start justify-between gap-3 rounded-xl px-3 py-2.5 text-xs"
                                    style={{ background: '#fdf1f2', border: '1px solid #f7d4d8', color: '#e75465' }}
                                >
                                    <div className="flex items-start gap-2">
                                        <X className="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>{error}</span>
                                    </div>
                                    <button type="button" onClick={() => setDismissedError(true)} className="shrink-0" aria-label="Dismiss error">
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            )}

                            {status && <div className="text-center text-xs text-green-600">{status}</div>}

                            <button
                                type="submit"
                                disabled={processing}
                                className="flex h-[52px] w-full items-center justify-center gap-2.5 rounded-2xl text-sm font-extrabold text-white transition-all duration-200 hover:-translate-y-0.5 disabled:opacity-60"
                                style={{
                                    background: 'linear-gradient(100deg, var(--color-indigo-700), var(--color-indigo-500))',
                                    boxShadow: '0 14px 28px color-mix(in srgb, var(--color-indigo-600) 29%, transparent)',
                                }}
                            >
                                <Lock className="h-[18px] w-[18px]" strokeWidth={2} />
                                {processing ? 'Signing in...' : 'Login'}
                                <ChevronRight className="h-[18px] w-[18px]" strokeWidth={2} />
                            </button>
                        </form>
                    </div>
                </div>

                <div
                    className="relative hidden overflow-hidden rounded-r-[30px] text-white lg:flex lg:flex-col"
                    style={{
                        background: 'linear-gradient(145deg, var(--color-indigo-700) 0%, var(--color-indigo-600) 50%, var(--color-indigo-500) 100%)',
                    }}
                >
                    <div
                        className="absolute rounded-full opacity-30 blur-[5px]"
                        style={{ width: 350, height: 350, background: 'var(--color-indigo-300)', right: -120, top: -130 }}
                    />
                    <div
                        className="absolute rounded-full opacity-30 blur-[5px]"
                        style={{ width: 280, height: 280, background: 'var(--color-indigo-800)', left: -100, bottom: -100 }}
                    />
                    <div
                        className="absolute inset-0"
                        style={{
                            backgroundImage:
                                'linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px)',
                            backgroundSize: '40px 40px',
                            maskImage: 'linear-gradient(to bottom, transparent, black 30%, black 80%, transparent)',
                        }}
                    />

                    <div className="relative z-10 px-10 pt-12 pb-1 text-center">
                        <div
                            className="mx-auto inline-flex items-center gap-2 rounded-full px-3 py-2 backdrop-blur-sm"
                            style={{ border: '1px solid rgba(255,255,255,.18)', background: 'rgba(255,255,255,.1)' }}
                        >
                            <span className="h-1.5 w-1.5 rounded-full" style={{ background: '#88f0c9', boxShadow: '0 0 12px #88f0c9' }} />
                            <span className="text-[10px] font-extrabold">School Management Simplified</span>
                        </div>
                        <h2 className="mt-[22px] mb-[15px] text-[43px] leading-[0.93] font-black tracking-[-0.055em]">
                            Continue
                            <br />
                            <em className="not-italic" style={{ color: 'var(--color-indigo-200)' }}>
                                Managing.
                            </em>
                        </h2>
                        <p className="mx-auto max-w-[430px] text-[13px] leading-[1.65]" style={{ color: 'var(--color-indigo-100)' }}>
                            Pick up right where you left off. Sign in to your school's all-in-one management platform.
                        </p>
                    </div>

                    <div id="illustration" className="relative z-[3] -mt-3 flex flex-1 items-end justify-center px-6 pb-6">
                        <div
                            className="absolute rounded-full"
                            style={{
                                width: 360,
                                height: 360,
                                left: '50%',
                                top: '55%',
                                transform: 'translate(-50%,-50%)',
                                background: 'radial-gradient(circle, rgba(255,255,255,.28), rgba(255,255,255,.04) 52%, transparent 70%)',
                                border: '1px solid rgba(255,255,255,.12)',
                            }}
                        />
                        <img
                            src="/images/login.webp"
                            alt="School Management Illustration"
                            className="animate-model-float relative z-[3] h-[400px] w-full max-w-md object-contain"
                            style={{
                                filter: 'hue-rotate(var(--brand-hue-shift, 0deg)) drop-shadow(0 30px 30px color-mix(in srgb, var(--color-indigo-950) 28%, transparent))',
                            }}
                        />

                        <div
                            className="absolute top-[38%] left-[4%] z-[5] flex items-center gap-2 rounded-[15px] px-3.5 py-3 text-[10px] font-bold backdrop-blur-[15px]"
                            style={{
                                border: '1px solid rgba(255,255,255,.2)',
                                background: 'color-mix(in srgb, var(--color-indigo-900) 30%, transparent)',
                                boxShadow: '0 16px 38px color-mix(in srgb, var(--color-indigo-950) 25%, transparent)',
                            }}
                        >
                            <ShieldCheck className="h-5 w-5" strokeWidth={1.8} />
                            <span>Secure access</span>
                        </div>

                        <div
                            className="absolute top-[32%] right-[3%] z-[5] flex items-center gap-2 rounded-[15px] px-3.5 py-3 text-[10px] font-bold backdrop-blur-[15px]"
                            style={{
                                border: '1px solid rgba(255,255,255,.2)',
                                background: 'color-mix(in srgb, var(--color-indigo-900) 30%, transparent)',
                                boxShadow: '0 16px 38px color-mix(in srgb, var(--color-indigo-950) 25%, transparent)',
                            }}
                        >
                            <i className="h-2 w-2 rounded-full" style={{ background: '#66ebbd', boxShadow: '0 0 12px #66ebbd' }} />
                            <span>Systems online</span>
                            <strong style={{ color: '#8df0ce' }}>99.9%</strong>
                        </div>

                        <div
                            className="absolute right-[5%] bottom-[11%] z-[5] flex items-center gap-2 rounded-[15px] px-3 py-2.5 text-[10px] font-bold backdrop-blur-[15px]"
                            style={{
                                border: '1px solid rgba(255,255,255,.2)',
                                background: 'color-mix(in srgb, var(--color-indigo-900) 30%, transparent)',
                                boxShadow: '0 16px 38px color-mix(in srgb, var(--color-indigo-950) 25%, transparent)',
                            }}
                        >
                            <div className="flex">
                                {['A', 'S', '+8'].map((letter) => (
                                    <b
                                        key={letter}
                                        className="-ml-1.5 flex h-[25px] w-[25px] items-center justify-center rounded-full border-2 bg-white text-[8px] first:ml-0"
                                        style={{ color: 'var(--color-indigo-600)', borderColor: 'var(--color-indigo-400)' }}
                                    >
                                        {letter}
                                    </b>
                                ))}
                            </div>
                            <span>Team connected</span>
                        </div>
                    </div>

                    <div
                        className="relative z-[6] mx-auto mb-5 grid w-[82%] grid-cols-3 rounded-[17px] px-5 py-3.5 backdrop-blur-[14px]"
                        style={{
                            border: '1px solid rgba(255,255,255,.16)',
                            background: 'color-mix(in srgb, var(--color-indigo-800) 35%, transparent)',
                        }}
                    >
                        {[
                            ['24/7', 'Secure access'],
                            ['10k+', 'Active students'],
                            ['99.9%', 'Platform uptime'],
                        ].map(([value, label], index) => (
                            <div
                                key={label}
                                className={`flex flex-col items-center ${index < 2 ? 'border-r' : ''}`}
                                style={{ borderColor: 'rgba(255,255,255,.14)' }}
                            >
                                <strong className="text-[15px]">{value}</strong>
                                <span className="mt-0.5 text-[8px]" style={{ color: 'var(--color-indigo-100)' }}>
                                    {label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
