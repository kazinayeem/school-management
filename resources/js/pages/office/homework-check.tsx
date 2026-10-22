import { Avatar } from '@/components/avatar';
import { Badge, Card, inputCls, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';

type Item = { id: number; name: string; photo: string | null; roll_no: string; status: string; note: string | null; remarks: string | null; grade: string | null; submitted_at: string | null };

function Row({ item }: { item: Item }) {
    const { t } = useI18n();
    const form = useForm({ grade: item.grade ?? '', remarks: item.remarks ?? '', redo: false });
    const send = (redo: boolean) => {
        form.transform((d) => ({ ...d, redo }));
        form.post(`/app/homework/items/${item.id}`, { preserveScroll: true });
    };
    const tone = item.status === 'marked' ? 'green' : item.status === 'submitted' ? 'amber' : 'slate';

    return (
        <li className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="flex items-center gap-3">
                <Avatar name={item.name} src={item.photo} className="h-10 w-10" />
                <div className="min-w-0 flex-1">
                    <div className="truncate font-medium">{item.name}</div>
                    <div className="text-xs text-slate-500">
                        {t('Roll')} {item.roll_no}
                        {item.submitted_at ? ` · ${item.submitted_at}` : ''}
                    </div>
                </div>
                <Badge tone={tone}>{t(item.status === 'todo' ? 'Not handed in' : item.status === 'submitted' ? 'Handed in' : 'Checked')}</Badge>
            </div>
            {item.note && <p className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{item.note}</p>}
            {item.status !== 'todo' || item.remarks ? (
                <div className="mt-3 grid gap-2 md:grid-cols-[120px_1fr_auto_auto]">
                    <input className={inputCls} placeholder={t('Grade')} value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)} />
                    <input className={inputCls} placeholder={t('Feedback for the student and parent')} value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                    <button type="button" className={primaryBtn} disabled={form.processing || item.status === 'todo'} onClick={() => send(false)}>
                        {t('Save')}
                    </button>
                    <button type="button" className="rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50" disabled={form.processing} onClick={() => send(true)}>
                        {t('Ask to redo')}
                    </button>
                </div>
            ) : null}
        </li>
    );
}

export default function HomeworkCheck({ navigation, roleLabel, batch, assignment, items }: { navigation: any; roleLabel: string; batch: string; assignment: { title: string; subject: string; due_on: string; instructions: string | null; class_name: string; section: string }; items: Item[] }) {
    const { t } = useI18n();
    const waiting = items.filter((i) => i.status === 'submitted').length;

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title={assignment.title} subtitle={`${assignment.subject} · ${assignment.class_name} ${assignment.section} · ${t('Due')} ${assignment.due_on}`} wide>
            <Card className="flex flex-wrap items-center gap-3">
                <Link href="/app/homework" className="text-sm font-medium text-fuchsia-700">
                    ← {t('All homework')}
                </Link>
                <span className="text-sm text-slate-500">{t('{n} waiting', { n: waiting })}</span>
                <button
                    className="ms-auto text-sm text-red-600"
                    onClick={() => confirm(t('Delete this homework for the whole section?')) && router.delete(`/app/homework/${batch}`)}
                >
                    {t('Delete')}
                </button>
            </Card>
            {assignment.instructions && <Card><p className="text-sm whitespace-pre-line text-slate-700">{assignment.instructions}</p></Card>}
            <ul className="space-y-3">
                {items.map((i) => (
                    <Row key={i.id} item={i} />
                ))}
            </ul>
        </OfficePage>
    );
}
