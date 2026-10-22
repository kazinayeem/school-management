<?php

namespace App\Support;

class PublicContent
{
    /** The school's contact details and wording, as set under Website → Site Settings. */
    public static function site(): array
    {
        $get = fn (string $k, string $d) => \App\Support\SiteSettings::get($k, $d) ?: $d;

        return [
            'name' => $get('school_name', config('app.name')),
            'tagline' => $get('tagline', 'Nurturing minds, building future leaders'),
            'phone' => $get('phone', '+880 1711-000000'),
            'email' => $get('email', 'office@borno.edu.bd'),
            'address' => $get('address', 'House 12, Road 5, Dhanmondi, Dhaka 1205'),
            'facebook' => \App\Support\SiteSettings::get('facebook_url', ''),
            'footer' => $get('footer_text', 'Education that builds character, not just results.'),
            'admission_open' => \App\Support\SiteSettings::get('admission_open', '1') !== '0',
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function banners(): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('hero_banners')->where('active', true)->orderBy('sort_order')->limit(5)->get();
        if ($rows->isEmpty()) {
            return [['eyebrow' => 'ADMISSIONS OPEN', 'title' => 'Nurturing minds, building future leaders', 'subtitle' => 'A holistic learning environment where academic excellence meets character development.', 'image' => '/images/site/hero-campus.svg', 'label' => 'Apply for admission', 'url' => '/admission']];
        }

        return $rows->map(fn ($r) => ['eyebrow' => $r->eyebrow, 'title' => $r->title, 'subtitle' => $r->subtitle, 'image' => $r->image_url ?: '/images/site/hero-campus.svg', 'label' => $r->button_label, 'url' => $r->button_url ?: '/admission'])->all();
    }

    /** @return list<array<string, mixed>> */
    public static function gallery(?int $limit = null): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('gallery_items')->whereNotNull('image_url')->where('image_url', '!=', '')->orderBy('sort_order')->orderBy('id')->when($limit, fn ($q) => $q->limit($limit))->get();

        return $rows->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'album' => $r->album, 'caption' => $r->caption, 'image' => $r->image_url])->all();
    }

    /** @return list<array<string, mixed>> */
    public static function testimonials(): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('testimonials')->where('published', true)->orderByDesc('id')->limit(6)->get();

        return $rows->map(fn ($r) => ['name' => $r->name, 'role' => $r->role, 'quote' => $r->quote, 'rating' => (int) ($r->rating ?: 5)])->all();
    }

    /** Monthly tuition and admission fee for each class, straight from the fee structure. @return list<array<string, mixed>> */
    public static function fees(): array
    {
        $db = \Illuminate\Support\Facades\DB::table('fee_heads')->where('active', true)->whereNotNull('class_name')->whereIn('category', ['tuition', 'admission'])->get()->groupBy('class_name');
        $out = [];
        foreach ($db as $class => $heads) {
            $out[] = ['class' => $class, 'tuition' => (int) ($heads->firstWhere('category', 'tuition')->amount ?? 0), 'admission' => (int) ($heads->firstWhere('category', 'admission')->amount ?? 0)];
        }
        usort($out, fn ($a, $b) => (int) substr($a['class'], 6) <=> (int) substr($b['class'], 6));

        return $out;
    }

    /** @return list<array{value: string, label: string}> */
    public static function stats(): array
    {
        $db = fn (string $t) => \Illuminate\Support\Facades\DB::table($t);
        $students = $db('student_profiles')->where('status', 'active')->count();
        $teachers = $db('users')->where('role', 'teacher')->where('is_active', 1)->count();
        $classes = $db('academic_classes')->count();
        $books = (int) $db('library_books')->sum('copies');

        return array_values(array_filter([
            $students > 0 ? ['value' => number_format($students).'+', 'label' => 'Students'] : null,
            $teachers > 0 ? ['value' => (string) $teachers, 'label' => 'Teachers'] : null,
            $classes > 0 ? ['value' => (string) $classes, 'label' => 'Classes, 1 to '.$classes] : null,
            $books > 0 ? ['value' => number_format($books), 'label' => 'Library books'] : null,
        ]));
    }

    public static function teachers(): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('users')
            ->join('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'teacher')->where('users.is_active', 1)
            ->orderByRaw("case when staff_profiles.designation like 'Senior%' then 0 else 1 end")->orderBy('staff_profiles.employee_no')
            ->get(['users.name', 'users.photo', 'staff_profiles.department', 'staff_profiles.designation']);

        if ($rows->isNotEmpty()) {
            return $rows->map(fn ($r) => [
                'name' => $r->name, 'subject' => $r->department ?: 'Teacher', 'role' => $r->designation ?: 'Teacher', 'photo' => Photos::url($r->photo),
            ])->all();
        }

        return [
            ['name' => 'John Smith', 'subject' => 'Mathematics', 'role' => 'Assistant Teacher'],
            ['name' => 'Alisha Henry', 'subject' => 'English', 'role' => 'Assistant Teacher'],
            ['name' => 'Jordan Mitchell', 'subject' => 'Mathematics', 'role' => 'Assistant Teacher'],
            ['name' => 'Georgina Hayward', 'subject' => 'English', 'role' => 'Assistant Teacher'],
            ['name' => 'Cooper Donin', 'subject' => 'Science', 'role' => 'Assistant Teacher'],
            ['name' => 'Rebecca Lawson', 'subject' => 'Bangla', 'role' => 'Assistant Teacher'],
            ['name' => 'Frank Sullivan', 'subject' => 'Mathematics', 'role' => 'Senior Teacher'],
            ['name' => 'Miriam Fletcher', 'subject' => 'Social Science', 'role' => 'Assistant Teacher'],
        ];
    }

    public static function events(): array
    {
        return [
            [
                'slug' => 'blood-donation-camp',
                'day' => '22',
                'month' => 'Oct',
                'date' => '22 October 2026',
                'title' => 'Voluntary blood donation camp',
                'place' => 'School premises',
                'body' => 'Students, staff and parents are invited to the voluntary blood donation camp on the school premises. Registration opens at the office from 9 AM.',
            ],
            [
                'slug' => 'annual-picnic',
                'day' => '29',
                'month' => 'Oct',
                'date' => '29 October 2026',
                'title' => 'Annual school picnic',
                'place' => 'Botanical garden',
                'body' => 'The annual picnic is at the botanical garden. Buses leave from the school gate in the morning and return before sunset. Consent forms are with the class teacher.',
            ],
        ];
    }

    public static function event(string $slug): ?array
    {
        foreach (self::events() as $event) {
            if ($event['slug'] === $slug) {
                return $event;
            }
        }

        return null;
    }

    public static function albums(): array
    {
        return [
            ['title' => 'Morning assembly', 'tone' => 'from-fuchsia-700 to-rose-500'],
            ['title' => 'Science fair', 'tone' => 'from-indigo-700 to-sky-500'],
            ['title' => 'Annual sports', 'tone' => 'from-emerald-700 to-lime-500'],
            ['title' => 'Library week', 'tone' => 'from-amber-600 to-orange-400'],
            ['title' => 'Debate club', 'tone' => 'from-slate-800 to-fuchsia-800'],
            ['title' => 'Founding day', 'tone' => 'from-purple-800 to-pink-500'],
        ];
    }

    public static function faqs(): array
    {
        return [
            ['q' => 'When does admission open for the new session?', 'a' => 'Admission usually opens a few months before the session begins. The exact dates are announced on the notice board and on this website, and you can apply online without visiting the school.'],
            ['q' => 'What documents are needed for admission?', 'a' => 'Bring a birth certificate, two passport-size photographs, and the previous school certificate if the child is joining above Class 1. You can submit the online form first and bring the papers when the office calls.'],
            ['q' => "How do I check my child's exam result?", 'a' => 'Open Check Exam Result, choose the examination, and enter the roll number. Results appear only after the school publishes them.'],
            ['q' => 'Does the school provide transport?', 'a' => 'Yes. Routes run across the city, and each bus has a driver the office can put you in touch with. Seats are assigned from the parent portal after admission.'],
            ['q' => "How can I contact my child's class teacher?", 'a' => 'Sign in to the parent portal, or call the school office on a working day. The office will pass a message to the class teacher.'],
            ['q' => 'Can I pay fees online?', 'a' => 'Yes. Parents sign in and settle dues from the portal. The office counter also accepts payment on working days.'],
        ];
    }

    public static function exams(): array
    {
        $published = \Illuminate\Support\Facades\DB::table('exam_publications')->join('exams', 'exams.id', '=', 'exam_publications.exam_id')
            ->orderByDesc('exam_publications.id')->get(['exam_publications.exam_id', 'exam_publications.class_name', 'exams.name'])
            ->map(fn ($p) => ['value' => "exam-{$p->exam_id}-".\Illuminate\Support\Str::slug($p->class_name), 'label' => "{$p->name} — {$p->class_name}", 'class' => $p->class_name, 'exam' => $p->name])->all();

        return [
            ...$published,
            ['value' => 'half-yearly-8', 'label' => 'Half Yearly Examination 2026 — Class 8', 'class' => 'Class 8', 'exam' => 'Half Yearly Examination 2026'],
            ['value' => 'half-yearly-5', 'label' => 'Half Yearly Examination 2026 — Class 5', 'class' => 'Class 5', 'exam' => 'Half Yearly Examination 2026'],
        ];
    }
}
