import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { FileText, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

export type UserFile = { id: number; kind: string; kind_label: string; title: string; mime: string; size: number; uploaded_at: string; url: string };

const size = (n: number) => (n > 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

const MAX_BYTES = 5 * 1024 * 1024;
const ALLOWED = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

/** The papers on file. Only an administrator can add or remove; everyone else just opens them. */
export function UserFiles({ userId, files, kinds, canManage = false }: { userId?: number; files: UserFile[]; kinds?: Record<string, string>; canManage?: boolean }) {
    const { t } = useI18n();
    const input = useRef<HTMLInputElement>(null);
    const [kind, setKind] = useState('birth_certificate');
    const [title, setTitle] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [done, setDone] = useState('');

    const choose = (f: File | null) => {
        setDone('');
        if (!f) return setFile(null);
        const ext = f.name.split('.').pop()?.toLowerCase() ?? '';
        if (!ALLOWED_EXT.includes(ext) || (f.type && !ALLOWED.includes(f.type))) {
            setFile(null);
            return setError(t('Only PDF, JPG, PNG or WebP files can be uploaded.'));
        }
        if (f.size > MAX_BYTES) {
            setFile(null);
            return setError(t('That file is larger than 5 MB.'));
        }
        setError('');
        setFile(f);
    };

    const upload = () => {
        if (busy) return;
        if (!file) return setError(t('Choose a file first.'));
        setBusy(true);
        setError('');
        router.post(
            `/files/${userId}`,
            { kind, title, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setFile(null);
                    setTitle('');
                    setDone(t('Document uploaded.'));
                    if (input.current) input.current.value = '';
                },
                onError: (e) => setError(e.file ?? e.kind ?? e.title ?? t('The upload failed. Please try again.')),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <div className="space-y-4">
            {canManage && userId && kinds && (
                <div
                    className="space-y-3 rounded-xl border border-dashed border-slate-300 p-4"
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        choose(e.dataTransfer.files?.[0] ?? null);
                    }}
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="text-xs font-medium text-slate-500">
                            {t('Type')}
                            <select className="mt-1 block rounded-lg border border-slate-200 px-3 py-2 text-sm" value={kind} onChange={(e) => setKind(e.target.value)}>
                                {Object.entries(kinds).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {t(v)}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-xs font-medium text-slate-500">
                            {t('Title (optional)')}
                            <input className="mt-1 block rounded-lg border border-slate-200 px-3 py-2 text-sm" value={title} maxLength={150} onChange={(e) => setTitle(e.target.value)} />
                        </label>
                        <label className="text-xs font-medium text-slate-500">
                            {t('File')}
                            <input ref={input} type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" className="mt-1 block max-w-[240px] text-xs" onChange={(e) => choose(e.target.files?.[0] ?? null)} />
                        </label>
                        <button type="button" disabled={busy || !file} onClick={upload} className="flex items-center gap-2 rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            <Upload className="h-4 w-4" /> {busy ? t('Uploading…') : t('Upload document')}
                        </button>
                    </div>
                    <p className="text-xs text-slate-400">
                        {file ? `${file.name} · ${(file.size / 1024).toFixed(0)} KB` : t('You can also drop a file here.')} {t('PDF, JPG, PNG or WebP, up to 5 MB.')}
                    </p>
                    {error && <p role="alert" className="text-sm text-rose-600">{error}</p>}
                    {done && <p role="status" className="text-sm text-emerald-700">{done}</p>}
                </div>
            )}
            {files.length === 0 ? (
                <p className="text-sm text-slate-500">{t('No documents uploaded yet.')}</p>
            ) : (
                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-sm">
                    {files.map((f) => (
                        <li key={f.id} className="flex flex-wrap items-center gap-3 p-3">
                            <FileText className="h-5 w-5 text-fuchsia-700" />
                            <div className="min-w-0 flex-1">
                                <div className="truncate font-medium">{f.title}</div>
                                <div className="text-xs text-slate-500">
                                    {t(f.kind_label)} · {size(f.size)} · {f.uploaded_at}
                                </div>
                            </div>
                            <a href={f.url} target="_blank" rel="noreferrer" className="font-medium text-fuchsia-700">
                                {t('View')}
                            </a>
                            <a href={`${f.url}?download=1`} className="font-medium text-slate-600">
                                {t('Download')}
                            </a>
                            {canManage && (
                                <button type="button" aria-label={t('Remove')} onClick={() => confirm(t('Remove this document?')) && router.delete(`/files/${f.id}`, { preserveScroll: true })} className="text-red-600">
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/** Optional file pickers shown while adding a student or staff member. */
export function DocumentInputs({ kinds, data, setData, errors }: { kinds: Record<string, string>; data: Record<string, any>; setData: (key: any, value: any) => void; errors: Record<string, string> }) {
    const { t } = useI18n();
    const shown = ['birth_certificate', 'nid', 'previous_certificate'].filter((k) => k in kinds);

    return (
        <fieldset className="space-y-3 rounded-xl border border-slate-200 p-4">
            <legend className="px-1 text-sm font-semibold">{t('Documents (optional)')}</legend>
            <p className="text-xs text-slate-500">{t('PDF, JPG, PNG or WebP, up to 5 MB.')}</p>
            <div className="grid gap-3 sm:grid-cols-3">
                {shown.map((k) => (
                    <label key={k} className="block text-xs font-medium text-slate-600">
                        {t(kinds[k])}
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" className="mt-1 block w-full text-xs" onChange={(e) => setData(`doc_${k}`, e.target.files?.[0] ?? null)} />
                        {data[`doc_${k}`] === undefined ? null : errors[`doc_${k}`] && <span className="text-rose-600">{errors[`doc_${k}`]}</span>}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
