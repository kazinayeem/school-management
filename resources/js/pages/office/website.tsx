import { OfficeShell } from '@/components/office-shell';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Field = { name: string; label: string; type: 'text' | 'textarea' | 'number' | 'date' | 'checkbox'; hint?: string; default?: unknown };
type Definition = { label: string; subtitle: string; columns?: string[]; fields: Field[] };
type Row = Record<string, any> & { id: number };

const input = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-fuchsia-500';

const initial = (fields: Field[], row?: Row) =>
    Object.fromEntries(
        fields.map((f) => [f.name, row ? (f.type === 'checkbox' ? Boolean(row[f.name]) : (row[f.name] ?? '')) : (f.default ?? (f.type === 'checkbox' ? false : ''))]),
    );

function Fields({ fields, form }: { fields: Field[]; form: ReturnType<typeof useForm<Record<string, any>>> }) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {fields.map((f) => (
                <div key={f.name} className={f.type === 'textarea' ? 'md:col-span-2' : ''}>
                    {f.type === 'checkbox' ? (
                        <label className="flex items-center gap-2 pt-6 text-sm font-medium text-slate-700">
                            <input type="checkbox" checked={Boolean(form.data[f.name])} onChange={(e) => form.setData(f.name, e.target.checked)} />
                            {f.label}
                        </label>
                    ) : (
                        <>
                            <label className="mb-1 block text-sm font-medium text-slate-700">{f.label}</label>
                            {f.type === 'textarea' ? (
                                <textarea rows={4} className={input} value={form.data[f.name] ?? ''} onChange={(e) => form.setData(f.name, e.target.value)} />
                            ) : (
                                <input type={f.type} className={input} value={form.data[f.name] ?? ''} onChange={(e) => form.setData(f.name, e.target.value)} />
                            )}
                            {f.hint && <p className="mt-1 text-xs text-slate-400">{f.hint}</p>}
                        </>
                    )}
                    {form.errors[f.name] && <p className="mt-1 text-xs text-red-600">{form.errors[f.name]}</p>}
                </div>
            ))}
        </div>
    );
}

function Settings({ definition, values }: { definition: Definition; values: Record<string, any> }) {
    const form = useForm<Record<string, any>>(values);
    const [saved, setSaved] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/website/settings', { preserveScroll: true, onSuccess: () => setSaved(true) });
    };

    return (
        <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <Fields fields={definition.fields} form={form} />
            <div className="flex items-center gap-3">
                <button disabled={form.processing} className="rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-60">
                    Save settings
                </button>
                {saved && !form.isDirty && <span className="text-sm text-green-600">Saved.</span>}
            </div>
        </form>
    );
}

function Crud({ resource, definition, rows }: { resource: string; definition: Definition; rows: Row[] }) {
    const [editing, setEditing] = useState<Row | 'new' | null>(null);
    const form = useForm<Record<string, any>>(initial(definition.fields));
    const url = `/app/website/${resource}`;

    const open = (row: Row | 'new') => {
        form.clearErrors();
        form.setData(initial(definition.fields, row === 'new' ? undefined : row));
        setEditing(row);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: () => setEditing(null) };
        if (editing === 'new') form.post(url, done);
        else if (editing) form.put(`${url}/${editing.id}`, done);
    };

    const remove = (row: Row) => {
        if (confirm('Delete this entry?')) router.delete(`${url}/${row.id}`, { preserveScroll: true });
    };

    const columns = definition.columns ?? [];
    const label = (c: string) => c.replace(/_/g, ' ');
    const cell = (v: unknown) => (typeof v === 'number' && (v === 0 || v === 1) ? null : (v as string));

    return (
        <div className="space-y-5">
            <div className="flex justify-end">
                <button onClick={() => open('new')} className="flex items-center gap-1 rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800">
                    <Plus className="h-4 w-4" /> Add new
                </button>
            </div>

            {editing && (
                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-fuchsia-200 bg-white p-6 shadow-sm">
                    <h2 className="font-semibold">{editing === 'new' ? 'Add entry' : 'Edit entry'}</h2>
                    <Fields fields={definition.fields} form={form} />
                    <div className="flex gap-3">
                        <button disabled={form.processing} className="rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">
                            Save
                        </button>
                        <button type="button" onClick={() => setEditing(null)} className="rounded-lg border px-5 py-2 text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            )}

            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                {rows.length === 0 ? (
                    <p className="px-6 py-16 text-center text-sm text-slate-400">No entries yet.</p>
                ) : (
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                {columns.map((c) => (
                                    <th key={c} className="px-4 py-3 text-left font-semibold">{label(c)}</th>
                                ))}
                                <th className="px-4 py-3" />
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
                                                    <span className={`rounded-full px-2 py-0.5 text-xs ${row[c] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'}`}>
                                                        {row[c] ? 'Yes' : 'No'}
                                                    </span>
                                                ) : (
                                                    (cell(row[c]) ?? row[c])
                                                )}
                                            </td>
                                        );
                                    })}
                                    <td className="space-x-2 px-4 py-3 text-right whitespace-nowrap">
                                        <button aria-label="Edit" onClick={() => open(row)} className="text-slate-500 hover:text-fuchsia-700">
                                            <Pencil className="inline h-4 w-4" />
                                        </button>
                                        <button aria-label="Delete" onClick={() => remove(row)} className="text-slate-500 hover:text-red-600">
                                            <Trash2 className="inline h-4 w-4" />
                                        </button>
                                    </td>
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
    definition,
    rows = [],
    values = {},
}: {
    navigation: Parameters<typeof OfficeShell>[0]['navigation'];
    roleLabel: string;
    resource: string;
    definition: Definition;
    rows?: Row[];
    values?: Record<string, any>;
}) {
    return (
        <OfficeShell navigation={navigation} roleLabel={roleLabel}>
            <Head title={definition.label} />
            <div className="mx-auto max-w-[1200px] space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{definition.label}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">{definition.subtitle}</p>
                </div>
                {resource === 'settings' ? (
                    <Settings key={resource} definition={definition} values={values} />
                ) : (
                    <Crud key={resource} resource={resource} definition={definition} rows={rows} />
                )}
            </div>
        </OfficeShell>
    );
}
