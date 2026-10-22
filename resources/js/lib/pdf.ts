const xsrf = () => decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

/** Save a file the server sent back. Works in every browser, with no print dialog. */
export function saveBlob(blob: Blob, filename: string) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
}

export type PdfTable = {
    title: string;
    subtitle?: string;
    filters?: string;
    summary?: { label: string; value: string | number }[];
    sections: { title?: string | null; columns: { key: string; label: string; num?: boolean; money?: boolean }[]; rows: Record<string, unknown>[]; footer?: Record<string, unknown> | null }[];
};

/** Ask the server to turn the table on screen into a file (PDF by default) and save it, or print it. Returns an error message, or null on success. */
export async function downloadTablePdf(table: PdfTable, format: 'pdf' | 'csv' | 'xlsx' | 'html' = 'pdf'): Promise<string | null> {
    try {
        const res = await fetch('/pdf/table', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: '*/*', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ ...table, format }),
        });
        if (res.status === 401 || res.status === 419) return 'Your session has expired. Please sign in again.';
        if (!res.ok) return 'The PDF could not be created.';
        if (format === 'html') {
            printHtml(await res.text());

            return null;
        }
        saveBlob(await res.blob(), `${table.title.toLowerCase().replace(/[^a-z0-9]+/g, '-') || 'report'}.${format}`);

        return null;
    } catch {
        return 'The PDF could not be created. Check your connection.';
    }
}

export function downloadCsv(filename: string, header: string[], rows: (string | number | null | undefined)[][]) {
    const esc = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = [header, ...rows].map((r) => r.map(esc).join(',')).join('\r\n');
    saveBlob(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }), filename);
}

export function printHtml(html: string) {
    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
    document.body.appendChild(frame);
    frame.onload = () => {
        frame.contentWindow?.focus();
        frame.contentWindow?.print();
        setTimeout(() => frame.remove(), 60000);
    };
    frame.srcdoc = html;
}
