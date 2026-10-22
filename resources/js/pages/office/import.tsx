import { FlashStatus } from '@/components/flash';
import { OfficeShell } from '@/components/office-shell';
import { useI18n } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { FormEvent } from 'react';

type Result = { created: number; failed: number; errors: { row: number; name: string; message: string }[] };

export default function ImportStudents({
    navigation,
    roleLabel,
    columns,
    result,
}: {
    navigation: Parameters<typeof OfficeShell>[0]['navigation'];
    roleLabel: string;
    columns: string[];
    result?: Result | null;
}) {
    const { t } = useI18n();
    const form = useForm<{ file: File | null; pasted: string }>({ file: null, pasted: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/students/import', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <OfficeShell navigation={navigation} roleLabel={roleLabel}>
            <Head title={t('Bulk Import')} />
            <div className="mx-auto max-w-[900px] space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{t('Bulk Import')}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">{t('Add many students at once from a CSV file.')}</p>
                </div>
                <FlashStatus />

                {result && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" role="status">
                        <p className="font-semibold">
                            {t('{created} students imported, {failed} rows skipped.', { created: result.created, failed: result.failed })}
                        </p>
                        {result.errors.length > 0 && (
                            <ul className="mt-3 space-y-1 text-sm text-red-600">
                                {result.errors.map((e) => (
                                    <li key={e.row}>
                                        {t('Row {row}', { row: e.row })} {e.name && `(${e.name})`}: {e.message}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div>
                        <p className="mb-1 text-sm font-medium text-slate-700">{t('CSV columns')}</p>
                        <code className="block rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" dir="ltr">
                            {columns.join(', ')}
                        </code>
                        <p className="mt-2 text-xs text-slate-500">
                            {t('Class like "Class 6", section A or B, gender Male or Female, date as YYYY-MM-DD.')}
                        </p>
                        <a href="/app/students/import/template" className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-fuchsia-700">
                            <Download className="h-4 w-4" /> {t('Download template')}
                        </a>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">{t('CSV file')}</label>
                        <input
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">{t('Or paste rows')}</label>
                        <textarea
                            rows={6}
                            dir="ltr"
                            value={form.data.pasted}
                            onChange={(e) => form.setData('pasted', e.target.value)}
                            className="w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs outline-none focus:border-fuchsia-500"
                            placeholder={columns.join(',')}
                        />
                    </div>

                    {form.errors.file && <p className="text-sm text-red-600">{t(form.errors.file)}</p>}
                    {form.errors.pasted && <p className="text-sm text-red-600">{form.errors.pasted}</p>}

                    <button
                        disabled={form.processing}
                        className="flex items-center gap-2 rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-60"
                    >
                        <Upload className="h-4 w-4" /> {t('Import students')}
                    </button>
                </form>
            </div>
        </OfficeShell>
    );
}
