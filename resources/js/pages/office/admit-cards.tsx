import { Avatar } from '@/components/avatar';
import { Card, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useState } from 'react';

type Student = { id: number; name: string; roll_no: string; section: string; admission_no: string; photo?: string | null };
type Slot = { subject: string; date: string; time: string; room: string | null };

export default function AdmitCards({
    navigation,
    roleLabel,
    exams,
    classes,
    filters,
    exam,
    schedule,
    students,
    school,
}: {
    navigation: Nav;
    roleLabel: string;
    exams: { id: number; name: string }[];
    classes: string[];
    filters: { exam: number; class: string; section: string; roll: string };
    exam: { id: number; name: string; starts_on: string; ends_on: string } | null;
    schedule: Slot[];
    students: Student[];
    school: { name: string };
}) {
    const { t } = useI18n();
    const [roll, setRoll] = useState(filters.roll);
    const go = (next: Record<string, string | number>) =>
        router.get('/app/exams/admit-cards', { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Admit Cards"
            subtitle="Print exam admit cards for a class, a section or one student."
            wide
        >
            <Card className="no-print flex flex-wrap items-end gap-4">
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Exam')}</label>
                    <select className={inputCls} value={filters.exam} onChange={(e) => go({ exam: e.target.value })}>
                        {exams.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Class')}</label>
                    <select className={inputCls} value={filters.class} onChange={(e) => go({ class: e.target.value })}>
                        {classes.map((c) => (
                            <option key={c} value={c}>
                                {t(c)}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Section')}</label>
                    <select className={inputCls} value={filters.section} onChange={(e) => go({ section: e.target.value })}>
                        <option value="">{t('All sections')}</option>
                        <option>A</option>
                        <option>B</option>
                    </select>
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        go({ roll });
                    }}
                >
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Roll')}</label>
                    <input className={`${inputCls} w-24`} value={roll} onChange={(e) => setRoll(e.target.value)} />
                </form>
                <button
                    onClick={() => window.print()}
                    disabled={students.length === 0 || schedule.length === 0}
                    className={`${primaryBtn} ms-auto flex items-center gap-2`}
                >
                    <Printer className="h-4 w-4" /> {t('Print')}
                </button>
            </Card>

            {!exam ? (
                <p className="text-sm text-slate-500">{t('Create an exam first.')}</p>
            ) : schedule.length === 0 ? (
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {t('No schedule has been set for this class yet. Add one in Exam Setup.')}
                </p>
            ) : students.length === 0 ? (
                <p className="text-sm text-slate-500">{t('No students found.')}</p>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2 print:grid-cols-2">
                    {students.map((s) => (
                        <article key={s.id} className="print-card rounded-xl border-2 border-slate-300 bg-white p-5 text-sm">
                            <header className="border-b pb-2 text-center">
                                <h3 className="font-bold">{school.name}</h3>
                                <p className="text-xs text-slate-500">{exam.name}</p>
                                <p className="mt-1 text-xs font-semibold tracking-widest uppercase">{t('Admit Card')}</p>
                            </header>
                            <div className="my-3 flex items-start gap-4">
                                <Avatar name={s.name} src={s.photo} className="h-24 w-20 shrink-0 rounded-md" />
                            <dl className="grid flex-1 grid-cols-2 gap-x-4 gap-y-1">
                                <div>
                                    <dt className="text-xs text-slate-400">{t('Name')}</dt>
                                    <dd className="font-medium">{s.name}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400">{t('Admission no.')}</dt>
                                    <dd>{s.admission_no}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400">{t('Class')}</dt>
                                    <dd>
                                        {t(filters.class)} · {s.section}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-400">{t('Roll')}</dt>
                                    <dd>{s.roll_no}</dd>
                                </div>
                            </dl>
                            </div>
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b text-slate-500">
                                        <th className="py-1 text-start">{t('Date')}</th>
                                        <th className="py-1 text-start">{t('Subject')}</th>
                                        <th className="py-1 text-start">{t('Time')}</th>
                                        <th className="py-1 text-start">{t('Room')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {schedule.map((x) => (
                                        <tr key={x.subject} className="border-b border-slate-100">
                                            <td className="py-1">{x.date}</td>
                                            <td className="py-1">{x.subject}</td>
                                            <td className="py-1">{x.time}</td>
                                            <td className="py-1">{x.room}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <footer className="mt-8 flex justify-between text-xs text-slate-500">
                                <span className="border-t px-4 pt-1">{t('Class teacher')}</span>
                                <span className="border-t px-4 pt-1">{t('Principal')}</span>
                            </footer>
                        </article>
                    ))}
                </div>
            )}
        </OfficePage>
    );
}
