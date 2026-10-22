<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Numbers behind the dashboard charts. Everything is summed in the database and limited to what the
 * signed-in person may see: teachers get their own sections, families get their own child, and
 * office staff get only the modules they have been granted.
 */
class DashboardCharts
{
    public const RANGES = [7, 14, 30, 60, 90];

    /** @return array<string, mixed> */
    public static function office(User $user, Request $request): array
    {
        $admin = $user->role === 'admin';
        $teacher = TeacherScope::applies($user);
        $can = fn (string $module) => $admin || Permissions::canRole($user->role, $module, 'view');

        $days = (int) $request->input('range', 30);
        $days = in_array($days, self::RANGES, true) ? $days : 30;
        $allClasses = DB::table('academic_classes')->orderBy('sort_order')->pluck('name')->all();
        $classes = $teacher ? array_values(array_intersect($allClasses, TeacherScope::classes($user))) : $allClasses;
        $class = in_array($request->input('class'), $classes, true) ? (string) $request->input('class') : '';
        $from = today()->subDays($days - 1)->toDateString();

        $students = function () use ($user, $teacher, $class): Builder {
            $q = DB::table('student_profiles')->where('student_profiles.status', 'active');
            $class !== '' && $q->where('student_profiles.class_name', $class);
            $teacher && TeacherScope::restrict($q, $user);

            return $q;
        };

        $out = ['filters' => ['range' => $days, 'ranges' => self::RANGES, 'class' => $class, 'classes' => $classes, 'from' => $from, 'to' => today()->toDateString()]];

        // Who is at the school.
        if ($admin) {
            $roleCounts = DB::table('users')->where('is_active', true)->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');
            $out['people'] = ['students' => (int) ($roleCounts['student'] ?? 0), 'teachers' => (int) ($roleCounts['teacher'] ?? 0), 'staff' => (int) ($roleCounts['staff'] ?? 0), 'parents' => (int) ($roleCounts['parent'] ?? 0)];
        }

        if ($can('students')) {
            $rows = $students()->selectRaw('class_name, section, count(*) as total')->groupBy('class_name', 'section')->get();
            $by = [];
            foreach ($rows as $r) {
                $by[$r->class_name][$r->section ?: '—'] = (int) $r->total;
            }
            uksort($by, 'strnatcmp');
            $out['students_by_class'] = collect($by)->map(fn ($sections, $name) => ['name' => $name] + $sections)->values()->all();
            $out['section_keys'] = collect($by)->flatMap(fn ($s) => array_keys($s))->unique()->sort()->values()->all();
        }

        if ($can('attendance')) {
            $q = $students()->join('attendance_days', 'attendance_days.user_id', '=', 'student_profiles.user_id')->where('attendance_days.date', '>=', $from)
                ->selectRaw('attendance_days.date as day, attendance_days.status, count(*) as total')->groupBy('attendance_days.date', 'attendance_days.status')->orderBy('day')->get();
            $out['attendance'] = self::attendanceSeries($q, 'day');
        }

        if ($can('exams')) {
            $taught = $teacher ? DB::table('teaching_assignments')->where('user_id', $user->id)->pluck('subject_id')->all() : null;
            $examId = DB::table('exam_publications')->orderByDesc('published_at')->value('exam_id');
            if ($examId) {
                $base = fn () => DB::table('exam_results')->join('student_profiles', 'student_profiles.user_id', '=', 'exam_results.user_id')->where('exam_results.exam_id', $examId)
                    ->where('student_profiles.status', 'active')
                    ->when($class !== '', fn ($q) => $q->where('student_profiles.class_name', $class))
                    ->when($teacher, function ($q) use ($user, $taught) {
                        TeacherScope::restrict($q, $user);
                        $q->whereIn('exam_results.subject_id', $taught ?: [0]);
                    });
                $out['exam'] = [
                    'name' => (string) DB::table('exams')->where('id', $examId)->value('name'),
                    'grades' => $base()->selectRaw('exam_results.letter_grade as grade, count(*) as total')->groupBy('exam_results.letter_grade')->orderByDesc('total')->get()->map(fn ($r) => ['grade' => $r->grade, 'count' => (int) $r->total])->all(),
                    'subjects' => $base()->selectRaw('exam_results.subject, avg(exam_results.marks * 100.0 / nullif(exam_results.full_marks, 0)) as average, sum(case when exam_results.failed = 1 then 1 else 0 end) as failed, count(*) as total')
                        ->groupBy('exam_results.subject')->orderBy('exam_results.subject')->get()->map(fn ($r) => ['subject' => $r->subject, 'average' => round((float) $r->average, 1), 'fail_rate' => $r->total ? round($r->failed / $r->total * 100, 1) : 0])->all(),
                ];
            }
            $out['gpa_by_class'] = $students()->whereNotNull('gpa')->where('gpa', '>', 0)->selectRaw('class_name as name, avg(gpa) as gpa, count(*) as students')->groupBy('class_name')->get()
                ->sortBy(fn ($r) => (int) substr($r->name, 6))->map(fn ($r) => ['name' => $r->name, 'gpa' => round((float) $r->gpa, 2), 'students' => (int) $r->students])->values()->all();
        }

        if ($can('fees') && ! $teacher) {
            $invoices = DB::table('fee_invoices')->selectRaw('due_on, sum(payable) as billed, sum(paid) as collected')->groupBy('due_on')->orderBy('due_on')->get();
            $months = [];
            foreach ($invoices as $i) {
                $m = substr((string) $i->due_on, 0, 7);
                $months[$m] = ['month' => Carbon::parse($m.'-01')->format('M Y'), 'billed' => ($months[$m]['billed'] ?? 0) + (int) $i->billed, 'collected' => ($months[$m]['collected'] ?? 0) + (int) $i->collected];
            }
            $months = array_slice(array_values(array_map(fn ($m) => $m + ['outstanding' => max(0, $m['billed'] - $m['collected'])], $months)), -8);
            $daily = DB::table('fee_payments')->where('paid_on', '>=', $from)->selectRaw('paid_on as day, sum(amount) as total')->groupBy('paid_on')->orderBy('day')->get();
            $out['fees'] = [
                'months' => $months,
                'collections' => $daily->map(fn ($r) => ['date' => Carbon::parse($r->day)->format('d M'), 'amount' => (int) $r->total])->all(),
                'collected_in_range' => (int) $daily->sum('total'),
                'methods' => DB::table('fee_payments')->where('paid_on', '>=', $from)->selectRaw('method, sum(amount) as total')->groupBy('method')->orderByDesc('total')->get()->map(fn ($r) => ['name' => $r->method, 'value' => (int) $r->total])->all(),
                'outstanding' => (int) DB::table('fee_invoices')->whereColumn('payable', '>', 'paid')->selectRaw('sum(payable - paid) as due')->value('due'),
                'pending_approvals' => DB::table('online_transactions')->where('status', 'pending')->count(),
            ];
        }

        if ($can('admissions') && ! $teacher) {
            $apps = DB::table('admission_applications')->selectRaw('substr(created_at, 1, 7) as month, status, count(*) as total')->groupBy('month', 'status')->orderBy('month')->get();
            $out['admissions'] = [
                'status' => $apps->groupBy('status')->map(fn ($g, $status) => ['name' => $status, 'value' => (int) $g->sum('total')])->values()->all(),
                'months' => $apps->groupBy('month')->map(fn ($g, $m) => ['month' => Carbon::parse($m.'-01')->format('M Y'), 'applications' => (int) $g->sum('total')])->values()->take(-8)->values()->all(),
            ];
        }

        if ($can('website') && ! $teacher) {
            $out['messages'] = DB::table('contact_messages')->selectRaw('status, count(*) as total')->groupBy('status')->get()->map(fn ($r) => ['name' => $r->status ?: 'new', 'value' => (int) $r->total])->all();
        }

        if ($can('library') && ! $teacher) {
            $out['library'] = [
                'status' => DB::table('library_loans')->selectRaw('status, count(*) as total')->groupBy('status')->get()->map(fn ($r) => ['name' => $r->status, 'value' => (int) $r->total])->all(),
                'issued' => DB::table('library_loans')->where('issued_on', '>=', $from)->selectRaw('issued_on as day, count(*) as total')->groupBy('issued_on')->orderBy('day')->get()->map(fn ($r) => ['date' => Carbon::parse($r->day)->format('d M'), 'loans' => (int) $r->total])->all(),
            ];
        }

        if ($can('transport') && ! $teacher) {
            $out['transport'] = DB::table('bus_assignments')->selectRaw('route, count(*) as total')->whereNotNull('route')->groupBy('route')->orderByDesc('total')->get()->map(fn ($r) => ['name' => $r->route, 'value' => (int) $r->total])->all();
        }

        if ($can('homework')) {
            $q = DB::table('homework_items')->selectRaw('status, count(*) as total')->groupBy('status');
            $teacher && $q->where('teacher', $user->name);
            $out['homework'] = $q->get()->map(fn ($r) => ['name' => $r->status, 'value' => (int) $r->total])->all();
        }

        // Coming up: exams for the classes in view, and today's lessons for a teacher.
        $examQuery = DB::table('exam_subjects')->join('academic_subjects', 'academic_subjects.id', '=', 'exam_subjects.subject_id')->join('exams', 'exams.id', '=', 'exam_subjects.exam_id')
            ->whereBetween('exam_subjects.exam_date', [today()->toDateString(), today()->addDays(21)->toDateString()])->orderBy('exam_subjects.exam_date')->limit(6);
        if ($teacher) {
            $examQuery->whereIn('exam_subjects.class_name', $classes ?: ['—'])->whereIn('exam_subjects.subject_id', DB::table('teaching_assignments')->where('user_id', $user->id)->pluck('subject_id'));
        } elseif ($class !== '') {
            $examQuery->where('exam_subjects.class_name', $class);
        }
        if ($can('exams')) {
            $out['upcoming'] = $examQuery->get(['exams.name as exam', 'academic_subjects.name as subject', 'exam_subjects.class_name', 'exam_subjects.exam_date'])->map(fn ($r) => (array) $r)->all();
        }
        if ($teacher) {
            $day = (int) now()->dayOfWeek;
            $out['today_classes'] = $day <= 4 ? DB::table('class_periods')->where('teacher', $user->name)->where('day', $day)->where('is_break', false)->orderBy('sort')
                ->get(['label', 'time_range', 'class_name', 'section', 'subject', 'room'])->map(fn ($r) => (array) $r)->all() : [];
        }

        return $out;
    }

    /** One child's own numbers, for the student dashboard and for a parent looking at one child. */
    public static function student(User $child): array
    {
        $profile = DB::table('student_profiles')->where('user_id', $child->id)->first();
        if ($profile === null) {
            return [];
        }
        $out = [];

        $weeks = DB::table('attendance_days')->where('user_id', $child->id)->where('date', '>=', today()->subWeeks(11)->startOfWeek()->toDateString())->selectRaw('date as day, status, count(*) as total')->groupBy('date', 'status')->orderBy('day')->get();
        $out['attendance'] = self::attendanceSeries($weeks, 'day', weekly: true);

        // Each published exam: the student's GPA and average mark, next to the class average.
        $published = DB::table('exam_publications')->where('class_name', $profile->class_name)->pluck('exam_id');
        $exams = DB::table('exams')->whereIn('id', $published)->orderBy('starts_on')->get(['id', 'name']);
        $mine = DB::table('exam_results')->where('user_id', $child->id)->whereIn('exam_id', $published)->get()->groupBy('exam_id');
        $classAvg = DB::table('exam_results')->join('student_profiles', 'student_profiles.user_id', '=', 'exam_results.user_id')->where('student_profiles.class_name', $profile->class_name)
            ->whereIn('exam_results.exam_id', $published)->selectRaw('exam_results.exam_id, exam_results.subject, avg(exam_results.marks * 100.0 / nullif(exam_results.full_marks, 0)) as average')->groupBy('exam_results.exam_id', 'exam_results.subject')->get()->groupBy('exam_id');
        $out['progress'] = $exams->filter(fn ($e) => isset($mine[$e->id]))->map(function ($e) use ($mine, $classAvg) {
            $rows = $mine[$e->id];
            $failed = $rows->contains(fn ($r) => (bool) $r->failed);
            $points = $rows->avg('grade_point');
            $pct = $rows->sum('full_marks') > 0 ? round($rows->sum('marks') / $rows->sum('full_marks') * 100, 1) : null;

            return ['exam' => $e->name, 'gpa' => $failed ? 0.0 : round((float) $points, 2), 'percent' => $pct, 'class_average' => $classAvg[$e->id] ?? collect() ? round((float) collect($classAvg[$e->id] ?? [])->avg('average'), 1) : null];
        })->values()->all();
        $latest = $exams->filter(fn ($e) => isset($mine[$e->id]))->last();
        if ($latest) {
            $avg = collect($classAvg[$latest->id] ?? [])->keyBy('subject');
            $out['subjects'] = ['exam' => $latest->name, 'rows' => $mine[$latest->id]->sortBy('subject')->map(fn ($r) => [
                'subject' => $r->subject, 'mine' => $r->full_marks ? round($r->marks / $r->full_marks * 100, 1) : 0, 'class' => isset($avg[$r->subject]) ? round((float) $avg[$r->subject]->average, 1) : null, 'grade' => $r->letter_grade,
            ])->values()->all()];
        }

        $inv = DB::table('fee_invoices')->where('user_id', $child->id)->selectRaw('sum(paid) as paid, sum(case when payable > paid then payable - paid else 0 end) as due')->first();
        $out['fees'] = ['paid' => (int) ($inv->paid ?? 0), 'due' => (int) ($inv->due ?? 0), 'next_due' => DB::table('fee_invoices')->where('user_id', $child->id)->whereColumn('payable', '>', 'paid')->min('due_on')];
        $out['homework'] = DB::table('homework_items')->where('user_id', $child->id)->selectRaw('status, count(*) as total')->groupBy('status')->get()->map(fn ($r) => ['name' => $r->status, 'value' => (int) $r->total])->all();
        $out['upcoming_exams'] = DB::table('exam_subjects')->join('academic_subjects', 'academic_subjects.id', '=', 'exam_subjects.subject_id')->join('exams', 'exams.id', '=', 'exam_subjects.exam_id')
            ->where('exam_subjects.class_name', $profile->class_name)->where('exam_subjects.exam_date', '>=', today()->toDateString())->orderBy('exam_subjects.exam_date')->limit(6)
            ->get(['exams.name as exam', 'academic_subjects.name as subject', 'exam_subjects.exam_date', 'exam_subjects.starts_at', 'exam_subjects.room'])->map(fn ($r) => (array) $r)->all();
        $out['events'] = DB::table('calendar_events')->where('date', '>=', today()->toDateString())->orderBy('date')->limit(5)->get(['date', 'title', 'type'])->map(fn ($r) => (array) $r)->all();

        return $out;
    }

    /**
     * Attendance counts as a daily (or weekly) percentage line and a status split.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows  day, status, total
     * @return array{trend: list<array<string, mixed>>, split: list<array{name: string, value: int}>, percent: ?float}
     */
    private static function attendanceSeries($rows, string $dayKey, bool $weekly = false): array
    {
        $buckets = [];
        $split = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0];
        foreach ($rows as $r) {
            $key = $weekly ? Carbon::parse($r->$dayKey)->startOfWeek()->toDateString() : $r->$dayKey;
            $buckets[$key][$r->status] = ($buckets[$key][$r->status] ?? 0) + (int) $r->total;
            $split[$r->status] = ($split[$r->status] ?? 0) + (int) $r->total;
        }
        ksort($buckets);
        $trend = [];
        foreach ($buckets as $date => $c) {
            $counted = ($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['absent'] ?? 0);
            $trend[] = [
                'date' => Carbon::parse($date)->format($weekly ? 'd M' : 'd M'), 'present' => $c['present'] ?? 0, 'late' => $c['late'] ?? 0, 'absent' => $c['absent'] ?? 0, 'leave' => $c['leave'] ?? 0,
                'percent' => $counted > 0 ? round((($c['present'] ?? 0) + ($c['late'] ?? 0)) / $counted * 100, 1) : null,
            ];
        }
        $counted = $split['present'] + $split['late'] + $split['absent'];

        return [
            'trend' => $trend,
            'split' => collect($split)->map(fn ($v, $k) => ['name' => $k, 'value' => $v])->values()->all(),
            'percent' => $counted > 0 ? round(($split['present'] + $split['late']) / $counted * 100, 1) : null,
        ];
    }
}
