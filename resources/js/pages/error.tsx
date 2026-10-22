import { useI18n } from '@/lib/i18n';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home, LockKeyhole, SearchX, TriangleAlert } from 'lucide-react';

const copy: Record<number, { title: string; body: string }> = {
    403: { title: 'You do not have access to this page', body: 'Your account is not allowed to open it. If you think this is a mistake, ask the school office.' },
    404: { title: 'We could not find that page', body: 'The link may be old, or the record may have been removed.' },
    500: { title: 'Something went wrong on our side', body: 'The error has been recorded. Please try again in a moment.' },
    503: { title: 'The school system is being updated', body: 'Please try again in a few minutes.' },
};

export default function ErrorPage({ status, message }: { status: number; message?: string }) {
    const { t } = useI18n();
    const { auth } = usePage<{ auth: { user: { role: string } | null } }>().props;
    const text = copy[status] ?? copy[500];
    const Icon = status === 403 ? LockKeyhole : status === 404 ? SearchX : TriangleAlert;
    const home = !auth.user ? '/login' : ['admin', 'teacher', 'staff'].includes(auth.user.role) ? '/app' : auth.user.role === 'parent' ? '/parent' : '/student';

    return (
        <div className="themed flex min-h-screen items-center justify-center bg-slate-50 p-6 text-slate-800">
            <Head title={`${status}`} />
            <div className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-fuchsia-50 text-brand">
                    <Icon className="h-7 w-7" />
                </span>
                <p className="mt-4 text-xs font-bold tracking-[0.2em] text-slate-400">{status}</p>
                <h1 className="mt-1 text-xl font-bold">{t(text.title)}</h1>
                <p className="mt-2 text-sm text-slate-500">{message ? t(message) : t(text.body)}</p>
                <div className="mt-6 flex flex-wrap justify-center gap-2">
                    <button type="button" onClick={() => window.history.back()} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('Go back')}
                    </button>
                    <Link href={home} className="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">
                        <Home className="h-4 w-4" /> {auth.user ? t('Dashboard') : t('Sign in')}
                    </Link>
                </div>
            </div>
        </div>
    );
}
