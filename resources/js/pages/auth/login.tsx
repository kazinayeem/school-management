import { MadeBy } from '@/components/made-by';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BookOpen, Check, ChevronRight, Copy, Eye, EyeOff, GraduationCap, Languages as LanguagesIcon, Lock, ShieldCheck, User, Users, X } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

import { ThemeToggle } from '@/components/preferences';
import { useI18n, type Locale } from '@/lib/i18n';
import { Branding, Language, SharedData } from '@/types';

type Role = 'admin' | 'teacher' | 'staff' | 'student' | 'parent';

const roles: { value: Role; label: string; icon: typeof User }[] = [
    { value: 'admin', label: 'Admin', icon: ShieldCheck },
    { value: 'teacher', label: 'Teacher', icon: BookOpen },
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
        role: ((): Role => { const r = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('role') : null; return roles.some((x) => x.value === r) ? (r as Role) : 'admin'; })(),
        remember: false as boolean,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [copied, setCopied] = useState(false);
    const [dismissedError, setDismissedError] = useState(false);
    const [logoFailed, setLogoFailed] = useState(false);
    const [languageOpen, setLanguageOpen] = useState(false);
    const { t, locale, setLocale } = useI18n();

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
        setLocale(language.code as Locale);
        setLanguageOpen(false);
    };

    const activeLanguage = branding.languages.find((language) => language.code === locale) ?? branding.languages[0];

    const input = 'h-12 w-full rounded-xl border border-slate-200 bg-white ps-11 pe-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-brand focus:ring-2 focus:ring-brand/20';

    return (
        <div className="themed flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-brand-soft via-slate-50 to-slate-50 p-4 md:p-8">
            <Head title="Log in" />

            <div className="w-full max-w-[480px] rounded-3xl border border-slate-200 bg-white p-6 shadow-xl sm:p-9">
                <div className="mb-8 flex items-center gap-2.5">
                    <Link href="/" className="flex min-w-0 items-center gap-2.5" aria-label={branding.name}>
                        {!logoFailed ? (
                            <>
                                <img src={branding.logo} alt={`${branding.name} logo`} className="h-10 w-auto max-w-[180px] shrink-0 object-contain object-left dark:hidden" onError={() => setLogoFailed(true)} />
                                <img src={branding.logoWhite} alt="" aria-hidden className="hidden h-10 w-auto max-w-[180px] shrink-0 object-contain object-left dark:block" />
                            </>
                        ) : (
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand text-white"><GraduationCap className="h-5 w-5" /></span>
                        )}
                        <span className="truncate text-lg font-extrabold text-brand" title={branding.name}>{branding.name}</span>
                    </Link>
                    <div className="ms-auto flex shrink-0 items-center gap-2">
                        <ThemeToggle />
                        <div className="relative">
                            <button type="button" aria-label="Change language" onClick={() => setLanguageOpen((open) => !open)} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                <LanguagesIcon className="h-4 w-4 shrink-0 text-brand" />
                                <span className="hidden sm:inline">{activeLanguage?.flag} {activeLanguage?.name ?? locale.toUpperCase()}</span>
                                <span className="sm:hidden">{activeLanguage?.flag ?? locale.toUpperCase()}</span>
                            </button>
                            {languageOpen && (
                                <>
                                    <div className="fixed inset-0 z-40" onClick={() => setLanguageOpen(false)} />
                                    <div className="absolute end-0 top-full z-50 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                        {branding.languages.map((language) => (
                                            <button key={language.code} type="button" onClick={() => chooseLanguage(language)} className={`flex w-full items-center gap-2 px-3 py-2 text-start text-sm hover:bg-slate-50 ${language.code === locale ? 'font-semibold text-brand' : 'text-slate-600'}`}>
                                                <span>{language.flag}</span>
                                                <span className="flex-1">{language.name}</span>
                                                {language.code === locale && <Check className="h-3.5 w-3.5 text-brand" />}
                                            </button>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <h1 className="text-2xl font-extrabold text-slate-900">{t('Login')}</h1>
                <p className="mt-1 text-sm text-slate-500">{t('Sign in to continue')}</p>

                <form className="mt-6 space-y-4" onSubmit={submit}>
                    <fieldset>
                        <legend className="mb-2 text-xs font-bold tracking-wider text-slate-500 uppercase">{t('Select Your Role')}</legend>
                        <div className="grid grid-cols-5 gap-2">
                            {roles.map(({ value, label, icon: Icon }) => {
                                const active = data.role === value;

                                return (
                                    <label key={value} className={`flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border px-1 py-2.5 text-[11px] font-semibold transition focus-within:ring-2 focus-within:ring-brand/30 sm:text-xs ${active ? 'border-brand bg-brand text-white shadow-md' : 'border-slate-200 bg-white text-slate-600 hover:border-brand/50'}`}>
                                        <input type="radio" name="role" value={value} checked={active} onChange={() => setData('role', value)} className="sr-only" />
                                        <Icon className="h-5 w-5" />
                                        {t(label)}
                                    </label>
                                );
                            })}
                        </div>
                    </fieldset>

                    <label className="block">
                        <span className="mb-1.5 block text-sm font-medium text-slate-700">{t('Username or email')}</span>
                        <span className="relative block">
                            <User className="pointer-events-none absolute start-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input className={input} autoComplete="username" autoFocus value={data.login} onChange={(e) => setData('login', e.target.value)} placeholder={t('Your Username or Email')} />
                        </span>
                    </label>

                    <label className="block">
                        <span className="mb-1.5 block text-sm font-medium text-slate-700">{t('Password')}</span>
                        <span className="relative block">
                            <Lock className="pointer-events-none absolute start-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input className={`${input} pe-12`} type={showPassword ? 'text' : 'password'} autoComplete="current-password" value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder={t('Your Password')} />
                            <button type="button" aria-label={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(!showPassword)} className="absolute end-3 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 hover:text-slate-700">
                                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </span>
                    </label>

                    {current && (
                        <button type="button" onClick={fillDemo} title="Fill demo credentials" className="flex w-full items-center gap-2 rounded-xl border border-dashed border-brand/40 bg-brand-soft px-3 py-2.5 text-start text-xs text-slate-700 transition hover:border-brand">
                            <span className="font-bold text-brand">{t('Demo')}:</span>
                            <span className="min-w-0 flex-1 truncate font-mono">{current.username} · {current.password}{current.name ? ` · ${current.name}` : ''}</span>
                            {copied ? <Check className="h-4 w-4 shrink-0 text-emerald-600" /> : <Copy className="h-4 w-4 shrink-0 text-brand" />}
                        </button>
                    )}

                    <div className="flex items-center justify-between gap-3 text-sm">
                        <label className="flex cursor-pointer items-center gap-2 text-slate-600">
                            <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} className="h-4 w-4 rounded border-slate-300 accent-brand" />
                            {t('Remember me')}
                        </label>
                        {canResetPassword && <Link href={route('password.request')} className="font-medium text-brand hover:underline">{t('Forgot password?')}</Link>}
                    </div>

                    {error && !dismissedError && (
                        <div role="alert" className="animate-shake flex items-start justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs text-rose-700">
                            <span>{error}</span>
                            <button type="button" onClick={() => setDismissedError(true)} className="shrink-0" aria-label="Dismiss error"><X className="h-4 w-4" /></button>
                        </div>
                    )}
                    {status && <div className="text-center text-xs text-emerald-600">{status}</div>}

                    <button type="submit" disabled={processing} className="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-2 text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60">
                        <Lock className="h-4 w-4" />
                        {processing ? t('Signing in...') : t('Login')}
                        <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                    </button>
                </form>
            </div>

            <div className="mt-6 text-center text-xs text-slate-500">
                <Link href="/" className="font-medium text-brand hover:underline">← {t('Back to website')}</Link>
                <div className="mt-2"><MadeBy /></div>
            </div>
        </div>
    );
}
