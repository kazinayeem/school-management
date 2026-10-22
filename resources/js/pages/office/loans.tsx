import { Badge, inputCls, money, Nav, OfficePage, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { useState } from 'react';

type Loan = {
    id: number;
    name: string;
    title: string;
    card_no: string | null;
    issued_on: string;
    due_on: string;
    returned_on: string | null;
    fine: number;
    accrued?: number;
};

export default function Loans({
    navigation,
    roleLabel,
    mode,
    rows,
    unpaid = [],
    search,
    finePerDay,
}: {
    navigation: Nav;
    roleLabel: string;
    mode: 'desk' | 'fines';
    rows: Loan[];
    unpaid?: Loan[];
    search: string;
    finePerDay: number;
}) {
    const { t } = useI18n();
    const [q, setQ] = useState(search);
    const desk = mode === 'desk';
    const late = (l: Loan) => l.due_on < new Date().toISOString().slice(0, 10);

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title={desk ? 'Take Books Back' : 'Overdue & Fines'}
            subtitle={
                desk ? 'Receive returned books. Late books are fined automatically.' : 'Books past their due date and fines still to be collected.'
            }
        >
            {desk && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/app/library/desk', { search: q }, { preserveState: true, replace: true });
                    }}
                >
                    <input
                        className={`${inputCls} max-w-sm`}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('Search member, card or book')}
                    />
                </form>
            )}
            <p className="text-xs text-slate-500">{t('Fine per late day: {amount}', { amount: finePerDay })}</p>

            <Table
                headers={['Member', 'Book', 'Issued', 'Due', desk ? 'Fine so far' : 'Fine so far', '']}
                empty={rows.length === 0 ? (desk ? 'No books are out.' : 'Nothing is overdue.') : null}
            >
                {rows.map((l) => (
                    <tr key={l.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">
                            {l.name} <span className="text-xs text-slate-400">{l.card_no}</span>
                        </td>
                        <td className="px-4 py-3">{l.title}</td>
                        <td className="px-4 py-3">{l.issued_on}</td>
                        <td className="px-4 py-3">
                            {l.due_on} {late(l) && <Badge tone="red">{t('Overdue')}</Badge>}
                        </td>
                        <td className="px-4 py-3">{money(l.accrued ?? 0)}</td>
                        <td className="px-4 py-3 text-end">
                            <button
                                onClick={() => router.put(`/app/library/loans/${l.id}/return`, {}, { preserveScroll: true })}
                                className="rounded-lg border px-3 py-1 text-xs hover:bg-slate-50"
                            >
                                {t('Mark returned')}
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>

            {!desk && (
                <>
                    <h2 className="font-semibold">{t('Fines to collect')}</h2>
                    <Table headers={['Member', 'Book', 'Returned', 'Fine', '']} empty={unpaid.length === 0 ? 'No unpaid fines.' : null}>
                        {unpaid.map((l) => (
                            <tr key={l.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3">{l.name}</td>
                                <td className="px-4 py-3">{l.title}</td>
                                <td className="px-4 py-3">{l.returned_on}</td>
                                <td className="px-4 py-3">{money(l.fine)}</td>
                                <td className="px-4 py-3 text-end">
                                    <button
                                        onClick={() => router.put(`/app/library/loans/${l.id}/fine`, {}, { preserveScroll: true })}
                                        className="rounded-lg bg-fuchsia-700 px-3 py-1 text-xs text-white"
                                    >
                                        {t('Mark paid')}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </Table>
                </>
            )}
        </OfficePage>
    );
}
