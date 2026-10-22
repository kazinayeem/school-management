<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Grading
{
    /** @var list<object{grade: string, min_marks: int, max_marks: int, point: float}>|null */
    private static ?array $scale = null;

    public static function flush(): void
    {
        self::$scale = null;
    }

    /** @return list<object> */
    public static function scale(): array
    {
        return self::$scale ??= DB::table('grade_scales')->orderByDesc('min_marks')->get()->map(fn ($r) => (object) ['grade' => $r->grade, 'min_marks' => (int) $r->min_marks, 'max_marks' => (int) $r->max_marks, 'point' => (float) $r->point])->all();
    }

    /**
     * Grade and point for one subject. Failing or absent always gives F and 0.
     *
     * @return array{grade: string, point: float, failed: bool}
     */
    public static function subject(int $marks, int $full, int $pass, bool $absent): array
    {
        if ($absent || $marks < $pass || $full < 1) {
            return ['grade' => 'F', 'point' => 0.0, 'failed' => true];
        }

        $percent = (int) round($marks / $full * 100);
        foreach (self::scale() as $band) {
            if ($percent >= $band->min_marks && $percent <= $band->max_marks) {
                return ['grade' => $band->grade, 'point' => $band->point, 'failed' => $band->point <= 0.0];
            }
        }

        return ['grade' => 'F', 'point' => 0.0, 'failed' => true];
    }

    /**
     * GPA over a student's subjects: the average point, or 0 if any subject failed.
     *
     * @param  iterable<array{point: float, failed: bool}>  $subjects
     * @return array{gpa: float, grade: string, failed: bool}
     */
    public static function overall(iterable $subjects): array
    {
        $points = [];
        $failed = false;
        foreach ($subjects as $s) {
            $points[] = $s['point'];
            $failed = $failed || $s['failed'];
        }

        if ($points === [] || $failed) {
            return ['gpa' => 0.0, 'grade' => 'F', 'failed' => true];
        }

        $gpa = round(array_sum($points) / count($points), 2);
        $scale = collect(self::scale())->sortByDesc('point');
        $grade = $scale->first(fn ($b) => $b->point <= $gpa + 1e-9 && $b->point > 0)?->grade ?? 'F';

        return ['gpa' => $gpa, 'grade' => $grade, 'failed' => false];
    }
}
