<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The data on someone's own identity card: an ID card for students and employees, a visiting pass for parents. */
class PersonalCard
{
    public static function for(User $user): array
    {
        $year = (string) (DB::table('academic_sessions')->where('is_current', true)->value('name') ?: today()->year);
        $base = [
            'kind' => 'employee', 'name' => $user->name, 'photo' => Photos::url($user->photo), 'phone' => $user->phone, 'email' => $user->email,
            'valid_until' => 'Dec '.$year, 'session' => $year, 'lines' => [], 'extra' => [], 'address' => null, 'number' => 'ID-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            'school' => ['name' => SiteSettings::get('school_name', config('app.name')), 'phone' => SiteSettings::get('phone', ''), 'address' => SiteSettings::get('address', '')],
        ];

        if ($user->role === 'student') {
            $p = DB::table('student_profiles')->where('user_id', $user->id)->first();

            return array_merge($base, [
                'kind' => 'student', 'number' => $p->admission_no ?? $base['number'], 'address' => $p->address ?? null, 'session' => $p->session ?? $year,
                'lines' => [['Class', ($p->class_name ?? '').' · '.($p->section ?? '')], ['Roll', $p->roll_no ?? ''], ['Born', $p->date_of_birth ?? '']],
                'extra' => [['Guardian', $p->guardian_name ?? '—'], ['Guardian phone', $p->guardian_mobile ?? '']],
            ]);
        }

        if ($user->role === 'parent') {
            $children = DB::table('guardian_student')->join('users', 'users.id', '=', 'guardian_student.student_id')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                ->where('guardian_student.guardian_id', $user->id)->orderBy('users.name')->get(['users.name', 'student_profiles.class_name', 'student_profiles.section']);

            return array_merge($base, [
                'kind' => 'parent', 'number' => 'VP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'lines' => $children->map(fn ($c) => ['Guardian of', "{$c->name} ({$c->class_name} · {$c->section})"])->all(),
                'extra' => [],
            ]);
        }

        $s = DB::table('staff_profiles')->where('user_id', $user->id)->first();

        return array_merge($base, [
            'number' => $s->employee_no ?? $base['number'], 'address' => $s->address ?? null,
            'lines' => array_values(array_filter([
                ['Designation', $s->designation ?? ($user->role === 'admin' ? 'Administrator' : '')], ['Department', $s->department ?? ''], ['Joined', $s->joined_on ?? ''],
            ], fn ($l) => $l[1] !== '')),
        ]);
    }
}
