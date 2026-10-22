import { Badge, Card, Field, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Row = { id: number; name: string; title: string; reserved_on: string; status: string };
const tone = { waiting: 'amber', ready: 'green', fulfilled: 'slate', cancelled: 'red' } as const;
const label = { waiting: 'Waiting', ready: 'Ready', fulfilled: 'Fulfilled', cancelled: 'Cancelled' } as const;

export default function Reservations({
    navigation,
    roleLabel,
    rows,
    books,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    books: { id: number; title: string }[];
}) {
    const { t } = useI18n();
    const form = useForm({ member: '', book_id: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/library/reservations', { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const set = (id: number, status: 'ready' | 'cancelled') => router.put(`/app/library/reservations/${id}`, { status }, { preserveScroll: true });

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Reserved Books" subtitle="Hold a book for a member who is waiting for it.">
            <form onSubmit={submit}>
                <Card className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Card no., admission no. or username" error={form.errors.member}>
                            <input className={inputCls} value={form.data.member} onChange={(e) => form.setData('member', e.target.value)} />
                        </Field>
                        <Field label="Book" error={form.errors.book_id}>
                            <select className={inputCls} value={form.data.book_id} onChange={(e) => form.setData('book_id', e.target.value)}>
                                <option value="">{t('Choose...')}</option>
                                {books.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.title}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Reserve')}
                    </button>
                </Card>
            </form>

            <Table headers={['Member', 'Book', 'Reserved on', 'Status', '']} empty={rows.length === 0 ? 'No reservations.' : null}>
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">{r.name}</td>
                        <td className="px-4 py-3">{r.title}</td>
                        <td className="px-4 py-3">{r.reserved_on}</td>
                        <td className="px-4 py-3">
                            <Badge tone={tone[r.status as keyof typeof tone] ?? 'slate'}>
                                {t(label[r.status as keyof typeof label] ?? r.status)}
                            </Badge>
                        </td>
                        <td className="space-x-2 px-4 py-3 text-end whitespace-nowrap">
                            {r.status === 'waiting' && (
                                <button onClick={() => set(r.id, 'ready')} className="rounded-lg border px-3 py-1 text-xs hover:bg-slate-50">
                                    {t('Mark ready')}
                                </button>
                            )}
                            {(r.status === 'waiting' || r.status === 'ready') && (
                                <button
                                    onClick={() => set(r.id, 'cancelled')}
                                    className="rounded-lg border px-3 py-1 text-xs text-red-600 hover:bg-slate-50"
                                >
                                    {t('Cancel')}
                                </button>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
