import { TableExportMenu } from '@/components/pdf-button';
import { FlashStatus } from '@/components/flash';
import { RichEditor } from '@/components/comms/rich-editor';
import { OfficeShell } from '@/components/office-shell';
import { useI18n } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Field = {
    name: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date' | 'time' | 'datetime' | 'checkbox' | 'select' | 'richtext';
    hint?: string;
    step?: string;
    default?: unknown;
    options?: { value: string; label: string }[];
};
type Definition = {
    label: string;
    subtitle: string;
    columns?: string[];
    column_labels?: Record<string, string>;
    fields: Field[];
    test?: { url: string; label: string } | null;
};
type Row = Record<string, any> & { id: number };

const input = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-fuchsia-500';

const cell = (f: Field, value: unknown) => {
    const text = String(value ?? '');
    if (f.type === 'time') return text.slice(0, 5);
    if (f.type === 'datetime') return text.replace(' ', 'T').slice(0, 16);
    return text;
};

const initial = (fields: Field[], row?: Row) =>
    Object.fromEntries(
        fields.map((f) => [
            f.name,
            row ? (f.type === 'checkbox' ? Boolean(row[f.name]) : cell(f, row[f.name])) : (f.default ?? (f.type === 'checkbox' ? false : '')),
        ]),
    );

function Fields({ fields, form }: { fields: Field[]; form: ReturnType<typeof useForm<Record<string, any>>> }) {
    const { t } = useI18n();

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {fields.map((f) => (
                <div key={f.name} className={f.type === 'textarea' || f.type === 'richtext' ? 'md:col-span-2' : ''}>
                    {f.type === 'checkbox' ? (
                        <label className="flex items-center gap-2 pt-6 text-sm font-medium text-slate-700">
                            <input type="checkbox" checked={Boolean(form.data[f.name])} onChange={(e) => form.setData(f.name, e.target.checked)} />
                            {t(f.label)}
                        </label>
                    ) : (
                        <>
                            <label className="mb-1 block text-sm font-medium text-slate-700">{t(f.label)}</label>
                            {f.type === 'select' ? (
                                <select className={input} value={form.data[f.name] ?? ''} onChange={(e) => form.setData(f.name, e.target.value)}>
                                    {!f.options?.some((o) => o.value === '') && <option value="">{t('Choose...')}</option>}
                                    {(f.options ?? []).map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {t(o.label)}
                                        </option>
                                    ))}
                                </select>
                            ) : f.type === 'richtext' ? (
                                <RichEditor label={t(f.label)} value={form.data[f.name] ?? ''} onChange={(html) => form.setData(f.name, html)} minHeight={260} />
                            ) : f.type === 'textarea' ? (
                                <textarea
                                    rows={4}
                                    className={input}
                                    value={form.data[f.name] ?? ''}
                                    onChange={(e) => form.setData(f.name, e.target.value)}
                                />
                            ) : (
                                <input
                                    type={f.type === 'datetime' ? 'datetime-local' : f.type}
                                    step={f.step}
                                    className={input}
                                    value={form.data[f.name] ?? ''}
                                    onChange={(e) => form.setData(f.name, e.target.value)}
                                />
                            )}
                            {f.hint && <p className="mt-1 text-xs text-slate-400">{t(f.hint)}</p>}
                        </>
                    )}
                    {form.errors[f.name] && <p className="mt-1 text-xs text-red-600">{form.errors[f.name]}</p>}
                </div>
            ))}
        </div>
    );
}

function TestBox({ test }: { test: { url: string; label: string } }) {
    const { t } = useI18n();
    const form = useForm({ email: '' });

    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                form.post(test.url, { preserveScroll: true });
            }}
            className="mt-4 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
            <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">{t('Send the test to')}</label>
                <input
                    type="email"
                    className={input}
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    placeholder="you@example.com"
                />
                {form.errors.email && <p className="mt-1 text-xs text-red-600">{form.errors.email}</p>}
            </div>
            <button
                disabled={form.processing}
                className="rounded-lg border border-fuchsia-700 px-5 py-2 text-sm font-semibold text-fuchsia-700 hover:bg-fuchsia-50 disabled:opacity-60"
            >
                {t(test.label)}
            </button>
            <p className="w-full text-xs text-slate-500">{t('Save your settings first. The test uses the saved settings.')}</p>
        </form>
    );
}

function Settings({ definition, values, baseUrl }: { definition: Definition; values: Record<string, any>; baseUrl: string }) {
    const { t } = useI18n();
    const form = useForm<Record<string, any>>(values);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(baseUrl, { preserveScroll: true });
    };

    return (
        <>
            <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <Fields fields={definition.fields} form={form} />
                <button
                    disabled={form.processing}
                    className="rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-60"
                >
                    {t('Save settings')}
                </button>
            </form>
            {definition.test && <TestBox test={definition.test} />}
        </>
    );
}

function Crud({ baseUrl, definition, rows, readOnly }: { baseUrl: string; definition: Definition; rows: Row[]; readOnly: boolean }) {
    const { t } = useI18n();
    const [editing, setEditing] = useState<Row | 'new' | null>(null);
    const form = useForm<Record<string, any>>(initial(definition.fields));

    const open = (row: Row | 'new') => {
        form.clearErrors();
        form.setData(initial(definition.fields, row === 'new' ? undefined : row));
        setEditing(row);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: () => setEditing(null) };
        if (editing === 'new') form.post(baseUrl, done);
        else if (editing) form.put(`${baseUrl}/${editing.id}`, done);
    };

    const remove = (row: Row) => {
        if (confirm(t('Delete this entry?'))) router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const columns = definition.columns ?? [];
    const heading = (c: string) => t(definition.column_labels?.[c] ?? definition.fields.find((f) => f.name === c)?.label ?? c.replace(/_/g, ' '));

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap justify-end gap-2">
                {rows.length > 0 && columns.length > 0 && (
                    <TableExportMenu
                        build={() => ({
                            title: definition.label,
                            subtitle: definition.subtitle,
                            sections: [{ columns: columns.map((c) => ({ key: c, label: heading(c) })), rows: rows.map((r) => Object.fromEntries(columns.map((c) => [c, typeof r[c] === 'boolean' ? (r[c] ? 'Yes' : 'No') : (r[c] ?? '')]))) }],
                        })}
                    />
                )}
            {!readOnly && (
                <div className="flex justify-end">
                    <button
                        onClick={() => open('new')}
                        className="flex items-center gap-1 rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800"
                    >
                        <Plus className="h-4 w-4" /> {t('Add new')}
                    </button>
                </div>
            )}
            </div>

            {editing && (
                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-fuchsia-200 bg-white p-6 shadow-sm">
                    <h2 className="font-semibold">{editing === 'new' ? t('Add entry') : t('Edit entry')}</h2>
                    <Fields fields={definition.fields} form={form} />
                    <div className="flex gap-3">
                        <button
                            disabled={form.processing}
                            className="rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {t('Save')}
                        </button>
                        <button type="button" onClick={() => setEditing(null)} className="rounded-lg border px-5 py-2 text-sm">
                            {t('Cancel')}
                        </button>
                    </div>
                </form>
            )}

            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                {rows.length === 0 ? (
                    <p className="px-6 py-16 text-center text-sm text-slate-400">{t('No entries yet.')}</p>
                ) : (
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                {columns.map((c) => (
                                    <th key={c} className="px-4 py-3 text-start font-semibold">
                                        {heading(c)}
                                    </th>
                                ))}
                                {!readOnly && <th className="px-4 py-3" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((row) => (
                                <tr key={row.id} className="hover:bg-slate-50">
                                    {columns.map((c) => {
                                        const field = definition.fields.find((f) => f.name === c);
                                        return (
                                            <td key={c} className="max-w-xs truncate px-4 py-3 text-slate-700">
                                                {field?.type === 'checkbox' ? (
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs ${row[c] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'}`}
                                                    >
                                                        {row[c] ? t('Yes') : t('No')}
                                                    </span>
                                                ) : row['__' + c] !== undefined ? (
                                                    t(String(row['__' + c]))
                                                ) : (
                                                    row[c]
                                                )}
                                            </td>
                                        );
                                    })}
                                    {!readOnly && (
                                        <td className="space-x-2 px-4 py-3 text-end whitespace-nowrap">
                                            <button
                                                aria-label={t('Edit')}
                                                onClick={() => open(row)}
                                                className="text-slate-500 hover:text-fuchsia-700"
                                            >
                                                <Pencil className="inline h-4 w-4" />
                                            </button>
                                            <button
                                                aria-label={t('Delete')}
                                                onClick={() => remove(row)}
                                                className="text-slate-500 hover:text-red-600"
                                            >
                                                <Trash2 className="inline h-4 w-4" />
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

export default function Website({
    navigation,
    roleLabel,
    resource,
    baseUrl,
    definition,
    rows = [],
    values = {},
    readOnly = false,
}: {
    navigation: Parameters<typeof OfficeShell>[0]['navigation'];
    roleLabel: string;
    resource: string;
    baseUrl: string;
    definition: Definition;
    rows?: Row[];
    values?: Record<string, any>;
    readOnly?: boolean;
}) {
    const { t } = useI18n();

    return (
        <OfficeShell navigation={navigation} roleLabel={roleLabel}>
            <Head title={t(definition.label)} />
            <div className="mx-auto max-w-[1200px] space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{t(definition.label)}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">{t(definition.subtitle)}</p>
                </div>
                <FlashStatus />
                {resource === 'settings' ? (
                    <Settings key={baseUrl} definition={definition} values={values} baseUrl={baseUrl} />
                ) : (
                    <Crud key={baseUrl} baseUrl={baseUrl} definition={definition} rows={rows} readOnly={readOnly} />
                )}
            </div>
        </OfficeShell>
    );
}
