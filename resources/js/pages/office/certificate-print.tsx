import { useI18n } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

export default function CertificatePrint({
    certificate,
    school,
}: {
    certificate: { number: string; title: string; body: string; issued_on: string; name: string };
    school: { name: string; address: string };
}) {
    const { t } = useI18n();

    return (
        <div className="min-h-screen bg-slate-100 p-6 print:bg-white print:p-0" style={{ colorScheme: 'light' }}>
            <Head title={`${certificate.title} · ${certificate.number}`} />
            <div className="mx-auto mb-4 flex max-w-3xl justify-end print:hidden">
                <button
                    onClick={() => window.print()}
                    className="flex items-center gap-2 rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white"
                >
                    <Printer className="h-4 w-4" /> {t('Print')}
                </button>
            </div>
            <article className="mx-auto max-w-3xl border-8 border-double border-fuchsia-800 bg-white p-12 text-center text-slate-900 shadow print:shadow-none">
                <h1 className="text-3xl font-extrabold text-fuchsia-900">{school.name}</h1>
                {school.address && <p className="mt-1 text-sm text-slate-500">{school.address}</p>}
                <h2 className="mt-8 text-2xl font-semibold tracking-wide uppercase">{certificate.title}</h2>
                <p className="mt-1 text-xs text-slate-500">
                    {t('No.')} {certificate.number}
                </p>
                <p className="mx-auto mt-10 max-w-xl text-start leading-8 whitespace-pre-line">{certificate.body}</p>
                <div className="mt-20 flex justify-between text-sm">
                    <span className="border-t border-slate-400 px-6 pt-1">{t('Class teacher')}</span>
                    <span className="border-t border-slate-400 px-6 pt-1">{t('Principal')}</span>
                </div>
            </article>
        </div>
    );
}
