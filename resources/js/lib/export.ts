import { printHtml, saveBlob } from '@/lib/pdf';

const xsrf = () => decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

export type ExportRequest = {
    dataset: string;
    format: 'csv' | 'xlsx' | 'pdf' | 'html';
    scope: 'page' | 'selected' | 'all';
    ids?: number[];
    exclude?: number[];
    columns?: string[];
    /** The filters, page, limit and sort the list is showing now, so the file matches the screen. */
    params: Record<string, string | number | null | undefined>;
};

const extension = { csv: 'csv', xlsx: 'xlsx', pdf: 'pdf', html: 'html' } as const;

/** Ask the server for the file and save it (or print it). Returns an error message, or null on success. */
export async function runExport(req: ExportRequest): Promise<string | null> {
    try {
        const body = { ...Object.fromEntries(Object.entries(req.params).filter(([, v]) => v !== '' && v !== null && v !== undefined)), format: req.format, scope: req.scope, ids: req.ids, exclude: req.exclude, columns: req.columns };
        const res = await fetch(`/export/${req.dataset}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json, */*', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        if (res.status === 401 || res.status === 419) return 'Your session has expired. Please sign in again.';
        if (res.status === 403) return 'You are not allowed to export this list.';
        if (!res.ok) {
            const j = await res.json().catch(() => null);

            return j?.message ?? 'The export failed. Please try again.';
        }
        if (req.format === 'html') {
            printHtml(await res.text());

            return null;
        }
        saveBlob(await res.blob(), `${req.dataset}-${new Date().toISOString().slice(0, 10)}.${extension[req.format]}`);

        return null;
    } catch {
        return 'The export failed. Check your connection and try again.';
    }
}
