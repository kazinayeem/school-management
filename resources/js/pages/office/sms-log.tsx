import { Badge, Nav, OfficePage, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';

type Row = { id: number; phone: string; recipient: string | null; message: string; status: string; created_at: string; detail: string | null };
const tone = { queued: 'amber', sent: 'green', failed: 'red' } as const;
const label = { queued: 'Queued', sent: 'Sent', failed: 'Failed' } as const;

export default function SmsLog({
    navigation,
    roleLabel,
    rows,
    counts,
    filters,
    gateway,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    counts: Record<string, number>;
    filters: { status: string };
    gateway: { enabled: boolean; provider: string };
}) {
    const { t } = useI18n();

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Delivery Log" subtitle="Every message queued, sent or failed." wide>
            <div className="grid gap-3 sm:grid-cols-3">
                {(['queued', 'sent', 'failed'] as const).map((s) => (
                    <button
                        key={s}
                        onClick={() => router.get('/app/sms/log', { status: filters.status === s ? '' : s }, { preserveState: true, replace: true })}
                        className={`rounded-xl border bg-white p-4 text-start shadow-sm ${filters.status === s ? 'border-fuchsia-500' : 'border-slate-200'}`}
                    >
                        <div className="text-2xl font-bold">{counts[s] ?? 0}</div>
                        <div className="text-xs text-slate-500">{t(label[s])}</div>
                    </button>
                ))}
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <p className="text-sm text-slate-600">
                    {gateway.enabled
                        ? t('Delivering through {provider}.', { provider: gateway.provider })
                        : t('No provider is on, so messages stay queued until you mark them.')}
                </p>
                {gateway.enabled && (counts.queued ?? 0) > 0 && (
                    <button
                        onClick={() => router.post('/app/sms/log/deliver', {}, { preserveScroll: true })}
                        className="ms-auto rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800"
                    >
                        {t('Deliver queued messages')}
                    </button>
                )}
            </div>
            <Table headers={['Date', 'To', 'Phone', 'Message', 'Status', '']} empty={rows.length === 0 ? 'No messages yet.' : null}>
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 whitespace-nowrap">{r.created_at.slice(0, 16)}</td>
                        <td className="px-4 py-3">{r.recipient}</td>
                        <td className="px-4 py-3" dir="ltr">
                            {r.phone}
                        </td>
                        <td className="max-w-sm truncate px-4 py-3">{r.message}</td>
                        <td className="px-4 py-3">
                            <Badge tone={tone[r.status as keyof typeof tone] ?? 'slate'}>
                                {t(label[r.status as keyof typeof label] ?? r.status)}
                            </Badge>
                            {r.detail && <div className="mt-1 max-w-[220px] text-xs text-red-600">{r.detail}</div>}
                        </td>
                        <td className="space-x-2 px-4 py-3 text-end whitespace-nowrap">
                            {r.status === 'queued' && (
                                <>
                                    <button
                                        onClick={() => router.put(`/app/sms/log/${r.id}`, { status: 'sent' }, { preserveScroll: true })}
                                        className="rounded-lg border px-3 py-1 text-xs hover:bg-slate-50"
                                    >
                                        {t('Mark sent')}
                                    </button>
                                    <button
                                        onClick={() => router.put(`/app/sms/log/${r.id}`, { status: 'failed' }, { preserveScroll: true })}
                                        className="rounded-lg border px-3 py-1 text-xs text-red-600 hover:bg-slate-50"
                                    >
                                        {t('Mark failed')}
                                    </button>
                                </>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
