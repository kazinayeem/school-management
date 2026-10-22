<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Builds a whole exam timetable in one go: one subject a day for every chosen class, skipping weekends. */
class ExamPlanner
{
    public const WEEKEND = [5, 6]; // Friday, Saturday

    /** @param list<string> $classes */
    public static function fill(int $examId, string $startsOn, string $endsOn, array $classes, string $time = '10:00', ?string $room = null): int
    {
        $days = [];
        for ($d = strtotime($startsOn); $d <= strtotime($endsOn); $d += 86400) {
            if (! in_array((int) date('w', $d), self::WEEKEND, true)) {
                $days[] = date('Y-m-d', $d);
            }
        }

        $rows = [];
        foreach ($classes as $class) {
            $subjects = DB::table('academic_subjects')->where('active', true)->where(fn ($q) => $q->whereNull('class_name')->orWhere('class_name', $class))->orderBy('name')->get();
            if ($subjects->count() > count($days)) {
                throw ValidationException::withMessages(['ends_on' => "{$class} has {$subjects->count()} subjects but the period has only ".count($days).' working days. Extend the end date.']);
            }
            foreach ($subjects as $i => $s) {
                $rows[] = ['exam_id' => $examId, 'class_name' => $class, 'subject_id' => $s->id, 'exam_date' => $days[$i], 'starts_at' => $time, 'full_marks' => (int) ($s->full_marks ?? 100), 'pass_marks' => (int) ($s->pass_marks ?? 33), 'room' => $room, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('exam_subjects')->insert($chunk);
        }

        return count($rows);
    }
}
