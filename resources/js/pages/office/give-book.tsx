import { Badge, Card, Field, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { PickedStudent, StudentSelect } from '@/components/student-picker';
import { useI18n } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Book = { id: number; title: string; author: string | null };
type Loan = { id: number; name: string; title: string; issued_on: string; due_on: string; status: string };

export default function GiveBook({
    navigation,
    roleLabel,
    books,
    loanDays,
    today,
    recent,
}: {
    navigation: Nav;
    roleLabel: string;
    books: Book[];
    loanDays: number;
    today: string;
    recent: Loan[];
}) {
    const { t } = useI18n();
    const [picked, setPicked] = useState<PickedStudent | null>(null);
    const form = useForm({ member: '', book_id: '', due_on: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/library/loans', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setPicked(null);
            },
        });
    };

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Give a Book" subtitle="Lend a book to a member with a library card.">
            <form onSubmit={submit}>
                <Card className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-3">
                        <StudentSelect
                            label="Student"
                            value={picked}
                            scope="library"
                            error={form.errors.member}
                            onChange={(s) => {
                                setPicked(s);
                                form.setData('member', s?.card_no ?? '');
                            }}
                        />
                        <Field label="Book" error={form.errors.book_id}>
                            <select className={inputCls} value={form.data.book_id} onChange={(e) => form.setData('book_id', e.target.value)}>
                                <option value="">{t('Choose...')}</option>
                                {books.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.title}
                                        {b.author ? ` — ${b.author}` : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Due date" error={form.errors.due_on}>
                            <input
                                type="date"
                                min={today}
                                className={inputCls}
                                value={form.data.due_on}
                                onChange={(e) => form.setData('due_on', e.target.value)}
                            />
                        </Field>
                    </div>
                    <p className="text-xs text-slate-500">{t('Leave the due date empty for the standard {days}-day loan.', { days: loanDays })}</p>
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Issue book')}
                    </button>
                </Card>
            </form>

            <h2 className="font-semibold">{t('Recent loans')}</h2>
            <Table headers={['Member', 'Book', 'Issued', 'Due', 'Status']} empty={recent.length === 0 ? 'No loans yet.' : null}>
                {recent.map((l) => (
                    <tr key={l.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">{l.name}</td>
                        <td className="px-4 py-3">{l.title}</td>
                        <td className="px-4 py-3">{l.issued_on}</td>
                        <td className="px-4 py-3">{l.due_on}</td>
                        <td className="px-4 py-3">
                            <Badge tone={l.status === 'returned' ? 'green' : l.status === 'overdue' ? 'red' : 'amber'}>
                                {t(l.status === 'on loan' ? 'On loan' : l.status === 'overdue' ? 'Overdue' : 'Returned')}
                            </Badge>
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
