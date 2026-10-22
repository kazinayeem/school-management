<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A teacher works only with the classes, sections and subjects they have been assigned
 * under Academic Structure → Teaching Assignments. Everyone else is not limited.
 */
class TeacherScope
{
    public static function applies(User $user): bool
    {
        return $user->role === 'teacher';
    }

    /**
     * The (class, section) pairs a teacher is assigned to. A blank section means both A and B.
     *
     * @return list<array{class: string, section: string}>
     */
    public static function sections(User $user): array
    {
        $pairs = [];
        foreach (DB::table('teaching_assignments')->where('user_id', $user->id)->get(['class_name', 'section']) as $a) {
            foreach ($a->section ? [$a->section] : ['A', 'B'] as $letter) {
                $pairs["{$a->class_name}|{$letter}"] = ['class' => $a->class_name, 'section' => $letter];
            }
        }

        return array_values($pairs);
    }

    /** @return list<string> */
    public static function classes(User $user): array
    {
        return array_values(array_unique(array_column(self::sections($user), 'class')));
    }

    /** @return list<string> */
    public static function sectionLetters(User $user, string $class): array
    {
        return array_values(array_unique(array_column(array_filter(self::sections($user), fn ($p) => $p['class'] === $class), 'section')));
    }

    public static function allowsSection(User $user, string $class, ?string $section): bool
    {
        if (! self::applies($user)) {
            return true;
        }

        foreach (self::sections($user) as $p) {
            if ($p['class'] === $class && ($section === null || $section === '' || $p['section'] === $section)) {
                return true;
            }
        }

        return false;
    }

    /** Whether this user may see this student at all. */
    public static function canSee(User $user, int $studentId): bool
    {
        if (! self::applies($user)) {
            return true;
        }

        $profile = DB::table('student_profiles')->where('user_id', $studentId)->first(['class_name', 'section']);

        return $profile !== null && self::allowsSection($user, $profile->class_name, $profile->section);
    }

    /** Limit a query that includes the student_profiles table to the teacher's own students. */
    public static function restrict(Builder|\Illuminate\Database\Eloquent\Builder $query, User $user, string $table = 'student_profiles'): void
    {
        if (! self::applies($user)) {
            return;
        }

        $pairs = self::sections($user);
        if ($pairs === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($q) use ($pairs, $table) {
            foreach ($pairs as $p) {
                $q->orWhere(fn ($w) => $w->where("{$table}.class_name", $p['class'])->where("{$table}.section", $p['section']));
            }
        });
    }

    /**
     * The subjects a teacher teaches in one section.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function subjects(User $user, string $class, string $section): array
    {
        return DB::table('teaching_assignments')->join('academic_subjects', 'academic_subjects.id', '=', 'teaching_assignments.subject_id')
            ->where('teaching_assignments.user_id', $user->id)->where('teaching_assignments.class_name', $class)
            ->where(fn ($q) => $q->whereNull('teaching_assignments.section')->orWhere('teaching_assignments.section', $section))
            ->orderBy('academic_subjects.name')->get(['academic_subjects.id', 'academic_subjects.name'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])->unique('id')->values()->all();
    }
}
