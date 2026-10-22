<?php

namespace App\Support;

class PublicContent
{
    public static function teachers(): array
    {
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
