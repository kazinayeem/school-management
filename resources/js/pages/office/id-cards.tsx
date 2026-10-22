import { Avatar } from '@/components/avatar';
import { Card, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { code128Bars } from '@/lib/barcode';
import { useI18n } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';
import { GraduationCap, MapPin, Phone, Printer } from 'lucide-react';
import { useState } from 'react';

type Student = {
    id: number;
    name: string;
    photo_url: string | null;
    admission_no: string;
    roll_no: string;
    section: string;
    session: string;
    guardian_name: string | null;
    guardian_mobile: string | null;
    gender: string | null;
    date_of_birth: string | null;
    address: string | null;
};
type School = { name: string; phone: string; address: string };

function Barcode({ value, className = '' }: { value: string; className?: string }) {
    const { bars, width } = code128Bars(value);

    return (
        <svg role="img" aria-label={`Barcode ${value}`} viewBox={`0 0 ${width} 40`} preserveAspectRatio="none" className={className}>
            <rect width={width} height="40" fill="#fff" />
            {bars.map((b, i) => (
                <rect key={i} x={b.x} y="0" width={b.w} height="40" fill="#111" />
            ))}
        </svg>
    );
}

const shell = 'print-card relative h-[214px] w-[340px] shrink-0 overflow-hidden rounded-xl border border-slate-300 bg-white text-slate-900 shadow-sm';

function Front({ s, cls, school, logo }: { s: Student; cls: string; school: School; logo: string }) {
    const { t } = useI18n();

    return (
        <article className={shell} style={{ colorScheme: 'light' }}>
            <div className="absolute inset-x-0 top-0 h-[58px] bg-gradient-to-r from-fuchsia-900 via-fuchsia-700 to-violet-700" />
            <div className="absolute -end-10 -top-10 h-28 w-28 rounded-full bg-white/10" />
            <header className="relative flex items-center gap-2 px-3 pt-2 text-white">
                {logo ? (
                    <img src={logo} alt="" className="h-8 w-8 rounded bg-white/90 object-contain p-0.5" />
                ) : (
                    <GraduationCap className="h-7 w-7" />
                )}
                <div className="min-w-0">
                    <div className="truncate text-[13px] leading-tight font-bold">{school.name}</div>
                    <div className="text-[8px] tracking-[0.2em] uppercase opacity-80">{t('Student identity card')}</div>
                </div>
            </header>

            <div className="relative mt-3 flex gap-3 px-3">
                <div className="-mt-0.5 h-[92px] w-[74px] shrink-0 overflow-hidden rounded-lg border-[3px] border-white bg-slate-100 shadow-md">
                    {s.photo_url ? (
                        <img src={s.photo_url} alt={s.name} className="h-full w-full object-cover" />
                    ) : (
                        <Avatar name={s.name} className="h-full w-full rounded-none text-3xl" />
                    )}
                </div>
                <div className="min-w-0 flex-1 pt-3">
                    <div className="truncate text-[15px] leading-tight font-extrabold text-fuchsia-900">{s.name}</div>
                    <dl className="mt-1.5 grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-[10px]">
                        <dt className="text-slate-500">{t('Class')}</dt>
                        <dd className="font-semibold">
                            {t(cls)} · {s.section}
                        </dd>
                        <dt className="text-slate-500">{t('Roll')}</dt>
                        <dd className="font-semibold">{s.roll_no}</dd>
                        <dt className="text-slate-500">{t('ID')}</dt>
                        <dd className="font-semibold">{s.admission_no}</dd>
                        {s.date_of_birth && (
                            <>
                                <dt className="text-slate-500">{t('Born')}</dt>
                                <dd className="font-semibold">{s.date_of_birth}</dd>
                            </>
                        )}
                    </dl>
                </div>
            </div>

            <footer className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 border-t border-slate-100 bg-slate-50/80 px-3 py-1">
                <Barcode value={s.admission_no} className="h-[22px] w-[150px]" />
                <div className="text-end text-[8px] leading-tight text-slate-500">
                    <div>
                        {t('Session')} {s.session}
                    </div>
                    <div className="font-semibold text-fuchsia-800">{t('Valid until Dec {year}', { year: s.session })}</div>
                </div>
            </footer>
        </article>
    );
}

function Back({ s, school }: { s: Student; school: School }) {
    const { t } = useI18n();

    return (
        <article className={`${shell} flex flex-col`} style={{ colorScheme: 'light' }}>
            <div className="h-2 bg-gradient-to-r from-fuchsia-900 via-fuchsia-700 to-violet-700" />
            <div className="flex-1 space-y-1.5 px-3 pt-2 text-[9px] leading-snug">
                <div>
                    <div className="text-[8px] font-bold tracking-wider text-fuchsia-800 uppercase">{t('Guardian')}</div>
                    <div className="text-[11px] font-semibold">{s.guardian_name ?? '—'}</div>
                    {s.guardian_mobile && (
                        <div className="flex items-center gap-1 text-slate-600" dir="ltr">
                            <Phone className="h-2.5 w-2.5" /> {s.guardian_mobile}
                        </div>
                    )}
                </div>
                {s.address && (
                    <div className="flex items-start gap-1 text-slate-600">
                        <MapPin className="mt-0.5 h-2.5 w-2.5 shrink-0" />
                        <span className="line-clamp-2">{s.address}</span>
                    </div>
                )}
                <p className="border-t pt-1.5 text-[8px] text-slate-500">
                    {t('This card belongs to the school and must be carried every day. If found, please return it to:')}
                </p>
                <div className="text-[9px] font-semibold">
                    {school.name}
                    {(school.address || school.phone) && (
                        <div className="font-normal text-slate-600">{[school.address, school.phone].filter(Boolean).join(' · ')}</div>
                    )}
                </div>
            </div>
            <div className="flex items-end justify-between gap-2 px-3 pb-2">
                <div className="text-center text-[8px] text-slate-500">
                    <div className="mb-0.5 h-px w-20 bg-slate-400" />
                    {t('Principal')}
                </div>
                <Barcode value={s.admission_no} className="h-[26px] w-[130px]" />
            </div>
        </article>
    );
}

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
    school: School;
}) {
    const { t } = useI18n();
    const logo = (usePage().props as { branding?: { logo?: string } }).branding?.logo ?? '';
    const [roll, setRoll] = useState(filters.roll);
    const [both, setBoth] = useState(true);
    const go = (next: Record<string, string>) =>
        router.get('/app/students/id-cards', { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="ID Cards"
            subtitle="Print student ID cards, front and back, for a class, a section or one student."
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
                <label className="flex items-center gap-2 pb-2 text-sm">
                    <input type="checkbox" checked={both} onChange={(e) => setBoth(e.target.checked)} /> {t('Print the back too')}
                </label>
                <button onClick={() => window.print()} disabled={students.length === 0} className={`${primaryBtn} ms-auto flex items-center gap-2`}>
                    <Printer className="h-4 w-4" /> {t('Print')}
                </button>
            </Card>

            {students.length === 0 ? (
                <p className="text-sm text-slate-500">{t('No students found.')}</p>
            ) : (
                <div className="flex flex-wrap gap-6 print:gap-3">
                    {students.map((s) => (
                        <div key={s.id} className="flex break-inside-avoid flex-wrap gap-3">
                            <Front s={s} cls={filters.class} school={school} logo={logo} />
                            {both && <Back s={s} school={school} />}
                        </div>
                    ))}
                </div>
            )}
        </OfficePage>
    );
}
