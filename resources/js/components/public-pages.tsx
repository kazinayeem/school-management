import { Link, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';

const field = 'mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm';

export function PublicPages({ page }: { page: Record<string, any> }) {
    if (page.kind === 'article') {
        return (
            <Article title={page.title} body={page.body} points={page.points} />
        );
    }
    if (page.kind === 'teachers') {
        return (
            <Article title="Teachers" body="Small sections, so every child is taught by someone who knows their name.">
                <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {page.teachers.map((teacher: { name: string; subject: string; role: string }) => (
                        <article key={teacher.name} className="rounded-2xl border border-slate-200 bg-white p-5">
                            <p className="text-xs font-semibold text-[#b31299]">{teacher.subject}</p>
                            <h3 className="font-bold">{teacher.name}</h3>
                            <p className="text-sm text-slate-500">{teacher.role}</p>
                        </article>
                    ))}
                </div>
            </Article>
        );
    }
    if (page.kind === 'gallery') {
        return (
            <Article title="Gallery" body="Sports days, science fairs and the ordinary mornings in between.">
                <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {page.albums.map((album: { title: string; tone: string }) => (
                        <div key={album.title} className={`flex h-52 items-end rounded-2xl bg-gradient-to-br ${album.tone} p-5 font-bold text-white`}>
                            {album.title}
                        </div>
                    ))}
                </div>
            </Article>
        );
    }
    if (page.kind === 'notices') {
        return (
            <Article title="Notices" body="Official announcements from the school office.">
                <div className="mt-8 space-y-3">
                    {page.notices.map((notice: { id: number; day: string; month: string; title: string; body: string }) => (
                        <Link key={notice.id} href={`/notices/${notice.id}`} className="block rounded-2xl border border-slate-200 bg-white p-5">
                            <p className="text-xs font-bold text-[#b31299]">{notice.day} {notice.month}</p>
                            <h3 className="mt-1 font-bold">{notice.title}</h3>
                            <p className="mt-2 line-clamp-2 text-sm text-slate-600">{notice.body}</p>
                        </Link>
                    ))}
                </div>
            </Article>
        );
    }
    if (page.kind === 'notice') {
        return <Article title={page.notice.title} body={page.notice.body} meta={`Published ${page.notice.published}`} />;
    }
    if (page.kind === 'events') {
        return (
            <Article title="Events" body="What is happening on campus this month.">
                <div className="mt-8 space-y-3">
                    {page.events.map((event: { slug: string; date: string; title: string; place: string }) => (
                        <Link key={event.slug} href={`/events/${event.slug}`} className="block rounded-2xl border border-slate-200 bg-white p-5">
                            <p className="text-xs font-bold text-[#b31299]">{event.date}</p>
                            <h3 className="font-bold">{event.title}</h3>
                            <p className="text-sm text-slate-500">{event.place}</p>
                        </Link>
                    ))}
                </div>
            </Article>
        );
    }
    if (page.kind === 'event') {
        return <Article title={page.event.title} body={page.event.body} meta={`${page.event.date} · ${page.event.place}`} />;
    }
    if (page.kind === 'contact') {
        return <Contact sent={page.sent} />;
    }
    if (page.kind === 'admission') {
        return <Admission tracking={page.tracking} status={page.status} />;
    }
    if (page.kind === 'results') {
        return <Results page={page} />;
    }

    return null;
}

function Article({ title, body, points, meta, children }: { title: string; body: string; points?: string[]; meta?: string; children?: ReactNode }) {
    return (
        <div className="mx-auto max-w-6xl px-4 py-12">
            <p className="text-sm text-slate-400">Home / {title}</p>
            <h1 className="mt-2 text-4xl font-extrabold">{title}</h1>
            {meta && <p className="mt-2 text-sm text-[#b31299]">{meta}</p>}
            <p className="mt-4 max-w-3xl text-lg text-slate-600">{body}</p>
            {points && (
                <ul className="mt-6 space-y-2">
                    {points.map((point) => (
                        <li key={point} className="rounded-xl bg-white px-4 py-3 text-sm shadow-sm">{point}</li>
                    ))}
                </ul>
            )}
            {children}
        </div>
    );
}

function Contact({ sent }: { sent: boolean }) {
    const form = useForm({ name: '', email: '', phone: '', message: '' });

    return (
        <Article title="Contact" body="The office answers on working days between school hours.">
            <div className="mt-8 grid gap-8 lg:grid-cols-2">
                <div className="space-y-3 text-sm">
                    <p className="rounded-2xl bg-white p-4 shadow-sm">House 12, Road 5, Dhanmondi, Dhaka 1205</p>
                    <p className="rounded-2xl bg-white p-4 shadow-sm"><a href="tel:+8801711000000">+880 1711-000000</a></p>
                    <p className="rounded-2xl bg-white p-4 shadow-sm"><a href="mailto:office@demoschool.edu.bd">office@demoschool.edu.bd</a></p>
                </div>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('contact.store'));
                    }}
                    className="space-y-3 rounded-2xl bg-white p-5 shadow-sm"
                >
                    {sent && <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Your message has been sent to the school office.</p>}
                    <label className="block text-sm">Name<input aria-label="Name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={field} /></label>
                    <label className="block text-sm">Email<input aria-label="Email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className={field} /></label>
                    <label className="block text-sm">Phone<input aria-label="Phone" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} className={field} /></label>
                    <label className="block text-sm">Message<textarea aria-label="Message" value={form.data.message} onChange={(event) => form.setData('message', event.target.value)} className={field} rows={4} /></label>
                    <button disabled={form.processing} className="rounded-[10px] bg-[#b31299] px-4 py-2 text-sm font-semibold text-white">Send message</button>
                </form>
            </div>
        </Article>
    );
}

function Admission({ tracking, status }: { tracking?: string; status?: { found: boolean; tracking?: string; name?: string; class?: string; state?: string } }) {
    const apply = useForm({
        class_name: 'Class 8',
        first_name: '',
        last_name: '',
        date_of_birth: '',
        gender: 'male',
        religion: 'islam',
        blood_group: '',
        previous_school: '',
        address: '',
        guardian_name: '',
        relation: 'father',
        mobile: '',
        email: '',
        mother_name: '',
        occupation: '',
    });
    const track = useForm({ tracking_no: '', mobile: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply.post(route('admission.store'));
    };

    return (
        <div className="mx-auto max-w-3xl px-4 py-12">
            <h1 className="text-4xl font-extrabold">Online admission</h1>
            <p className="mt-2 text-slate-600">No account is needed. You will receive a tracking number.</p>
            {tracking && (
                <div className="mt-6 rounded-2xl bg-emerald-50 p-5 text-emerald-900">
                    <p className="font-bold">Your application has been received.</p>
                    <p className="mt-1 text-sm">Keep this number and the mobile you entered — you need both to check your status.</p>
                    <p className="mt-3 text-2xl font-extrabold">{tracking}</p>
                </div>
            )}
            <form onSubmit={submit} className="mt-8 space-y-8 rounded-3xl bg-white p-6 shadow-sm">
                <section>
                    <h2 className="font-bold">Applying for</h2>
                    <label className="mt-3 block text-sm">Class *
                        <select aria-label="Select class" value={apply.data.class_name} onChange={(event) => apply.setData('class_name', event.target.value)} className={field}>
                            {['Class 5', 'Class 6', 'Class 7', 'Class 8'].map((name) => <option key={name}>{name}</option>)}
                        </select>
                    </label>
                </section>
                <section className="grid gap-3 sm:grid-cols-2">
                    <h2 className="font-bold sm:col-span-2">Student details</h2>
                    <label className="text-sm">First name *<input aria-label="First name" value={apply.data.first_name} onChange={(event) => apply.setData('first_name', event.target.value)} className={field} /></label>
                    <label className="text-sm">Last name<input aria-label="Last name" value={apply.data.last_name} onChange={(event) => apply.setData('last_name', event.target.value)} className={field} /></label>
                    <label className="text-sm">Date of birth *<input aria-label="Date of birth" type="date" value={apply.data.date_of_birth} onChange={(event) => apply.setData('date_of_birth', event.target.value)} className={field} /></label>
                    <label className="text-sm">Gender *
                        <select aria-label="Gender" value={apply.data.gender} onChange={(event) => apply.setData('gender', event.target.value)} className={field}>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label className="text-sm">Religion *
                        <select aria-label="Religion" value={apply.data.religion} onChange={(event) => apply.setData('religion', event.target.value)} className={field}>
                            <option value="islam">Islam</option>
                            <option value="hinduism">Hinduism</option>
                            <option value="buddhism">Buddhism</option>
                            <option value="christianity">Christianity</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label className="text-sm">Blood group<input aria-label="Blood group" placeholder="e.g. B+" value={apply.data.blood_group} onChange={(event) => apply.setData('blood_group', event.target.value)} className={field} /></label>
                    <label className="text-sm sm:col-span-2">Previous school<input aria-label="Previous school" value={apply.data.previous_school} onChange={(event) => apply.setData('previous_school', event.target.value)} className={field} /></label>
                    <label className="text-sm sm:col-span-2">Present address *<input aria-label="Present address" value={apply.data.address} onChange={(event) => apply.setData('address', event.target.value)} className={field} /></label>
                </section>
                <section className="grid gap-3 sm:grid-cols-2">
                    <h2 className="font-bold sm:col-span-2">Guardian</h2>
                    <label className="text-sm">Guardian name *<input aria-label="Guardian name" value={apply.data.guardian_name} onChange={(event) => apply.setData('guardian_name', event.target.value)} className={field} /></label>
                    <label className="text-sm">Relation *
                        <select aria-label="Relation" value={apply.data.relation} onChange={(event) => apply.setData('relation', event.target.value)} className={field}>
                            <option value="father">Father</option>
                            <option value="mother">Mother</option>
                            <option value="local_guardian">Local guardian</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label className="text-sm">Mobile *<input aria-label="Mobile" value={apply.data.mobile} onChange={(event) => apply.setData('mobile', event.target.value)} className={field} /></label>
                    <label className="text-sm">Email<input aria-label="Email" value={apply.data.email} onChange={(event) => apply.setData('email', event.target.value)} className={field} /></label>
                    <label className="text-sm">Mother's name<input aria-label="Mother's name" value={apply.data.mother_name} onChange={(event) => apply.setData('mother_name', event.target.value)} className={field} /></label>
                    <label className="text-sm">Occupation<input aria-label="Occupation" value={apply.data.occupation} onChange={(event) => apply.setData('occupation', event.target.value)} className={field} /></label>
                </section>
                <p className="text-sm text-slate-500">You will receive a tracking number — keep it, along with this mobile number.</p>
                <button disabled={apply.processing} className="rounded-[10px] bg-[#b31299] px-4 py-2 text-sm font-semibold text-white">Submit application</button>
            </form>

            <form
                id="track"
                onSubmit={(event) => {
                    event.preventDefault();
                    track.post(route('admission.track'));
                }}
                className="mt-8 space-y-3 rounded-3xl bg-white p-6 shadow-sm"
            >
                <h2 className="font-bold">Check status</h2>
                <p className="text-sm text-slate-500">Both are required — a tracking number alone is not enough.</p>
                {status && (
                    <p className={`rounded-lg px-3 py-2 text-sm ${status.found ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-700'}`}>
                        {status.found ? `${status.name} · ${status.class} · ${status.state}` : 'No application matched that tracking number and mobile.'}
                    </p>
                )}
                <label className="block text-sm">Tracking number *<input aria-label="Tracking number" value={track.data.tracking_no} onChange={(event) => track.setData('tracking_no', event.target.value)} className={field} /></label>
                <label className="block text-sm">Guardian mobile *<input aria-label="Guardian mobile" value={track.data.mobile} onChange={(event) => track.setData('mobile', event.target.value)} className={field} /></label>
                <button className="rounded-[10px] border border-[#b31299] px-4 py-2 text-sm font-semibold text-[#b31299]">Check status</button>
            </form>
        </div>
    );
}

function Results({ page }: { page: Record<string, any> }) {
    const form = useForm({ exam: page.exam || 'half-yearly-8', roll: page.roll || '' });

    return (
        <Article title="Exam Results" body="Results appear here only after the school publishes them.">
            <form
                onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    form.get('/results', { preserveState: true });
                }}
                className="mt-8 grid max-w-xl gap-3 rounded-2xl bg-white p-5 shadow-sm"
            >
                <h2 className="font-bold">Check your result</h2>
                <label className="text-sm">Examination
                    <select aria-label="Examination" value={form.data.exam} onChange={(event) => form.setData('exam', event.target.value)} className={field}>
                        <option value="">Choose…</option>
                        {page.exams.map((exam: { value: string; label: string }) => (
                            <option key={exam.value} value={exam.value}>{exam.label}</option>
                        ))}
                    </select>
                </label>
                <label className="text-sm">Roll number
                    <input aria-label="Roll number" value={form.data.roll} onChange={(event) => form.setData('roll', event.target.value)} className={field} />
                </label>
                <button className="w-fit rounded-[10px] bg-[#b31299] px-4 py-2 text-sm font-semibold text-white">Check result</button>
            </form>
            {page.result && (
                <div className="mt-6 max-w-xl rounded-2xl bg-white p-5 shadow-sm">
                    {page.result.found ? (
                        <>
                            <h3 className="text-xl font-bold">{page.result.name}</h3>
                            <p className="text-sm text-slate-500">{page.result.exam} · Class {page.result.class} · Roll {page.result.roll}</p>
                            <table className="mt-4 w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs tracking-wide text-slate-400 uppercase">
                                        <th className="py-2">Subject</th>
                                        <th>Marks</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {page.result.rows.map((row: { subject: string; marks: string; grade: string }) => (
                                        <tr key={row.subject} className="border-t border-slate-100">
                                            <td className="py-2">{row.subject}</td>
                                            <td>{row.marks}</td>
                                            <td>{row.grade}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </>
                    ) : (
                        <p className="text-sm text-slate-600">No published result matched that examination and roll number.</p>
                    )}
                </div>
            )}
        </Article>
    );
}
