import { Card, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { GraduationCap, Printer } from 'lucide-react';
import { useState } from 'react';

type Student = {
    id: number;
    name: string;
    admission_no: string;
    roll_no: string;
    section: string;
    session: string;
    guardian_mobile: string | null;
    gender: string | null;
};

export default function IdCards({
    navigation,
    roleLabel,
    classes,
    filters,
    students,
    school,
}: {
    navigation: Nav;
    roleLabel: string;
    classes: string[];
    filters: { class: string; section: string; roll: string };
    students: Student[];
    school: { name: string; phone: string; address: string };
}) {
    const { t } = useI18n();
    const [roll, setRoll] = useState(filters.roll);
    const go = (next: Record<string, string>) =>
        router.get('/app/students/id-cards', { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="ID Cards"
            subtitle="Print student ID cards for a class, a section or one student."
            wide
        >
            <Card className="no-print flex flex-wrap items-end gap-4">
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
                <button onClick={() => window.print()} disabled={students.length === 0} className={`${primaryBtn} ms-auto flex items-center gap-2`}>
                    <Printer className="h-4 w-4" /> {t('Print')}
                </button>
            </Card>

            {students.length === 0 ? (
                <p className="text-sm text-slate-500">{t('No students found.')}</p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 print:grid-cols-3">
                    {students.map((s) => (
                        <article
                            key={s.id}
                            className="print-card overflow-hidden rounded-xl border-2 border-fuchsia-800 bg-white text-sm"
                            style={{ colorScheme: 'light', color: '#0f172a' }}
                        >
                            <header className="flex items-center gap-2 bg-fuchsia-800 px-3 py-2 text-white">
                                <GraduationCap className="h-5 w-5" />
                                <div className="min-w-0">
                                    <div className="truncate font-bold">{school.name}</div>
                                    <div className="text-[10px] opacity-80">{t('Student ID card')}</div>
                                </div>
                            </header>
                            <div className="flex gap-3 p-3">
                                <div className="flex h-24 w-20 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-3xl font-bold text-fuchsia-800">
                                    {s.name.charAt(0)}
                                </div>
                                <dl className="min-w-0 space-y-0.5 text-xs">
                                    <dd className="truncate text-sm font-bold">{s.name}</dd>
                                    <div>
                                        <dt className="inline text-slate-500">{t('Class')}: </dt>
                                        <dd className="inline">
                                            {t(filters.class)} · {s.section}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="inline text-slate-500">{t('Roll')}: </dt>
                                        <dd className="inline">{s.roll_no}</dd>
                                    </div>
                                    <div>
                                        <dt className="inline text-slate-500">{t('Admission no.')}: </dt>
                                        <dd className="inline">{s.admission_no}</dd>
                                    </div>
                                    <div>
                                        <dt className="inline text-slate-500">{t('Session')}: </dt>
                                        <dd className="inline">{s.session}</dd>
                                    </div>
                                    {s.guardian_mobile && (
                                        <div>
                                            <dt className="inline text-slate-500">{t('Guardian')}: </dt>
                                            <dd className="inline" dir="ltr">
                                                {s.guardian_mobile}
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                            <footer className="border-t bg-slate-50 px-3 py-1.5 text-center text-[10px] text-slate-500">
                                {[school.address, school.phone].filter(Boolean).join(' · ') || t('If found, please return to the school office.')}
                            </footer>
                        </article>
                    ))}
                </div>
            )}
        </OfficePage>
    );
}
