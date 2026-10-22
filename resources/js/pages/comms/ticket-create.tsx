import { CommsPage, FilePicker, type Shell } from '@/components/comms/ui';
import { RichEditor } from '@/components/comms/rich-editor';
import { useI18n } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { Loader2, Send } from 'lucide-react';
import { FormEvent } from 'react';

const field = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-fuchsia-500';

export default function TicketCreate({ shell, meta, students, teachers, roles, preset, limits }: { shell: Shell; meta: { categories: Record<string, string>; priorities: Record<string, string> }; students: { id: number; name: string; class_name: string; section: string; admission_no: string }[]; teachers: { id: number; name: string }[]; roles: { value: string; label: string }[]; preset: { student_id: number | null; category: string }; limits: { files: number; kb: number; types: string[] } }) {
    const { t } = useI18n();
    const form = useForm<{ title: string; category: string; priority: string; description: string; recipient_role: string; assigned_to: string; student_id: string; teacher_id: string; files: File[] }>({
        title: '', category: meta.categories[preset.category] ? preset.category : 'other', priority: 'normal', description: '', recipient_role: 'admin', assigned_to: '', student_id: preset.student_id ? String(preset.student_id) : '', teacher_id: '', files: [],
    });
    const errors = form.errors as Record<string, string>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (form.processing) return;
        form.post(`${shell.base}/tickets`, { forceFormData: true, preserveScroll: true });
    };
    const err = (k: string) => errors[k] && <span role="alert" className="mt-1 block text-xs text-rose-600">{errors[k]}</span>;

    return (
        <CommsPage shell={shell} title="New ticket" subtitle="Raise a complaint, an issue or a request. You can follow it here until it is resolved.">
            <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium">{t('Title')}</span>
                    <input className={field} maxLength={200} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder={t('A short summary of the issue')} />
                    {err('title')}
                </label>
                <div className="grid gap-4 md:grid-cols-3">
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium">{t('Category')}</span>
                        <select className={field} value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                            {Object.entries(meta.categories).map(([k, v]) => <option key={k} value={k}>{t(v)}</option>)}
                        </select>
                        {err('category')}
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium">{t('Priority')}</span>
                        <select className={field} value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}>
                            {Object.entries(meta.priorities).map(([k, v]) => <option key={k} value={k}>{t(v)}</option>)}
                        </select>
                        {err('priority')}
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium">{t('Send to')}</span>
                        <select className={field} value={form.data.recipient_role} onChange={(e) => form.setData('recipient_role', e.target.value)}>
                            {roles.filter((r) => ['admin', 'teacher', 'staff'].includes(r.value)).map((r) => <option key={r.value} value={r.value}>{t(r.label)}</option>)}
                        </select>
                        <span className="mt-1 block text-xs text-slate-400">{t('The school office reviews every ticket.')}</span>
                    </label>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {students.length > 0 && (
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">{t('About which student? (optional)')}</span>
                            <select className={field} value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>
                                <option value="">{t('Not about a student')}</option>
                                {students.map((s) => <option key={s.id} value={s.id}>{s.name} · {s.class_name} {s.section}</option>)}
                            </select>
                            {err('student_id')}
                        </label>
                    )}
                    {teachers.length > 0 && (
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">{t('About which teacher? (optional)')}</span>
                            <select className={field} value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)}>
                                <option value="">{t('Not about a teacher')}</option>
                                {teachers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                            <span className="mt-1 block text-xs text-slate-400">{t('A teacher you name cannot see this ticket.')}</span>
                            {err('teacher_id')}
                        </label>
                    )}
                </div>
                <RichEditor label={t('Describe the issue')} value={form.data.description} onChange={(v) => form.setData('description', v)} error={errors.description} minHeight={200} />
                <div>
                    <span className="mb-1 block text-sm font-medium">{t('Evidence or supporting documents (optional)')}</span>
                    <FilePicker files={form.data.files} onChange={(f) => form.setData('files', f)} limits={limits} error={errors['files.0'] ?? errors.files} />
                </div>
                <div className="flex flex-wrap gap-3">
                    <button disabled={form.processing || !form.data.title.trim() || !form.data.description} className="inline-flex items-center gap-2 rounded-xl bg-fuchsia-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-fuchsia-800 disabled:opacity-50">
                        {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />} {form.processing ? t('Submitting…') : t('Submit ticket')}
                    </button>
                    <Link href={`${shell.base}/tickets`} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm hover:bg-slate-50">{t('Cancel')}</Link>
                </div>
            </form>
        </CommsPage>
    );
}
