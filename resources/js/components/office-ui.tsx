import { FlashStatus } from '@/components/flash';
import { OfficeShell } from '@/components/office-shell';
import { useI18n } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

export type Nav = Parameters<typeof OfficeShell>[0]['navigation'];

export const inputCls = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-fuchsia-500';
export const primaryBtn = 'rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-50';
export const ghostBtn = 'rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50';
export const money = (n: number | null | undefined) => (n === null || n === undefined ? '' : Number(n).toLocaleString());

export function OfficePage({
    navigation,
    roleLabel,
    title,
    subtitle,
    wide = false,
    children,
}: PropsWithChildren<{ navigation: Nav; roleLabel: string; title: string; subtitle: string; wide?: boolean }>) {
    const { t } = useI18n();

    return (
        <OfficeShell navigation={navigation} roleLabel={roleLabel}>
            <Head title={t(title)} />
            <div className={`mx-auto space-y-6 ${wide ? 'max-w-[1400px]' : 'max-w-[1100px]'}`}>
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{t(title)}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">{t(subtitle)}</p>
                </div>
                <FlashStatus />
                {children}
            </div>
        </OfficeShell>
    );
}

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <section className={`rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ${className}`}>{children}</section>;
}

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    const { t } = useI18n();

    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{t(label)}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{t(error)}</p>}
        </div>
    );
}

export function Table({ headers, empty, children, busy = false }: { headers: ReactNode[]; empty?: string | null; children: ReactNode; busy?: boolean }) {
    const { t } = useI18n();

    return (
        <div className={`overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity ${busy ? 'opacity-60' : ''}`} aria-busy={busy}>
            {empty ? (
                <p className="px-6 py-14 text-center text-sm text-slate-400">{t(empty)}</p>
            ) : (
                <table className="w-full min-w-[640px] text-sm">
                    <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                        <tr>
                            {headers.map((h, i) => (
                                <th key={i} className="px-4 py-3 text-start font-semibold">
                                    {typeof h === 'string' ? t(h) : h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">{children}</tbody>
                </table>
            )}
        </div>
    );
}

export function Badge({ tone, children }: { tone: 'green' | 'amber' | 'red' | 'slate'; children: ReactNode }) {
    const tones = {
        green: 'bg-green-100 text-green-700',
        amber: 'bg-amber-100 text-amber-700',
        red: 'bg-red-100 text-red-700',
        slate: 'bg-slate-100 text-slate-600',
    };

    return <span className={`rounded-full px-2 py-0.5 text-xs ${tones[tone]}`}>{children}</span>;
}
