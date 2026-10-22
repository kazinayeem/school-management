import { useI18n } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowRight, Bus, CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, ClipboardCheck, GraduationCap, Library, MapPin, MessageSquare, Phone, Quote, Search, ShieldCheck, Star, Users, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

type Banner = { eyebrow?: string | null; title: string; subtitle?: string | null; image: string; label?: string | null; url: string };

const brand = 'text-brand';
const eyebrow = 'text-xs font-bold tracking-[0.2em] text-brand uppercase';

function Heading({ kicker, title, note, action }: { kicker: string; title: string; note?: string; action?: { href: string; label: string } }) {
    const { t } = useI18n();

    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="max-w-2xl">
                <p className={eyebrow}>{t(kicker)}</p>
                <h2 className="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{t(title)}</h2>
                {note && <p className="mt-3 text-slate-500">{t(note)}</p>}
            </div>
            {action && (
                <Link href={action.href} className={`inline-flex items-center gap-1 text-sm font-semibold ${brand}`}>
                    {t(action.label)} <ArrowRight className="h-4 w-4" />
                </Link>
            )}
        </div>
    );
}

function Hero({ banners, open }: { banners: Banner[]; open: boolean }) {
    const { t } = useI18n();
    const [i, setI] = useState(0);
    const n = banners.length;
    useEffect(() => {
        if (n < 2) return;
        const id = setInterval(() => setI((x) => (x + 1) % n), 7000);

        return () => clearInterval(id);
    }, [n]);
    const b = banners[i];

    return (
        <section className="relative overflow-hidden bg-gradient-to-br from-fuchsia-50 via-white to-violet-50">
            <div className="pointer-events-none absolute -top-32 -end-24 h-96 w-96 rounded-full bg-brand/10 blur-3xl" />
            <div className="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 sm:py-20 lg:grid-cols-2">
                <div key={i} className="animate-in fade-in slide-in-from-bottom-2 duration-500">
                    {b.eyebrow && <p className={eyebrow}>{b.eyebrow}</p>}
                    <h1 className="mt-4 text-4xl leading-[1.1] font-extrabold tracking-tight sm:text-5xl lg:text-6xl">{b.title}</h1>
                    {b.subtitle && <p className="mt-5 max-w-xl text-lg text-slate-600">{b.subtitle}</p>}
                    <div className="mt-8 flex flex-wrap gap-3">
                        {b.label && (
                            <Link href={b.url} className="inline-flex items-center gap-2 rounded-xl bg-brand px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-fuchsia-900/20 transition hover:bg-brand-dark">
                                {b.label} <ArrowRight className="h-4 w-4" />
                            </Link>
                        )}
                        <Link href="/results" className="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold hover:border-brand">
                            {t('Check exam result')}
                        </Link>
                    </div>
                    {open && (
                        <p className="mt-6 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            <span className="h-2 w-2 animate-pulse rounded-full bg-emerald-500" /> {t('Admissions are open')}
                        </p>
                    )}
                </div>
                <div className="relative">
                    <div className="aspect-[4/3] overflow-hidden rounded-[2rem] border-4 border-white bg-white shadow-2xl shadow-fuchsia-900/15">
                        <img key={b.image} src={b.image} alt="" className="h-full w-full object-cover animate-in fade-in duration-700" />
                    </div>
                    {n > 1 && (
                        <div className="mt-4 flex items-center justify-center gap-3">
                            <button type="button" aria-label="Previous" className="rounded-full border border-slate-200 bg-white p-1.5 hover:border-brand" onClick={() => setI((i - 1 + n) % n)}>
                                <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                            </button>
                            {banners.map((_, k) => (
                                <button key={k} type="button" aria-label={`Slide ${k + 1}`} onClick={() => setI(k)} className={`h-2 rounded-full transition-all ${k === i ? 'w-8 bg-brand' : 'w-2 bg-slate-300'}`} />
                            ))}
                            <button type="button" aria-label="Next" className="rounded-full border border-slate-200 bg-white p-1.5 hover:border-brand" onClick={() => setI((i + 1) % n)}>
                                <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}

function Faq({ question, answer }: { question: string; answer: string }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-2xl border border-slate-200 bg-white">
            <button type="button" aria-expanded={open} className="flex w-full items-center justify-between gap-4 px-5 py-4 text-start font-semibold" onClick={() => setOpen(!open)}>
                {question}
                <span className={`text-xl leading-none ${brand}`}>{open ? '–' : '+'}</span>
            </button>
            {open && <p className="px-5 pb-5 text-sm leading-relaxed text-slate-600">{answer}</p>}
        </div>
    );
}

export function PublicHome({ page }: { page: Record<string, any> }) {
    const { t } = useI18n();
    const site = (page.site ?? {}) as Record<string, string>;
    const levels = [
        { name: 'Primary', range: 'Class 1 – 5', note: 'Reading, number sense and curiosity, in small sections.', from: 1, to: 5 },
        { name: 'Junior', range: 'Class 6 – 8', note: 'Science labs, computer lessons and first debates.', from: 6, to: 8 },
        { name: 'Secondary', range: 'Class 9 – 10', note: 'Focused SSC preparation with practical work.', from: 9, to: 10 },
    ];
    const fees = (page.fees ?? []) as { class: string; tuition: number; admission: number }[];
    const lowest = (from: number, to: number) => {
        const list = fees.filter((f) => Number(f.class.slice(6)) >= from && Number(f.class.slice(6)) <= to).map((f) => f.tuition);

        return list.length ? Math.min(...list) : null;
    };

    return (
        <>
            <Hero banners={page.banners} open={page.admissionOpen !== false} />

            <section className="relative z-10 mx-auto -mt-8 grid max-w-6xl gap-4 px-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    { icon: ClipboardCheck, title: 'Online Admission', note: 'Apply from home', href: '/admission' },
                    { icon: Search, title: 'Check Exam Result', note: 'By roll number', href: '/results' },
                    { icon: CalendarDays, title: 'Notice Board', note: 'Official announcements', href: '/notices' },
                    { icon: Users, title: 'Student & Parent Login', note: 'Attendance, fees, homework', href: '/login?role=parent' },
                ].map((c) => (
                    <Link key={c.title} href={c.href} className="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg">
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-fuchsia-50 text-brand">
                            <c.icon className="h-6 w-6" />
                        </span>
                        <span>
                            <span className="block font-bold">{t(c.title)}</span>
                            <span className="text-sm text-slate-500">{t(c.note)}</span>
                        </span>
                    </Link>
                ))}
            </section>

            {page.stats?.length > 0 && (
                <section className="mx-auto mt-14 grid max-w-6xl grid-cols-2 gap-4 px-4 md:grid-cols-4">
                    {page.stats.map((s: { value: string; label: string }) => (
                        <div key={s.label} className="rounded-2xl bg-white p-6 text-center shadow-sm">
                            <p className={`text-4xl font-extrabold ${brand}`}>{s.value}</p>
                            <p className="mt-1 text-sm text-slate-500">{t(s.label)}</p>
                        </div>
                    ))}
                </section>
            )}

            <section className="mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 lg:grid-cols-2">
                <div className="relative">
                    <div className="overflow-hidden rounded-[2rem] shadow-xl">
                        <img src="/images/site/about-school.svg" alt="" className="aspect-[4/3] w-full object-cover" loading="lazy" />
                    </div>
                    <div className="absolute -bottom-5 end-4 rounded-2xl bg-white px-5 py-4 shadow-xl">
                        <p className={`text-3xl font-extrabold ${brand}`}>Class 1–10</p>
                        <p className="text-xs text-slate-500">{t('National curriculum')}</p>
                    </div>
                </div>
                <div>
                    <p className={eyebrow}>{t('Know about us')}</p>
                    <h2 className="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{t('A school the neighbourhood grew up with')}</h2>
                    <p className="mt-4 text-slate-600">{t('We teach from Class 1 to Class 10 under the national curriculum. Class sections are kept small so that every child is known by name rather than by roll number.')}</p>
                    <ul className="mt-6 space-y-3">
                        {['Recognised by the education board', 'Sport, music and debate, not only lessons', 'Parents can follow the school day online'].map((item) => (
                            <li key={item} className="flex items-start gap-3">
                                <CheckCircle2 className={`mt-0.5 h-5 w-5 shrink-0 ${brand}`} /> {t(item)}
                            </li>
                        ))}
                    </ul>
                    <Link href="/about" className={`mt-7 inline-flex items-center gap-1 text-sm font-semibold ${brand}`}>
                        {t('Read more about us')} <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </section>

            <section className="bg-white py-20">
                <div className="mx-auto max-w-6xl px-4">
                    <Heading kicker="Academics" title="From first steps to SSC" note="Three stages, one steady approach." />
                    <div className="mt-10 grid gap-5 md:grid-cols-3">
                        {levels.map((l) => {
                            const from = lowest(l.from, l.to);

                            return (
                                <article key={l.name} className="rounded-3xl border border-slate-200 bg-slate-50 p-7">
                                    <GraduationCap className={`h-8 w-8 ${brand}`} />
                                    <h3 className="mt-4 text-xl font-bold">{t(l.name)}</h3>
                                    <p className={`text-sm font-semibold ${brand}`}>{t(l.range)}</p>
                                    <p className="mt-3 text-sm text-slate-600">{t(l.note)}</p>
                                    {from && <p className="mt-5 text-sm text-slate-500">{t('Tuition from')} <strong className="text-slate-900">Tk {from.toLocaleString()}</strong> {t('a month')}</p>}
                                </article>
                            );
                        })}
                    </div>
                </div>
            </section>

            {fees.length > 0 && (
                <section className="mx-auto max-w-6xl px-4 py-20">
                    <Heading kicker="Fees" title="Clear fees, class by class" note="Tuition is charged every month. Labs and activities are charged only if your child joins them, and discounts show on every invoice." />
                    <div className="mt-10 overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <table className="w-full min-w-[520px] text-sm">
                            <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                <tr>
                                    <th className="px-5 py-4 text-start">{t('Class')}</th>
                                    <th className="px-5 py-4 text-end">{t('Monthly tuition')}</th>
                                    <th className="px-5 py-4 text-end">{t('Admission fee (once)')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {fees.map((f) => (
                                    <tr key={f.class} className="hover:bg-fuchsia-50/40">
                                        <td className="px-5 py-3 font-semibold">{t(f.class)}</td>
                                        <td className="px-5 py-3 text-end tabular-nums">Tk {f.tuition.toLocaleString()}</td>
                                        <td className="px-5 py-3 text-end tabular-nums">Tk {f.admission.toLocaleString()}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="mt-4 flex items-start gap-2 text-sm text-slate-500">
                        <Wallet className={`mt-0.5 h-4 w-4 shrink-0 ${brand}`} />
                        {t('Merit scholarships, sibling discounts and help for families in need are available. Ask the office.')}
                    </p>
                </section>
            )}

            <section className="bg-white py-20">
                <div className="mx-auto max-w-6xl px-4">
                    <Heading kicker="Why families choose us" title="What this school offers" note="The things parents ask about most, and what we do about each of them." />
                    <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            { icon: Library, title: 'Library', note: 'Open all day, and every student can borrow.' },
                            { icon: Bus, title: 'Transport', note: 'Routes across the city, each with a driver you can call.' },
                            { icon: Users, title: 'Our teachers', note: 'Small sections, so every child is known by name.' },
                            { icon: ShieldCheck, title: 'Safety', note: 'An SMS reaches you the same day if your child is absent.' },
                        ].map((item) => (
                            <article key={item.title} className="rounded-2xl border border-slate-200 p-6 transition hover:border-brand hover:shadow-md">
                                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-fuchsia-50 text-brand">
                                    <item.icon className="h-5 w-5" />
                                </span>
                                <h3 className="mt-4 font-bold">{t(item.title)}</h3>
                                <p className="mt-1 text-sm text-slate-500">{t(item.note)}</p>
                            </article>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-20">
                <div className="grid items-center gap-10 rounded-[2rem] bg-gradient-to-br from-brand to-slate-900 p-8 text-white sm:p-12 lg:grid-cols-[1fr_auto]">
                    <div>
                        <Quote className="h-8 w-8 opacity-60" />
                        <p className="mt-4 text-xl leading-relaxed sm:text-2xl">"{t('Our mission is to foster an environment where curiosity is celebrated and challenges are viewed as opportunities for growth. We do not just teach subjects; we build character.')}"</p>
                        <p className="mt-6 font-bold">{t("Principal's message")}</p>
                        <Link href="/principal-message" className="mt-1 inline-flex items-center gap-1 text-sm text-fuchsia-100 hover:underline">
                            {t('Read the full message')} <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                    <GraduationCap className="hidden h-40 w-40 text-white/20 lg:block" />
                </div>
            </section>

            <section className="bg-white py-20">
                <div className="mx-auto max-w-6xl px-4">
                    <Heading kicker="Our mentors" title="Meet our dedicated faculty" note="Small sections, so every child is taught by someone who knows their name." action={{ href: '/teachers', label: 'See all teachers' }} />
                    <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {page.teachers.map((teacher: { name: string; subject: string; role: string; photo?: string | null }) => (
                            <article key={teacher.name} className="rounded-2xl border border-slate-200 p-6 text-center transition hover:shadow-md">
                                {teacher.photo ? (
                                    <img src={teacher.photo} alt={teacher.name} className="mx-auto h-24 w-24 rounded-full object-cover ring-4 ring-fuchsia-50" loading="lazy" />
                                ) : (
                                    <span className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-fuchsia-100 text-3xl font-bold text-brand">{teacher.name.charAt(0)}</span>
                                )}
                                <h3 className="mt-4 font-bold">{teacher.name}</h3>
                                <p className={`text-xs font-semibold tracking-wide ${brand}`}>{teacher.subject}</p>
                                <p className="text-sm text-slate-500">{teacher.role}</p>
                            </article>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-20">
                <div className="grid gap-10 lg:grid-cols-2">
                    <div>
                        <Heading kicker="Stay updated" title="Important notices" action={{ href: '/notices', label: 'View all' }} />
                        <div className="mt-8 space-y-3">
                            {page.notices.map((notice: { id: number; day: string; month: string; title: string; body: string }) => (
                                <Link key={notice.id} href={`/notices/${notice.id}`} className="flex gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-brand">
                                    <span className="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-fuchsia-50 text-center">
                                        <span className={`text-2xl leading-none font-extrabold ${brand}`}>{notice.day}</span>
                                        <span className="text-[10px] tracking-wide uppercase">{notice.month}</span>
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block font-bold">{notice.title}</span>
                                        <span className="mt-1 line-clamp-2 block text-sm text-slate-500">{notice.body}</span>
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                    <div>
                        <Heading kicker="Campus life" title="Upcoming events" action={{ href: '/events', label: 'View all' }} />
                        <div className="mt-8 space-y-3">
                            {page.events.map((event: { slug: string; href?: string; day: string; month: string; title: string; place: string }) => (
                                <Link key={event.slug} href={event.href ?? `/events/${event.slug}`} className="flex gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-brand">
                                    <span className="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-brand text-center text-white">
                                        <span className="text-2xl leading-none font-extrabold">{event.day}</span>
                                        <span className="text-[10px] tracking-wide uppercase">{event.month}</span>
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block font-bold">{event.title}</span>
                                        <span className="mt-1 flex items-center gap-1 text-sm text-slate-500">
                                            <MapPin className="h-3.5 w-3.5" /> {event.place}
                                        </span>
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {page.gallery?.length > 0 && (
                <section className="bg-white py-20">
                    <div className="mx-auto max-w-6xl px-4">
                        <Heading kicker="Gallery" title="Life at our campus" note="Sports days, science fairs and the ordinary mornings in between." action={{ href: '/gallery', label: 'See all albums' }} />
                        <div className="mt-10 grid grid-cols-2 gap-4 md:grid-cols-4">
                            {page.gallery.map((g: { id: number; title: string; album: string | null; image: string }, k: number) => (
                                <Link key={g.id} href="/gallery" className={`group relative overflow-hidden rounded-2xl ${k === 0 || k === 5 ? 'md:col-span-2 md:row-span-2' : ''}`}>
                                    <img src={g.image} alt={g.title} loading="lazy" className="h-full min-h-40 w-full object-cover transition duration-500 group-hover:scale-105" />
                                    <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-4 text-sm font-semibold text-white">{g.title}</span>
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {page.testimonials?.length > 0 && (
                <section className="mx-auto max-w-6xl px-4 py-20">
                    <Heading kicker="Community feedback" title="What parents & alumni say" />
                    <div className="mt-10 grid gap-5 lg:grid-cols-3">
                        {page.testimonials.slice(0, 3).map((q: { name: string; role: string; quote: string; rating: number }) => (
                            <blockquote key={q.name} className="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm">
                                <div className="flex gap-0.5 text-amber-400">
                                    {Array.from({ length: q.rating }).map((_, k) => (
                                        <Star key={k} className="h-4 w-4 fill-current" />
                                    ))}
                                </div>
                                <p className="mt-4 text-sm leading-relaxed">"{q.quote}"</p>
                                <footer className="mt-5">
                                    <p className="font-bold">{q.name}</p>
                                    <p className="text-xs text-slate-500">{q.role}</p>
                                </footer>
                            </blockquote>
                        ))}
                    </div>
                </section>
            )}

            <section className="mx-auto max-w-6xl px-4">
                <div className="flex flex-wrap items-center justify-between gap-6 rounded-[2rem] bg-brand p-8 text-white sm:p-12">
                    <div>
                        <h2 className="text-3xl font-extrabold">{t('Admissions are open')}</h2>
                        <p className="mt-2 text-fuchsia-100">{t('Apply online in a few minutes. No account needed — you get a tracking number.')}</p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Link href="/admission" className="rounded-xl bg-white px-6 py-3 text-sm font-semibold text-brand">{t('Apply now')}</Link>
                        <Link href="/admission#track" className="rounded-xl border border-white/40 px-6 py-3 text-sm font-semibold">{t('Track application')}</Link>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-3xl px-4 py-20">
                <Heading kicker="Common queries" title="Frequently Asked Questions" />
                <div className="mt-8 space-y-3">
                    {page.faqs.map((item: { q: string; a: string }) => (
                        <Faq key={item.q} question={item.q} answer={item.a} />
                    ))}
                </div>
                <p className="mt-6 text-sm text-slate-500">
                    {t("Can't find the answer you're looking for?")} <Link href="/contact" className={`font-semibold ${brand}`}>{t('Reach out to the school office')}</Link>
                </p>
            </section>

            <section className="bg-white py-20">
                <div className="mx-auto max-w-6xl px-4">
                    <Heading kicker="Contact" title="Get in touch" note="The office answers on working days between school hours." />
                    <div className="mt-10 grid gap-5 md:grid-cols-3">
                        {[
                            { icon: MapPin, title: 'Visit us', value: site.address ?? 'House 12, Road 5, Dhanmondi, Dhaka 1205' },
                            { icon: Phone, title: 'Call us', value: site.phone ?? '+880 1711-000000' },
                            { icon: MessageSquare, title: 'Write to us', value: site.email ?? 'office@borno.edu.bd' },
                        ].map((c) => (
                            <article key={c.title} className="flex items-start gap-4 rounded-2xl border border-slate-200 p-6">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-fuchsia-50 text-brand">
                                    <c.icon className="h-5 w-5" />
                                </span>
                                <span>
                                    <span className="block font-bold">{t(c.title)}</span>
                                    <span className="mt-1 block text-sm text-slate-600">{c.value}</span>
                                </span>
                            </article>
                        ))}
                    </div>
                    <Link href="/contact" className={`mt-8 inline-flex items-center gap-1 text-sm font-semibold ${brand}`}>
                        {t('Send us a message')} <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </section>
        </>
    );
}
