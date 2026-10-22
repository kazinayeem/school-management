import { Link } from '@inertiajs/react';
import { Bus, CheckCircle2, Library, Quote, ShieldCheck, Users } from 'lucide-react';
import { useState } from 'react';

export function PublicHome({ page }: { page: Record<string, any> }) {
    return (
        <>
            <section className="relative overflow-hidden bg-slate-950 text-white">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(179,18,153,0.55),transparent_42%),linear-gradient(160deg,#1a1024,#3b0a3a_55%,#120814)]" />
                <div className="relative mx-auto max-w-6xl px-4 py-20 sm:py-28">
                    <p className="text-xs font-bold tracking-[0.2em] text-fuchsia-200">ADMISSIONS OPEN</p>
                    <h1 className="mt-4 max-w-3xl text-4xl font-extrabold tracking-tight sm:text-5xl">Nurturing minds, building future leaders</h1>
                    <p className="mt-4 max-w-xl text-lg text-fuchsia-100/90">A holistic learning environment where academic excellence meets character development.</p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link href="/admission" className="rounded-[10px] bg-[#b31299] px-5 py-3 text-sm font-semibold text-white">
                            Apply for admission
                        </Link>
                        <Link href="/results" className="rounded-[10px] border border-white/30 px-5 py-3 text-sm font-semibold">
                            Check exam result
                        </Link>
                    </div>
                </div>
            </section>

            <section className="mx-auto -mt-8 grid max-w-6xl gap-4 px-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    ['Online Admission', 'Apply from home', '/admission'],
                    ['Check Exam Result', 'By roll number', '/results'],
                    ['Notice Board', 'Official announcements', '/notices'],
                    ['Parent Login', 'Attendance, results, dues', '/login'],
                ].map(([title, note, href]) => (
                    <Link key={title} href={href} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-[#b31299]">
                        <h3 className="font-bold">{title}</h3>
                        <p className="mt-1 text-sm text-slate-500">{note}</p>
                    </Link>
                ))}
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16">
                <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">WHY FAMILIES CHOOSE US</p>
                <h2 className="mt-2 text-3xl font-extrabold">What this school offers</h2>
                <p className="mt-2 text-slate-500">The things parents ask about most, and what we do about each of them.</p>
                <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                    { icon: Library, title: 'Library', note: 'Open all day, and every student can borrow.' },
                    { icon: Bus, title: 'Transport', note: 'Routes across the city, each with a driver you can call.' },
                    { icon: Users, title: 'Our teachers', note: 'Small sections, so every child is known by name.' },
                    { icon: ShieldCheck, title: 'Safety', note: 'An SMS reaches you the same day if your child is absent.' },
                ].map((item) => (
                    <article key={item.title} className="rounded-2xl bg-white p-5 shadow-sm">
                        <item.icon className="h-6 w-6 text-[#b31299]" />
                        <h4 className="mt-3 font-bold">{item.title}</h4>
                        <p className="mt-1 text-sm text-slate-500">{item.note}</p>
                    </article>
                ))}
                </div>
            </section>

            <section className="bg-white py-16">
                <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 lg:grid-cols-2">
                    <div className="rounded-3xl bg-gradient-to-br from-[#b31299] to-slate-900 p-10 text-white">
                        <p className="text-6xl font-extrabold">41</p>
                        <p className="mt-2 text-sm tracking-widest">YEARS OF TEACHING</p>
                    </div>
                    <div>
                        <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">KNOW ABOUT US</p>
                        <h2 className="mt-2 text-3xl font-extrabold">A school the neighbourhood grew up with</h2>
                        <p className="mt-3 text-slate-600">We teach from Class 1 to Class 10 under the national curriculum. Class sections are kept small so that every child is known by name rather than by roll number.</p>
                        <ul className="mt-4 space-y-2 text-sm">
                            {['Recognised by the education board', 'Sport, music and debate, not only lessons', 'Parents can follow the school day online'].map((item) => (
                                <li key={item} className="flex items-center gap-2">
                                    <CheckCircle2 className="h-4 w-4 text-[#b31299]" /> {item}
                                </li>
                            ))}
                        </ul>
                        <Link href="/about" className="mt-5 inline-block text-sm font-semibold text-[#b31299]">
                            Read more about us
                        </Link>
                    </div>
                </div>
                <div className="mx-auto mt-10 grid max-w-6xl grid-cols-2 gap-4 px-4 md:grid-cols-4">
                    {[
                        ['1,200+', 'STUDENTS'],
                        ['60+', 'TEACHERS'],
                        ['40+', 'YEARS OF TEACHING'],
                        ['98%', 'SSC PASS RATE'],
                    ].map(([value, label]) => (
                        <div key={label} className="rounded-2xl bg-slate-50 p-5 text-center">
                            <p className="text-3xl font-extrabold text-[#b31299]">{value}</p>
                            <p className="mt-1 text-xs tracking-widest text-slate-500">{label}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16">
                <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">FROM THE PRINCIPAL'S DESK</p>
                <h2 className="mt-2 max-w-2xl text-3xl font-extrabold">Empowering students to lead with purpose and integrity</h2>
                <blockquote className="mt-6 rounded-3xl bg-white p-8 shadow-sm">
                    <Quote className="h-6 w-6 text-[#b31299]" />
                    <p className="mt-4 text-lg leading-relaxed text-slate-700">"Our mission is to foster an environment where curiosity is celebrated and challenges are viewed as opportunities for growth. We do not just teach subjects; we build character. Every child who walks through our doors is a unique talent waiting to be nurtured, and we take that responsibility with the utmost care."</p>
                    <footer className="mt-4">
                        <p className="font-bold">Dr. Ananya Sharma</p>
                        <p className="text-sm text-slate-500">Principal, ReadySchool</p>
                    </footer>
                </blockquote>
            </section>

            <section className="bg-white py-16">
                <div className="mx-auto max-w-6xl px-4">
                    <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">ONLINE SERVICES</p>
                    <h2 className="mt-2 text-3xl font-extrabold">What you can do online</h2>
                    <p className="mt-2 max-w-2xl text-slate-500">The office counter, open all day. Most things a family needs no longer require a trip to school.</p>
                    <div className="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {[
                            ['Apply for admission', 'Submit the form from home and track it with your tracking number.', '/admission'],
                            ['Check exam results', 'Enter a roll number to see published results the day they release.', '/results'],
                            ['Pay fees online', 'Settle dues from the parent portal and keep the receipt.', '/login'],
                            ['Parent portal', 'Attendance, results, dues and homework for each of your children.', '/login'],
                            ['Homework', 'See what was set, when it is due, and submit online where allowed.', '/login'],
                            ['Library account', 'Check what your child has borrowed and when it must come back.', '/login'],
                        ].map(([title, note, href]) => (
                            <Link key={title} href={href} className="rounded-2xl border border-slate-200 p-5 hover:border-[#b31299]">
                                <h4 className="font-bold">{title}</h4>
                                <p className="mt-2 text-sm text-slate-500">{note}</p>
                            </Link>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16">
                <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">OUR MENTORS</p>
                <h2 className="mt-2 text-3xl font-extrabold">Meet our dedicated faculty</h2>
                <p className="mt-2 text-slate-500">Small sections, so every child is taught by someone who knows their name.</p>
                <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {page.teachers.map((teacher: { name: string; subject: string; role: string; photo?: string | null }) => (
                        <article key={teacher.name} className="rounded-2xl bg-white p-5 shadow-sm">
                            {teacher.photo ? (
                                <img src={teacher.photo} alt={teacher.name} className="h-14 w-14 rounded-full object-cover" />
                            ) : (
                                <span className="flex h-12 w-12 items-center justify-center rounded-full bg-fuchsia-100 font-bold text-[#b31299]">{teacher.name.charAt(0)}</span>
                            )}
                            <p className="mt-3 text-xs font-semibold tracking-wide text-[#b31299]">{teacher.subject}</p>
                            <h5 className="font-bold">{teacher.name}</h5>
                            <p className="text-sm text-slate-500">{teacher.role}</p>
                        </article>
                    ))}
                </div>
                <Link href="/teachers" className="mt-6 inline-block text-sm font-semibold text-[#b31299]">
                    See all teachers
                </Link>
            </section>

            <section className="bg-white py-16">
                <div className="mx-auto grid max-w-6xl gap-8 px-4 lg:grid-cols-2">
                    <div>
                        <div className="flex items-end justify-between">
                            <div>
                                <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">STAY UPDATED</p>
                                <h2 className="mt-2 text-3xl font-extrabold">Important notices</h2>
                            </div>
                            <Link href="/notices" className="text-sm font-semibold text-[#b31299]">View all</Link>
                        </div>
                        <div className="mt-6 space-y-3">
                            {page.notices.map((notice: { id: number; day: string; month: string; title: string; body: string }) => (
                                <Link key={notice.id} href={`/notices/${notice.id}`} className="flex gap-4 rounded-2xl border border-slate-200 p-4">
                                    <span className="text-center">
                                        <span className="block text-2xl font-extrabold text-[#b31299]">{notice.day}</span>
                                        <span className="text-xs uppercase">{notice.month}</span>
                                    </span>
                                    <span>
                                        <span className="block font-bold">{notice.title}</span>
                                        <span className="mt-1 line-clamp-2 block text-sm text-slate-500">{notice.body}</span>
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                    <div>
                        <div className="flex items-end justify-between">
                            <div>
                                <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">CAMPUS LIFE</p>
                                <h2 className="mt-2 text-3xl font-extrabold">Upcoming events</h2>
                            </div>
                            <Link href="/events" className="text-sm font-semibold text-[#b31299]">View all</Link>
                        </div>
                        <div className="mt-6 space-y-3">
                            {page.events.map((event: { slug: string; day: string; month: string; title: string; place: string }) => (
                                <Link key={event.slug} href={`/events/${event.slug}`} className="block rounded-2xl border border-slate-200 p-4">
                                    <p className="text-sm text-slate-500">{event.day} {event.month}</p>
                                    <h4 className="font-bold">{event.title}</h4>
                                    <p className="text-sm text-slate-500">{event.place}</p>
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16">
                <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">GALLERY</p>
                <h2 className="mt-2 text-3xl font-extrabold">Life at our campus</h2>
                <p className="mt-2 text-slate-500">Sports days, science fairs and the ordinary mornings in between.</p>
                <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {page.albums.map((album: { title: string; tone: string }) => (
                        <div key={album.title} className={`flex h-40 items-end rounded-2xl bg-gradient-to-br ${album.tone} p-4 text-white`}>
                            <p className="font-bold">{album.title}</p>
                        </div>
                    ))}
                </div>
                <Link href="/gallery" className="mt-6 inline-block text-sm font-semibold text-[#b31299]">See all albums</Link>
            </section>

            <section className="bg-white py-16">
                <div className="mx-auto max-w-6xl px-4">
                    <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">COMMUNITY FEEDBACK</p>
                    <h2 className="mt-2 text-3xl font-extrabold">What parents & alumni say</h2>
                    <p className="mt-2 text-slate-500">Unedited words from families who have been with us for years.</p>
                    <div className="mt-8 grid gap-4 lg:grid-cols-3">
                        {[
                            ['Sending our son here was the best decision we made. The individual attention he receives is remarkable, and his confidence has grown in just two years.', 'Mahmudul Hasan', 'Parent of a Class 8 student'],
                            ['The values of discipline and integrity I learned at this school have been the cornerstone of my professional life. Proud to be an alumnus.', 'Farzana Rahman', 'Alumna, batch of 2015'],
                            ["The teachers here don't just teach from textbooks; they inspire students to think critically and creatively. The facilities are excellent.", 'Rokeya Begum', 'Parent of a Class 10 student'],
                        ].map(([quote, name, who]) => (
                            <blockquote key={name} className="rounded-2xl border border-slate-200 p-5">
                                <p className="text-amber-500">★★★★★</p>
                                <p className="mt-3 text-sm leading-relaxed">"{quote}"</p>
                                <footer className="mt-4">
                                    <p className="font-bold">{name}</p>
                                    <p className="text-xs text-slate-500">{who}</p>
                                </footer>
                            </blockquote>
                        ))}
                    </div>
                </div>
            </section>

            <section className="bg-[#b31299] py-16 text-white">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-4">
                    <div>
                        <h2 className="text-3xl font-extrabold">Admissions are open</h2>
                        <p className="mt-2 text-fuchsia-100">Apply online in a few minutes. No account needed — you get a tracking number.</p>
                    </div>
                    <div className="flex gap-3">
                        <Link href="/admission" className="rounded-[10px] bg-white px-4 py-2 text-sm font-semibold text-[#b31299]">Apply now</Link>
                        <Link href="/admission#track" className="rounded-[10px] border border-white/40 px-4 py-2 text-sm font-semibold">Track application</Link>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-3xl px-4 py-16">
                <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">COMMON QUERIES</p>
                <h2 className="mt-2 text-3xl font-extrabold">Frequently Asked Questions</h2>
                <p className="mt-2 text-slate-500">Can't find the answer you're looking for? <Link href="/contact" className="font-semibold text-[#b31299]">Reach out to the school office</Link> for personalised assistance.</p>
                <div className="mt-6 space-y-3">
                    {page.faqs.map((item: { q: string; a: string }) => (
                        <Faq key={item.q} question={item.q} answer={item.a} />
                    ))}
                </div>
            </section>

            <section className="bg-white py-16">
                <div className="mx-auto max-w-6xl px-4">
                    <p className="text-xs font-bold tracking-[0.2em] text-[#b31299]">CONTACT</p>
                    <h2 className="mt-2 text-3xl font-extrabold">Get in touch</h2>
                    <p className="mt-2 text-slate-500">The office answers on working days between school hours.</p>
                    <div className="mt-8 grid gap-4 md:grid-cols-3">
                        {[
                            ['Visit us', 'House 12, Road 5, Dhanmondi, Dhaka 1205'],
                            ['Call us', '+880 1711-000000'],
                            ['Write to us', 'office@demoschool.edu.bd'],
                        ].map(([title, value]) => (
                            <article key={title} className="rounded-2xl border border-slate-200 p-5">
                                <h4 className="font-bold">{title}</h4>
                                <p className="mt-2 text-sm text-slate-600">{value}</p>
                            </article>
                        ))}
                    </div>
                    <Link href="/contact" className="mt-6 inline-block text-sm font-semibold text-[#b31299]">Send us a message</Link>
                </div>
            </section>
        </>
    );
}

function Faq({ question, answer }: { question: string; answer: string }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-2xl border border-slate-200 bg-white">
            <button type="button" className="flex w-full items-center justify-between px-4 py-3 text-left font-semibold" onClick={() => setOpen(!open)}>
                {question}
                <span className="text-[#b31299]">{open ? '–' : '+'}</span>
            </button>
            {open && <p className="px-4 pb-4 text-sm text-slate-600">{answer}</p>}
        </div>
    );
}
