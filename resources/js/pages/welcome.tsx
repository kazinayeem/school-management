import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    CheckCircle2,
    ClipboardCheck,
    FileText,
    GraduationCap,
    HeartHandshake,
    Mail,
    Megaphone,
    Music,
    Phone,
    ShieldCheck,
    Users,
} from 'lucide-react';

const nav = ['Home', 'About us', 'Academics', 'Teachers', 'Gallery', 'Notices', 'Contact'];

const quick = [
    { icon: FileText, title: 'Online Admission', hint: 'Apply from home' },
    { icon: ClipboardCheck, title: 'Check Exam Result', hint: 'By roll number' },
    { icon: Megaphone, title: 'Notice Board', hint: 'Official announcements' },
    { icon: Users, title: 'Parent Login', hint: 'Attendance, results, dues' },
];

const why = [
    { icon: ShieldCheck, title: 'Safe campus', text: 'Supervised grounds, verified pick-up and a staff member at every gate.' },
    { icon: BookOpen, title: 'Small sections', text: 'Teachers know every child by name, not just by roll number.' },
    { icon: Music, title: 'Beyond the books', text: 'Sport, music, debate and science clubs run through the school year.' },
    { icon: HeartHandshake, title: 'Open to parents', text: 'Follow attendance, homework and fees online, any time of day.' },
];

const stats = [
    ['1,200+', 'Students'],
    ['60+', 'Teachers'],
    ['25', 'Years of teaching'],
    ['98%', 'Pass rate'],
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <div className="bg-white text-gray-800">
            <Head title="Borno School" />

            <div className="flex items-center bg-gray-900 text-xs text-white">
                <span className="bg-amber-500 px-4 py-2 font-bold text-gray-900">LATEST NOTICE</span>
                <span className="truncate px-4">Admission for the next academic session is now open</span>
            </div>
            <div className="hidden items-center justify-between bg-fuchsia-950 px-6 py-2 text-xs text-fuchsia-100 md:flex">
                <div className="flex gap-6">
                    <span className="flex items-center gap-1"><Phone className="h-3 w-3" /> +880 1700-000000</span>
                    <span className="flex items-center gap-1"><Mail className="h-3 w-3" /> office@borno.school</span>
                </div>
                <div className="flex gap-6">
                    <span>Notices</span>
                    <span>Events</span>
                    <span>Results</span>
                </div>
            </div>

            <header className="sticky top-0 z-20 flex items-center justify-between border-b bg-white/95 px-6 py-3 backdrop-blur">
                <Link href="/" className="flex items-center gap-2 text-lg font-bold text-fuchsia-800">
                    <GraduationCap className="h-7 w-7" /> Borno School
                </Link>
                <nav className="hidden gap-6 text-sm lg:flex">
                    {nav.map((n, i) => (
                        <a key={n} href="#" className={i === 0 ? 'font-semibold text-fuchsia-700' : 'text-gray-600 hover:text-fuchsia-700'}>
                            {n}
                        </a>
                    ))}
                </nav>
                <div className="flex items-center gap-3">
                    <Link
                        href={auth.user ? route('dashboard') : route('login')}
                        className="rounded-full bg-fuchsia-700 px-5 py-2 text-sm font-semibold text-white hover:bg-fuchsia-800"
                    >
                        {auth.user ? 'Dashboard' : 'Parent / Student Login'}
                    </Link>
                </div>
            </header>

            <section className="relative overflow-hidden bg-gradient-to-br from-gray-900 via-fuchsia-950 to-fuchsia-800 px-6 py-24 text-white md:py-32">
                <div className="mx-auto max-w-6xl">
                    <p className="mb-4 text-xs font-semibold tracking-[0.3em] text-fuchsia-200">ADMISSIONS OPEN</p>
                    <h1 className="max-w-2xl text-4xl leading-tight font-extrabold md:text-6xl">Where every child is known by name.</h1>
                    <p className="mt-5 max-w-lg text-fuchsia-100">A learning community for Class 1 to Class 10, built on good teaching and good character.</p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <a href="#" className="rounded-xl bg-fuchsia-500 px-7 py-3 font-semibold shadow-lg hover:bg-fuchsia-400">Apply for admission</a>
                        <a href="#" className="rounded-xl border border-white/40 px-7 py-3 font-semibold hover:bg-white/10">Check exam result</a>
                    </div>
                </div>
            </section>

            <section className="relative z-10 mx-auto -mt-12 max-w-6xl px-6">
                <div className="grid grid-cols-2 gap-4 rounded-2xl bg-white p-6 shadow-xl md:grid-cols-4">
                    {quick.map(({ icon: Icon, title, hint }) => (
                        <div key={title} className="flex flex-col items-center text-center">
                            <span className="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-fuchsia-50 text-fuchsia-700">
                                <Icon className="h-5 w-5" />
                            </span>
                            <div className="text-sm font-semibold">{title}</div>
                            <div className="text-xs text-gray-500">{hint}</div>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-6 py-20">
                <p className="text-center text-xs font-semibold tracking-[0.3em] text-fuchsia-700">WHY FAMILIES CHOOSE US</p>
                <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {why.map(({ icon: Icon, title, text }) => (
                        <div key={title} className="rounded-2xl border p-6 transition hover:shadow-lg">
                            <Icon className="mb-3 h-7 w-7 text-fuchsia-700" />
                            <h3 className="font-semibold">{title}</h3>
                            <p className="mt-1 text-sm text-gray-500">{text}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mx-auto grid max-w-6xl items-center gap-10 px-6 pb-20 md:grid-cols-2">
                <div className="relative">
                    <div className="aspect-[4/3] rounded-3xl bg-gradient-to-br from-fuchsia-200 to-violet-300" />
                    <div className="absolute -right-3 -bottom-5 rounded-2xl bg-fuchsia-700 px-6 py-4 text-white shadow-lg">
                        <div className="text-3xl font-bold">25</div>
                        <div className="text-xs">YEARS OF TEACHING</div>
                    </div>
                </div>
                <div>
                    <p className="text-xs font-semibold tracking-[0.3em] text-fuchsia-700">KNOW ABOUT US</p>
                    <h2 className="mt-2 text-3xl font-extrabold">A school that grew up with its neighbourhood</h2>
                    <p className="mt-3 text-gray-500">We teach Class 1 to Class 10 under the national curriculum, keeping sections small so teachers can give each child real attention.</p>
                    <ul className="mt-5 space-y-2 text-sm">
                        {['Recognised by the education board', 'Sport, music and debate alongside lessons', 'Parents follow the school day online'].map((t) => (
                            <li key={t} className="flex items-center gap-2"><CheckCircle2 className="h-4 w-4 text-green-600" /> {t}</li>
                        ))}
                    </ul>
                    <a href="#" className="mt-6 inline-block rounded-xl bg-fuchsia-700 px-6 py-2.5 text-sm font-semibold text-white">Read more about us</a>
                </div>
            </section>

            <section className="bg-fuchsia-800 py-12 text-white">
                <div className="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-6 text-center md:grid-cols-4">
                    {stats.map(([v, l]) => (
                        <div key={l}>
                            <div className="text-4xl font-extrabold">{v}</div>
                            <div className="text-sm text-fuchsia-200">{l}</div>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mx-auto grid max-w-6xl items-center gap-10 px-6 py-20 md:grid-cols-2">
                <div className="flex aspect-square max-w-sm items-center justify-center rounded-3xl bg-gradient-to-br from-violet-200 to-fuchsia-300">
                    <Award className="h-24 w-24 text-fuchsia-800" />
                </div>
                <div>
                    <h2 className="text-3xl font-extrabold">Helping students lead with purpose</h2>
                    <p className="mt-4 text-gray-600 italic">“We do not only teach subjects. We help children grow into thoughtful people, and every family who joins us becomes part of that work.”</p>
                    <div className="mt-4 font-semibold text-fuchsia-800">The Principal, Borno School</div>
                </div>
            </section>

            <section className="bg-gray-50 px-6 py-20 text-center">
                <p className="text-xs font-semibold tracking-[0.3em] text-fuchsia-700">ONLINE SERVICES</p>
                <h2 className="mt-2 text-3xl font-extrabold">What you can do online</h2>
                <p className="mx-auto mt-3 max-w-md text-gray-500">The office counter is open all day. Most things no longer need a trip to school.</p>
                <div className="mx-auto mt-8 grid max-w-4xl gap-4 sm:grid-cols-3">
                    {['Apply for admission', 'Check results', 'Pay fees'].map((s) => (
                        <div key={s} className="rounded-2xl bg-white p-6 font-semibold shadow-sm">{s}</div>
                    ))}
                </div>
            </section>

            <footer className="bg-gray-900 px-6 py-8 text-center text-sm text-gray-400">© {new Date().getFullYear()} Borno School. All rights reserved.</footer>
        </div>
    );
}
