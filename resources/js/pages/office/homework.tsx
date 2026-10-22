import { Badge, Card, Field, inputCls, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { class_name: string; section: string; subjects: { id: number; name: string }[] };
type Row = { batch: string; title: string; subject: string; teacher: string; due_on: string; class_name: string; section: string; total: number; waiting: number; marked: number };

export default function Homework({ navigation, roleLabel, rows, options }: { navigation: any; roleLabel: string; rows: Row[]; options: Option[] }) {
    const { t } = useI18n();
    const first = options[0];
    const form = useForm({ title: '', class_name: first?.class_name ?? '', section: first?.section ?? 'A', subject_id: String(first?.subjects[0]?.id ?? ''), due_on: '', instructions: '' });
    const current = options.find((o) => o.class_name === form.data.class_name && o.section === form.data.section);
    const classes = [...new Set(options.map((o) => o.class_name))];
    const sections = options.filter((o) => o.class_name === form.data.class_name).map((o) => o.section);

    const pick = (class_name: string, section?: string) => {
        const o = options.find((x) => x.class_name === class_name && (section ? x.section === section : true)) ?? options.find((x) => x.class_name === class_name);
        if (o) form.setData({ ...form.data, class_name: o.class_name, section: o.section, subject_id: String(o.subjects[0]?.id ?? '') });
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/homework', { preserveScroll: true });
    };

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Homework" subtitle="Give homework to your own sections, then check it and leave feedback." wide>
            {options.length === 0 ? (
                <Card>
                    <p className="text-sm text-slate-500">{t('No classes have been assigned to you yet. Ask an administrator to add your teaching assignments.')}</p>
                </Card>
            ) : (
                <Card>
                    <form onSubmit={submit} className="grid gap-4 md:grid-cols-4">
                        <div className="md:col-span-2">
                            <Field label={t('Title')} error={form.errors.title}>
                                <input className={inputCls} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                            </Field>
                        </div>
                        <Field label={t('Class')} error={form.errors.class_name}>
                            <select className={inputCls} value={form.data.class_name} onChange={(e) => pick(e.target.value)}>
                                {classes.map((c) => (
                                    <option key={c} value={c}>
                                        {t(c)}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label={t('Section')} error={form.errors.section}>
                            <select className={inputCls} value={form.data.section} onChange={(e) => pick(form.data.class_name, e.target.value)}>
                                {sections.map((s) => (
                                    <option key={s}>{s}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label={t('Subject')} error={form.errors.subject_id}>
                            <select className={inputCls} value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                                {(current?.subjects ?? []).map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label={t('Due date')} error={form.errors.due_on}>
                            <input type="date" className={inputCls} value={form.data.due_on} onChange={(e) => form.setData('due_on', e.target.value)} />
                        </Field>
                        <div className="md:col-span-4">
                            <Field label={t('Instructions')} error={form.errors.instructions}>
                                <textarea rows={3} className={inputCls} value={form.data.instructions} onChange={(e) => form.setData('instructions', e.target.value)} />
                            </Field>
                        </div>
                        <div className="md:col-span-4">
                            <button className={primaryBtn} disabled={form.processing}>
                                {t('Give homework')}
                            </button>
                        </div>
                    </form>
                </Card>
            )}

            <Table headers={['Homework', 'Class', 'Due', 'Handed in', 'Checked', '']} empty={rows.length === 0 ? 'No homework yet.' : null}>
                {rows.map((r) => (
                    <tr key={r.batch} className="border-t border-slate-100">
                        <td className="px-4 py-3">
                            <div className="font-medium">{r.title}</div>
                            <div className="text-xs text-slate-500">
                                {r.subject} · {r.teacher}
                            </div>
                        </td>
                        <td className="px-4 py-3">
                            {r.class_name} · {r.section}
                        </td>
                        <td className="px-4 py-3">{r.due_on}</td>
                        <td className="px-4 py-3">{r.waiting > 0 ? <Badge tone="amber">{t('{n} waiting', { n: r.waiting })}</Badge> : <Badge tone="slate">0</Badge>}</td>
                        <td className="px-4 py-3">
                            {r.marked}/{r.total}
                        </td>
                        <td className="px-4 py-3 text-end">
                            <Link href={`/app/homework/${r.batch}`} className="font-medium text-fuchsia-700">
                                {t('Check')}
                            </Link>
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
