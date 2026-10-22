import { FlashStatus } from '@/components/flash';
import { OfficeShell } from '@/components/office-shell';
import { useI18n } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { FormEvent, useEffect } from 'react';

type Student = { id: number; name: string; section: string; roll_no: string; admission_no: string };

export default function Promotion({
    navigation,
    roleLabel,
    classes,
    filters,
    students,
    result,
}: {
    navigation: Parameters<typeof OfficeShell>[0]['navigation'];
    roleLabel: string;
    classes: string[];
    filters: { class: string; section: string };
    students: Student[];
    result?: { moved: number; to: string } | null;
}) {
    const { t } = useI18n();
    const nextClass = classes[classes.indexOf(filters.class) + 1] ?? 'Passed out';
    const form = useForm<{ from_class: string; to_class: string; student_ids: number[] }>({
        from_class: filters.class,
        to_class: nextClass,
        student_ids: [],
    });

    useEffect(() => {
        form.setData({ from_class: filters.class, to_class: nextClass, student_ids: [] });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.class, filters.section]);

    const filter = (next: Partial<{ class: string; section: string }>) =>
        router.get('/app/students/promotion', { ...filters, ...next }, { preserveState: true, replace: true });

    const toggle = (id: number) =>
        form.setData(
            'student_ids',
            form.data.student_ids.includes(id) ? form.data.student_ids.filter((x) => x !== id) : [...form.data.student_ids, id],
        );

    const allSelected = students.length > 0 && form.data.student_ids.length === students.length;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/students/promotion', { preserveScroll: true });
    };

    return (
        <OfficeShell navigation={navigation} roleLabel={roleLabel}>
            <Head title={t('Promotion')} />
            <div className="mx-auto max-w-[1100px] space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{t('Promotion')}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">{t('Move students to the next class at the end of the year.')}</p>
                </div>
                <FlashStatus />

                {result && (
                    <div role="status" className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {t('{moved} students moved to {to}.', { moved: result.moved, to: t(result.to) })}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">{t('From class')}</label>
                            <select
                                value={filters.class}
                                onChange={(e) => filter({ class: e.target.value })}
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            >
                                {classes.map((c) => (
                                    <option key={c} value={c}>
                                        {t(c)}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">{t('Section')}</label>
                            <select
                                value={filters.section}
                                onChange={(e) => filter({ section: e.target.value })}
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            >
                                <option value="">{t('All sections')}</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                            </select>
                        </div>
                        <div className="flex items-end justify-center pb-2 text-slate-400">
                            <ArrowRight className="h-5 w-5 rtl:rotate-180" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">{t('Promote to')}</label>
                            <select
                                value={form.data.to_class}
                                onChange={(e) => form.setData('to_class', e.target.value)}
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            >
                                {[...classes.filter((c) => c !== filters.class), 'Passed out'].map((c) => (
                                    <option key={c} value={c}>
                                        {t(c)}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded-xl border border-slate-200">
                        {students.length === 0 ? (
                            <p className="px-6 py-12 text-center text-sm text-slate-400">{t('No active students in this class.')}</p>
                        ) : (
                            <table className="w-full min-w-[520px] text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                    <tr>
                                        <th className="w-10 px-4 py-3">
                                            <input
                                                type="checkbox"
                                                aria-label={t('Select all')}
                                                checked={allSelected}
                                                onChange={() => form.setData('student_ids', allSelected ? [] : students.map((s) => s.id))}
                                            />
                                        </th>
                                        <th className="px-4 py-3 text-start font-semibold">{t('Name')}</th>
                                        <th className="px-4 py-3 text-start font-semibold">{t('Admission no.')}</th>
                                        <th className="px-4 py-3 text-start font-semibold">{t('Section')}</th>
                                        <th className="px-4 py-3 text-start font-semibold">{t('Roll')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {students.map((s) => (
                                        <tr key={s.id} className="hover:bg-slate-50">
                                            <td className="px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.student_ids.includes(s.id)}
                                                    onChange={() => toggle(s.id)}
                                                    aria-label={s.name}
                                                />
                                            </td>
                                            <td className="px-4 py-3">{s.name}</td>
                                            <td className="px-4 py-3">{s.admission_no}</td>
                                            <td className="px-4 py-3">{s.section}</td>
                                            <td className="px-4 py-3">{s.roll_no}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    {(form.errors.student_ids || form.errors.to_class) && (
                        <p className="text-sm text-red-600">{t(form.errors.student_ids ?? form.errors.to_class ?? '')}</p>
                    )}

                    <div className="flex items-center gap-4">
                        <button
                            disabled={form.processing || form.data.student_ids.length === 0}
                            className="rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-50"
                        >
                            {t('Promote {n} students', { n: form.data.student_ids.length })}
                        </button>
                    </div>
                </form>
            </div>
        </OfficeShell>
    );
}
