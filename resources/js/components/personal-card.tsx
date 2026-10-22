import { Avatar, PhotoUpload } from '@/components/avatar';
import { code128Bars } from '@/lib/barcode';
import { useI18n } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';
import { FileDown, GraduationCap, MapPin, Phone, Printer } from 'lucide-react';

export type CardData = {
    kind: 'student' | 'employee' | 'parent';
    name: string;
    photo: string | null;
    phone: string | null;
    email: string;
    number: string;
    valid_until: string;
    session: string;
    address: string | null;
    lines: [string, string][];
    extra: [string, string][];
    school: { name: string; phone: string; address: string };
};

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
const heading = { student: 'Student identity card', employee: 'Staff identity card', parent: 'Visiting pass' } as const;

export function CardFront({ c }: { c: CardData }) {
    const { t } = useI18n();
    const logo = (usePage().props as { branding?: { logo?: string } }).branding?.logo ?? '';
    const pass = c.kind === 'parent';
    const band = pass ? 'from-emerald-900 via-emerald-700 to-teal-600' : 'from-fuchsia-900 via-fuchsia-700 to-violet-700';

    return (
        <article className={shell} style={{ colorScheme: 'light' }}>
            <div className={`absolute inset-x-0 top-0 h-[58px] bg-gradient-to-r ${band}`} />
            <header className="relative flex items-center gap-2 px-3 pt-2 text-white">
                {logo ? <img src={logo} alt="" className="h-8 w-8 rounded bg-white/90 object-contain p-0.5" /> : <GraduationCap className="h-7 w-7" />}
                <div className="min-w-0">
                    <div className="truncate text-[13px] leading-tight font-bold">{c.school.name}</div>
                    <div className="text-[8px] tracking-[0.2em] uppercase opacity-80">{t(heading[c.kind])}</div>
                </div>
            </header>
            <div className="relative mt-3 flex gap-3 px-3">
                <div className="-mt-0.5 h-[92px] w-[74px] shrink-0 overflow-hidden rounded-lg border-[3px] border-white bg-slate-100 shadow-md">
                    {c.photo ? <img src={c.photo} alt={c.name} className="h-full w-full object-cover" /> : <Avatar name={c.name} className="h-full w-full rounded-none text-3xl" />}
                </div>
                <div className="min-w-0 flex-1 pt-3">
                    <div className={`truncate text-[15px] leading-tight font-extrabold ${pass ? 'text-emerald-900' : 'text-fuchsia-900'}`}>{c.name}</div>
                    <dl className="mt-1.5 grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-[10px]">
                        {c.lines.slice(0, 4).map(([k, v], i) => (
                            <div key={i} className="contents">
                                <dt className="text-slate-500">{t(k)}</dt>
                                <dd className="line-clamp-2 font-semibold">{v}</dd>
                            </div>
                        ))}
                        <dt className="text-slate-500">{t('ID')}</dt>
                        <dd className="font-semibold">{c.number}</dd>
                    </dl>
                </div>
            </div>
            <footer className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 border-t border-slate-100 bg-slate-50/80 px-3 py-1">
                <Barcode value={c.number} className="h-[22px] w-[150px]" />
                <div className="text-end text-[8px] leading-tight text-slate-500">
                    <div>
                        {t('Session')} {c.session}
                    </div>
                    <div className={`font-semibold ${pass ? 'text-emerald-800' : 'text-fuchsia-800'}`}>{t('Valid until')} {c.valid_until}</div>
                </div>
            </footer>
        </article>
    );
}

export function CardBack({ c }: { c: CardData }) {
    const { t } = useI18n();
    const pass = c.kind === 'parent';
    const band = pass ? 'from-emerald-900 via-emerald-700 to-teal-600' : 'from-fuchsia-900 via-fuchsia-700 to-violet-700';

    return (
        <article className={`${shell} flex flex-col`} style={{ colorScheme: 'light' }}>
            <div className={`h-2 bg-gradient-to-r ${band}`} />
            <div className="flex-1 space-y-1.5 px-3 pt-2 text-[9px] leading-snug">
                {c.extra.map(([k, v]) => (
                    <div key={k}>
                        <div className="text-[8px] font-bold tracking-wider text-slate-500 uppercase">{t(k)}</div>
                        <div className="text-[11px] font-semibold" dir="ltr">{v}</div>
                    </div>
                ))}
                {c.phone && (
                    <div className="flex items-center gap-1 text-slate-600" dir="ltr">
                        <Phone className="h-2.5 w-2.5" /> {c.phone}
                    </div>
                )}
                {c.address && (
                    <div className="flex items-start gap-1 text-slate-600">
                        <MapPin className="mt-0.5 h-2.5 w-2.5 shrink-0" />
                        <span className="line-clamp-2">{c.address}</span>
                    </div>
                )}
                <p className="border-t pt-1.5 text-[8px] text-slate-500">
                    {pass
                        ? t('Show this pass at the gate with a photo ID. It lets you visit the school; it is not an identity card.')
                        : t('This card belongs to the school and must be carried every day. If found, please return it to:')}
                </p>
                <div className="text-[9px] font-semibold">
                    {c.school.name}
                    {(c.school.address || c.school.phone) && <div className="font-normal text-slate-600">{[c.school.address, c.school.phone].filter(Boolean).join(' · ')}</div>}
                </div>
            </div>
            <div className="flex items-end justify-between gap-2 px-3 pb-2">
                <div className="text-center text-[8px] text-slate-500">
                    <div className="mb-0.5 h-px w-20 bg-slate-400" />
                    {t(pass ? 'Gate officer' : 'Principal')}
                </div>
                <Barcode value={c.number} className="h-[26px] w-[130px]" />
            </div>
        </article>
    );
}

/** The person's own card with a photo changer and a print / save-as-PDF button. */
export function MyCard({ card, userId }: { card: CardData; userId: number }) {
    const { t } = useI18n();

    return (
        <div className="space-y-5">
            <div className="no-print flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4">
                <PhotoUpload userId={userId} name={card.name} src={card.photo} />
                <span className="ms-auto flex flex-wrap gap-2">
                    <a href="/card.pdf" className="flex items-center gap-2 rounded-lg bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800">
                        <FileDown className="h-4 w-4" /> {t('Download PDF')}
                    </a>
                    <button onClick={() => window.print()} className="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50">
                        <Printer className="h-4 w-4" /> {t('Print')}
                    </button>
                </span>
            </div>
            <div className="flex flex-wrap gap-6 print:gap-3">
                <CardFront c={card} />
                <CardBack c={card} />
            </div>
        </div>
    );
}
