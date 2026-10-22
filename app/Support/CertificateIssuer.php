<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class CertificateIssuer
{
    /** Fills a template for one student and records it in the register. Returns the certificate id. */
    public static function issue(object $student, object $template, string $date, int $issuedBy): int
    {
        $body = strtr($template->body, [
            '{student_name}' => $student->name, '{guardian}' => $student->guardian_name ?: '—', '{class}' => trim($student->class_name.' '.$student->section),
            '{roll}' => (string) $student->roll_no, '{admission_no}' => (string) $student->admission_no, '{date}' => date('j F Y', strtotime($date)),
            '{school_name}' => (string) (SiteSettings::get('school_name') ?: config('app.name')),
        ]);

        return DB::transaction(function () use ($student, $template, $date, $issuedBy, $body) {
            $year = date('Y', strtotime($date));
            $seq = (int) DB::table('certificates')->where('number', 'like', "CERT-{$year}-%")->count() + 1;
            do {
                $number = "CERT-{$year}-".str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);
            } while (DB::table('certificates')->where('number', $number)->exists());

            return DB::table('certificates')->insertGetId([
                'number' => $number, 'template_id' => $template->id, 'user_id' => $student->id, 'title' => $template->name, 'body' => $body, 'style' => $template->style, 'show_photo' => $template->show_photo,
                'issued_on' => $date, 'issued_by' => $issuedBy, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public static function student(int $userId): ?object
    {
        return DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')->where('users.id', $userId)
            ->first(['users.id', 'users.name', 'student_profiles.class_name', 'student_profiles.section', 'student_profiles.roll_no', 'student_profiles.admission_no', 'student_profiles.guardian_name']);
    }
}
