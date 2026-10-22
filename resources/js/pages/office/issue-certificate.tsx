import { Card, Field, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { PickedStudent, StudentSelect } from '@/components/student-picker';
import { useI18n } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Recent = { id: number; number: string; title: string; issued_on: string; name: string };

export default function IssueCertificate({
    navigation,
    roleLabel,
    templates,
    recent,
}: {
    navigation: Nav;
    roleLabel: string;
    templates: { id: number; name: string; body: string; style: string; show_photo: number }[];
    recent: Recent[];
}) {
    const { t } = useI18n();
    const [picked, setPicked] = useState<PickedStudent | null>(null);
    const form = useForm({ template_id: templates[0] ? String(templates[0].id) : '', student: '', date: new Date().toISOString().slice(0, 10) });
    const template = templates.find((x) => String(x.id) === form.data.template_id);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/certificates');
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Issue Certificate"
            subtitle="Create a certificate for a student from a template."
        >
            <form onSubmit={submit}>
                <Card className="space-y-4">
                    {templates.length === 0 ? (
                        <p className="text-sm text-slate-500">{t('Add a certificate template first.')}</p>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Template" error={form.errors.template_id}>
                                <select
                                    className={inputCls}
                                    value={form.data.template_id}
                                    onChange={(e) => form.setData('template_id', e.target.value)}
                                >
                                    {templates.map((x) => (
                                        <option key={x.id} value={x.id}>
                                            {x.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <StudentSelect
                                label="Student"
                                value={picked}
                                error={form.errors.student}
                                onChange={(s) => {
                                    setPicked(s);
                                    form.setData('student', s?.admission_no ?? '');
                                }}
                            />
                            <Field label="Issue date" error={form.errors.date}>
                                <input
                                    type="date"
                                    className={inputCls}
                                    value={form.data.date}
                                    onChange={(e) => form.setData('date', e.target.value)}
                                />
                            </Field>
                        </div>
                    )}
                    {template && (
                        <p className="text-xs text-slate-500">
                            {t('Design')}: <b>{t(template.style === 'modern' ? 'Modern' : template.style === 'elegant' ? 'Elegant' : 'Classic')}</b>
                            {template.show_photo ? ` · ${t("with the student's photo")}` : ''}
                        </p>
                    )}
                    {template && <pre className="rounded-lg bg-slate-50 p-3 text-xs whitespace-pre-wrap text-slate-600">{template.body}</pre>}
                    <button disabled={form.processing || templates.length === 0} className={primaryBtn}>
                        {t('Issue and print')}
                    </button>
                </Card>
            </form>

            <h2 className="font-semibold">{t('Recently issued')}</h2>
            <Table headers={['Number', 'Student', 'Certificate', 'Issued on', '']} empty={recent.length === 0 ? 'No certificates issued yet.' : null}>
                {recent.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 font-mono text-xs">{r.number}</td>
                        <td className="px-4 py-3">{r.name}</td>
                        <td className="px-4 py-3">{r.title}</td>
                        <td className="px-4 py-3">{r.issued_on}</td>
                        <td className="px-4 py-3 text-end">
                            <a href={`/app/certificates/${r.id}/print`} target="_blank" className="text-fuchsia-700" rel="noreferrer">
                                {t('Print')}
                            </a>
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
