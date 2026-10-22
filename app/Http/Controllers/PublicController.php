<?php

namespace App\Http\Controllers;

use App\Support\PublicContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(): Response
    {
        $notices = $this->noticeList();

        return Inertia::render('public/site', [
            'page' => [
                'kind' => 'home',
                'title' => config('app.name'),
                'ticker' => $notices[0] ?? null,
                'notices' => array_slice($notices, 0, 3),
                'events' => $this->homeEvents(),
                'teachers' => array_slice(PublicContent::teachers(), 0, 8),
                'faqs' => $this->faqList(),
                'albums' => array_slice(PublicContent::albums(), 0, 4),
                'banners' => PublicContent::banners(),
                'site' => PublicContent::site(),
                'admissionOpen' => PublicContent::site()['admission_open'],
                'gallery' => PublicContent::gallery(8),
                'testimonials' => PublicContent::testimonials(),
                'fees' => PublicContent::fees(),
                'stats' => PublicContent::stats(),
            ],
        ]);
    }

    public function page(string $slug): Response
    {
        $pages = [
            'about' => ['About us', 'We teach from Class 1 to Class 10 under the national curriculum. Class sections are kept small so that every child is known by name rather than by roll number.', ['Recognised by the education board', 'Sport, music and debate, not only lessons', 'Parents can follow the school day online']],
            'facilities' => ['Facilities', 'The library stays open all day, buses cover the city, and an SMS goes out the same day a child is marked absent.', ['Library open to every student', 'Transport with a driver you can call', 'Same-day absence SMS']],
            'principal-message' => ["Principal's message", 'Our mission is to foster an environment where curiosity is celebrated and challenges are viewed as opportunities for growth. We do not just teach subjects; we build character.', ['Dr. Ananya Sharma, Principal']],
            'mission-and-vision' => ['Mission & vision', 'Academic excellence and character sit side by side. Every child who walks through the door is known by name.', ['Curiosity before rote learning', 'Integrity in sport and study', 'A school the neighbourhood grew up with']],
            'school-uniform' => ['School uniform', 'The regular uniform is navy and white. The approved winter uniform may be worn from the date announced on the notice board.', ['Navy and white on school days', 'Winter uniform from the announced date', 'Shoes should be plain black']],
            'privacy-policy' => ['Privacy policy', 'Application details, guardian numbers and result lookups are used only to run the school. We do not sell this information.', ['Admission forms stay with the office', 'Result checks use roll number only', 'Contact messages are read by the office']],
            'terms-and-conditions' => ['Terms & conditions', 'Online admission gives you a tracking number. A place is confirmed only after the office reviews the application.', ['A tracking number is not a confirmed seat', 'Published results can be checked by roll number', 'Fee receipts are issued after a payment is recorded']],
        ];

        $edited = \App\Support\SitePages::find($slug);
        if ($edited) {
            return $this->render([
                'kind' => 'article',
                'title' => $edited->title,
                'html' => \App\Support\SitePages::html((string) $edited->body),
                'body' => '',
                'policies' => $edited->kind === 'policy' ? \App\Support\SitePages::policies() : [],
                'ticker' => $this->ticker(),
            ]);
        }

        abort_unless(isset($pages[$slug]), 404);
        [$title, $body, $points] = $pages[$slug];

        return $this->render([
            'kind' => 'article',
            'title' => $title,
            'body' => $body,
            'points' => $points,
            'ticker' => $this->ticker(),
        ]);
    }

    /** Fee schedule and the school's fee policies, all editable by the office. */
    public function fees(): Response
    {
        $heads = DB::table('fee_heads')->where('active', true)->orderBy('category')->orderBy('name')->get();
        $classes = PublicContent::fees();
        $extras = $heads->filter(fn ($h) => ! in_array($h->category, ['tuition', 'admission'], true))->map(fn ($h) => [
            'name' => $h->name, 'amount' => (int) $h->amount, 'class' => $h->class_name, 'frequency' => $h->frequency ?? null, 'category' => $h->category,
        ])->values()->all();
        $site = PublicContent::site();

        return $this->render([
            'kind' => 'fees',
            'title' => 'Fees & policies',
            'ticker' => $this->ticker(),
            'classes' => $classes,
            'extras' => $extras,
            'policies' => collect(\App\Support\SitePages::policies())->map(function ($p) {
                $row = \App\Support\SitePages::find($p['slug']);

                return $p + ['html' => \App\Support\SitePages::html((string) ($row->body ?? ''))];
            })->all(),
            'pay' => collect(DB::table('site_settings')->where('key', 'like', 'pay_%')->pluck('value', 'key'))->all() + ['instructions' => DB::table('site_settings')->where('key', 'gateway_instructions')->value('value')],
        ]);
    }

    public function teachers(): Response
    {
        return $this->render([
            'kind' => 'teachers',
            'title' => 'Teachers',
            'ticker' => $this->ticker(),
            'teachers' => PublicContent::teachers(),
        ]);
    }

    public function gallery(): Response
    {
        return $this->render([
            'kind' => 'gallery',
            'title' => 'Gallery',
            'ticker' => $this->ticker(),
            'albums' => PublicContent::albums(),
            'items' => PublicContent::gallery(),
        ]);
    }

    public function notices(): Response
    {
        return $this->render([
            'kind' => 'notices',
            'title' => 'Notices',
            'ticker' => $this->ticker(),
            'notices' => $this->noticeList(),
        ]);
    }

    public function notice(int $notice): Response
    {
        $row = DB::table('school_notices')->where('id', $notice)->first();
        abort_if($row === null, 404);

        return $this->render([
            'kind' => 'notice',
            'title' => $row->title,
            'ticker' => $this->ticker(),
            'notice' => [
                'title' => $row->title,
                'body' => $row->body,
                'published' => $row->published_on,
            ],
        ]);
    }

    public function events(): Response
    {
        return $this->render([
            'kind' => 'events',
            'title' => 'Events',
            'ticker' => $this->ticker(),
            'events' => PublicContent::events(),
        ]);
    }

    public function event(string $event): Response
    {
        $row = PublicContent::event($event);
        abort_if($row === null, 404);

        return $this->render([
            'kind' => 'event',
            'title' => $row['title'],
            'ticker' => $this->ticker(),
            'event' => $row,
        ]);
    }

    public function contact(): Response
    {
        return $this->render([
            'kind' => 'contact',
            'title' => 'Contact',
            'ticker' => $this->ticker(),
            'sent' => (bool) session('sent'),
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        DB::table('contact_messages')->insert([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect('/contact')->with('sent', true);
    }

    public function admission(): Response
    {
        return $this->render([
            'kind' => 'admission',
            'title' => 'Admission',
            'ticker' => $this->ticker(),
            'tracking' => session('tracking'),
            'status' => session('status'),
        ]);
    }

    public function storeAdmission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_name' => ['required', 'in:Class 5,Class 6,Class 7,Class 8'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date'],
            'gender' => ['required', 'in:male,female,other'],
            'religion' => ['required', 'in:islam,hinduism,buddhism,christianity,other'],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'relation' => ['required', 'in:father,mother,local_guardian,other'],
            'mobile' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
        ]);

        $tracking = 'ADM-'.now()->format('Y').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        DB::table('admission_applications')->insert([
            ...$data,
            'tracking_no' => $tracking,
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect('/admission')->with('tracking', $tracking);
    }

    public function trackAdmission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tracking_no' => ['required', 'string'],
            'mobile' => ['required', 'string'],
        ]);

        $row = DB::table('admission_applications')
            ->where('tracking_no', $data['tracking_no'])
            ->where('mobile', $data['mobile'])
            ->first();

        return redirect('/admission')->with('status', $row === null ? ['found' => false] : [
            'found' => true,
            'tracking' => $row->tracking_no,
            'name' => trim($row->first_name.' '.$row->last_name),
            'class' => $row->class_name,
            'state' => ucfirst($row->status),
        ]);
    }

    public function results(Request $request): Response
    {
        $exam = collect(PublicContent::exams())->firstWhere('value', $request->query('exam'));
        $roll = trim((string) $request->query('roll', ''));
        $result = null;

        if ($exam !== null && $roll !== '') {
            $student = DB::table('users')
                ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                ->where('student_profiles.class_name', $exam['class'])
                ->where('student_profiles.roll_no', $roll)
                ->first(['users.id', 'users.name', 'student_profiles.roll_no', 'student_profiles.class_name', 'student_profiles.section']);

            if ($student !== null) {
                $rows = DB::table('exam_results')
                    ->where('user_id', $student->id)
                    ->where('exam', $exam['exam'])
                    ->get(['subject', 'marks', 'full_marks', 'letter_grade']);
                $result = [
                    'found' => $rows->isNotEmpty(),
                    'name' => $student->name,
                    'roll' => $student->roll_no,
                    'class' => trim(str_replace('Class ', '', $student->class_name).'-'.$student->section),
                    'exam' => $exam['label'],
                    'rows' => $rows->map(fn ($row) => [
                        'subject' => $row->subject,
                        'marks' => $row->marks.'/'.$row->full_marks,
                        'grade' => $row->letter_grade,
                    ])->all(),
                ];
            } else {
                $result = ['found' => false];
            }
        }

        return $this->render([
            'kind' => 'results',
            'title' => 'Exam Results',
            'ticker' => $this->ticker(),
            'exams' => PublicContent::exams(),
            'exam' => $request->query('exam', ''),
            'roll' => $roll,
            'result' => $result,
        ]);
    }

    /** Upcoming events from the Website → Events list, or the built-in ones when none are published. */
    private function homeEvents(): array
    {
        $rows = DB::table('website_events')->where('published', true)->where('starts_on', '>=', today()->toDateString())->orderBy('starts_on')->limit(4)->get();
        if ($rows->isEmpty()) {
            return PublicContent::events();
        }

        return $rows->map(fn ($e) => [
            'slug' => 'event-'.$e->id, 'href' => '/events', 'day' => \Illuminate\Support\Carbon::parse($e->starts_on)->format('d'), 'month' => \Illuminate\Support\Carbon::parse($e->starts_on)->format('M'),
            'title' => $e->title, 'place' => $e->venue ?: 'School premises', 'body' => $e->body,
        ])->all();
    }

    private function faqList(): array
    {
        $rows = DB::table('faqs')->where('published', true)->orderBy('sort_order')->get();

        return $rows->isEmpty() ? PublicContent::faqs() : $rows->map(fn ($f) => ['q' => $f->question, 'a' => $f->answer])->all();
    }

    private function render(array $page): Response
    {
        return Inertia::render('public/site', ['page' => $page]);
    }

    private function ticker(): ?array
    {
        return $this->noticeList()[0] ?? null;
    }

    private function noticeList(): array
    {
        $today = now()->toDateString();

        return DB::table('school_notices')
            ->orderByRaw('case when expires_on is null or expires_on >= ? then 0 else 1 end', [$today])
            ->orderByDesc('published_on')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'published' => $row->published_on,
                'day' => \Illuminate\Support\Carbon::parse($row->published_on)->format('d'),
                'month' => \Illuminate\Support\Carbon::parse($row->published_on)->format('M'),
            ])
            ->all();
    }
}
