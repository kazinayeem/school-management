import { downloadTablePdf, type PdfTable } from '@/lib/pdf';
import { useI18n } from '@/lib/i18n';
import { ChevronDown, FileDown, FileSpreadsheet, FileText, Printer } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Format = 'pdf' | 'csv' | 'xlsx' | 'html';

/** Export or print the table on screen: CSV, Excel, PDF or a print dialog. For lists that already show every row. */
export function TableExportMenu({ build, className = '' }: { build: () => PdfTable; className?: string }) {
    const { t } = useI18n();
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const box = useRef<HTMLSpanElement>(null);
    useEffect(() => {
        const close = (e: MouseEvent) => !box.current?.contains(e.target as Node) && setOpen(false);
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const run = async (format: Format) => {
        setBusy(true);
        setError('');
        const e = await downloadTablePdf(build(), format);
        setBusy(false);
        if (e) setError(e);
        else setOpen(false);
    };
    const item = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm hover:bg-fuchsia-50 disabled:opacity-50';

    return (
        <span className="relative inline-flex flex-col" ref={box}>
            <button type="button" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen(!open)} className={`inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 ${className}`}>
                <FileDown className="h-4 w-4" /> {busy ? t('Preparing…') : t('Export')} <ChevronDown className={`h-4 w-4 transition ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div role="menu" className="absolute end-0 top-full z-30 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                    <button type="button" role="menuitem" disabled={busy} className={item} onClick={() => run('csv')}>
                        <FileText className="h-4 w-4 text-slate-500" /> {t('Export CSV')}
                    </button>
                    <button type="button" role="menuitem" disabled={busy} className={item} onClick={() => run('xlsx')}>
                        <FileSpreadsheet className="h-4 w-4 text-emerald-600" /> {t('Export Excel (.xlsx)')}
                    </button>
                    <button type="button" role="menuitem" disabled={busy} className={item} onClick={() => run('pdf')}>
                        <FileText className="h-4 w-4 text-rose-600" /> {t('Download PDF')}
                    </button>
                    <button type="button" role="menuitem" disabled={busy} className={item} onClick={() => run('html')}>
                        <Printer className="h-4 w-4 text-slate-500" /> {t('Print')}
                    </button>
                    {error && <p role="alert" className="px-2 pt-1 text-xs text-rose-600">{t(error)}</p>}
                </div>
            )}
        </span>
    );
}

/** Kept for pages that only need a PDF. */
export const PdfButton = TableExportMenu;
