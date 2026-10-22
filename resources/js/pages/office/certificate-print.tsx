import { MadeBy } from '@/components/made-by';
import { Avatar } from '@/components/avatar';
import { code128Bars } from '@/lib/barcode';
import { useI18n } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { ReactNode, useEffect } from 'react';

type Certificate = {
    id: number;
    number: string;
    title: string;
    body: string;
    issued_on: string;
    name: string;
    style: 'classic' | 'modern' | 'elegant';
    show_photo: number | boolean;
    photo_url: string | null;
    class_name: string | null;
    section: string | null;
    admission_no: string | null;
};
type School = { name: string; address: string };

function Barcode({ value }: { value: string }) {
    const { bars, width } = code128Bars(value);

    return (
        <svg role="img" aria-label={`Barcode ${value}`} viewBox={`0 0 ${width} 30`} preserveAspectRatio="none" className="h-8 w-40">
            <rect width={width} height="30" fill="#fff" />
            {bars.map((b, i) => (
                <rect key={i} x={b.x} y="0" width={b.w} height="30" fill="#111" />
            ))}
        </svg>
    );
}

/** The student's name stands out inside the wording. */
function Wording({ text, name, className }: { text: string; name: string; className: string }) {
    const parts = text.split(name);

    return (
        <p className={`whitespace-pre-line ${className}`}>
            {parts.map((part, i) => (
                <span key={i}>
                    {part}
                    {i < parts.length - 1 && <strong className="font-bold">{name}</strong>}
                </span>
            ))}
        </p>
    );
}

function Seal({ tone }: { tone: string }) {
    return (
        <div
            className={`flex h-20 w-20 items-center justify-center rounded-full border-4 border-double text-center text-[8px] font-bold tracking-widest uppercase ${tone}`}
        >
            Official
            <br />
            seal
        </div>
    );
}

function Signatures({ date, t, line }: { date: string; t: (k: string) => string; line: string }) {
    return (
        <div className="flex items-end justify-between px-4 text-sm">
            <div className="text-center">
                <div className={`mb-1 h-px w-44 ${line}`} />
                {t('Class teacher')}
            </div>
            <div className="text-center text-xs opacity-70">
                {t('Date of issue')}
                <div className="text-sm font-semibold">{date}</div>
            </div>
            <div className="text-center">
                <div className={`mb-1 h-px w-44 ${line}`} />
                {t('Principal')}
            </div>
        </div>
    );
}

function Frame({ c, school, logo, children }: { c: Certificate; school: School; logo: string; children: ReactNode }) {
    const showPhoto = Boolean(c.show_photo);
    const heading = (
        <header className="text-center">
            <div className="flex items-center justify-center gap-3">
                {logo && <img src={logo} alt="" className="h-14 w-14 object-contain" />}
                <div>
                    <h1 className="text-2xl font-extrabold tracking-wide">{school.name}</h1>
                    {school.address && <p className="text-xs opacity-70">{school.address}</p>}
                </div>
            </div>
        </header>
    );
    const photo = showPhoto && (
        <div className="absolute end-10 top-10 h-28 w-24 overflow-hidden rounded-md border-4 border-white shadow-lg">
            {c.photo_url ? (
                <img src={c.photo_url} alt={c.name} className="h-full w-full object-cover" />
            ) : (
                <Avatar name={c.name} className="h-full w-full rounded-none text-4xl" />
            )}
        </div>
    );

    if (c.style === 'modern') {
        return (
            <article className="relative flex h-full overflow-hidden bg-white text-slate-900">
                <div className="w-14 shrink-0 bg-gradient-to-b from-fuchsia-800 via-fuchsia-600 to-violet-700" />
                <div className="relative flex flex-1 flex-col justify-between p-10">
                    {photo}
                    {heading}
                    {children}
                </div>
                <div className="absolute -end-16 -bottom-16 h-56 w-56 rounded-full bg-fuchsia-100" />
            </article>
        );
    }

    if (c.style === 'elegant') {
        return (
            <article className="relative h-full overflow-hidden bg-gradient-to-br from-amber-50 via-white to-amber-50 p-5 text-slate-900">
                <div className="relative h-full border border-amber-600/60 p-1">
                    <div className="relative flex h-full flex-col justify-between border-2 border-amber-700/70 p-9">
                        {['start-1 top-1', 'end-1 top-1 rotate-90', 'start-1 bottom-1 -rotate-90', 'end-1 bottom-1 rotate-180'].map((pos) => (
                            <span key={pos} className={`absolute h-12 w-12 border-s-4 border-t-4 border-amber-600 ${pos}`} />
                        ))}
                        {photo}
                        {heading}
                        {children}
                    </div>
                </div>
            </article>
        );
    }

    return (
        <article className="relative h-full bg-[#fdf8ec] p-6 font-serif text-slate-900">
            <div className="relative flex h-full flex-col justify-between border-[10px] border-double border-amber-700 p-9">
                {photo}
                {heading}
                {children}
            </div>
        </article>
    );
}

export default function CertificatePrint({ certificate: c, school }: { certificate: Certificate; school: School }) {
    const { t } = useI18n();
    const logo = (usePage().props as { branding?: { logo?: string } }).branding?.logo ?? '';
    const date = new Date(c.issued_on).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });

    // A4 landscape with no browser margins, only while this page is open.
    useEffect(() => {
        const style = document.createElement('style');
        style.textContent = '@page { size: A4 landscape; margin: 0 }';
        document.head.appendChild(style);

        return () => style.remove();
    }, []);

    const tone =
        c.style === 'modern'
            ? { title: 'text-fuchsia-800', line: 'bg-slate-400', seal: 'border-fuchsia-700 text-fuchsia-700' }
            : c.style === 'elegant'
              ? { title: 'text-amber-800', line: 'bg-amber-700', seal: 'border-amber-700 text-amber-800' }
              : { title: 'text-amber-900', line: 'bg-slate-500', seal: 'border-amber-700 text-amber-800' };

    return (
        <div className="min-h-screen bg-slate-200 p-4 print:bg-white print:p-0" style={{ colorScheme: 'light' }}>
            <Head title={`${c.title} · ${c.number}`} />
            <div className="mx-auto mb-3 flex max-w-[1123px] items-center justify-between print:hidden">
                <p className="text-sm text-slate-600">
                    {c.title} · {c.number}
                </p>
                <span className="flex gap-2">
                    <a href={`/app/certificates/${c.id}/pdf`} className="flex items-center gap-2 rounded-lg bg-fuchsia-700 px-4 py-2 text-sm font-semibold text-white">
                        {t('Download PDF')}
                    </a>
                    <button onClick={() => window.print()} className="flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm">
                        <Printer className="h-4 w-4" /> {t('Print')}
                    </button>
                </span>
            </div>

            <div className="mx-auto aspect-[297/210] w-full max-w-[1123px] overflow-hidden bg-white shadow-xl print:max-w-none print:shadow-none">
                <Frame c={c} school={school} logo={logo}>
                    <div className="space-y-4 text-center">
                        <h2 className={`text-4xl font-extrabold tracking-[0.2em] uppercase ${tone.title} ${c.style === 'elegant' ? 'italic' : ''}`}>
                            {c.title}
                        </h2>
                        <div className={`mx-auto h-1 w-40 rounded ${c.style === 'modern' ? 'bg-fuchsia-600' : 'bg-amber-600'}`} />
                        <Wording text={c.body} name={c.name} className="mx-auto max-w-3xl text-lg leading-9" />
                    </div>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between px-4">
                            <Seal tone={tone.seal} />
                            <div className="text-center text-[11px]">
                                <Barcode value={c.number} />
                                <div className="mt-0.5 tracking-widest opacity-70">{c.number}</div>
                            </div>
                        </div>
                        <Signatures date={date} t={t} line={tone.line} />
                    </div>
                </Frame>
            </div>
            <MadeBy className="mt-3" />
        </div>
    );
}
