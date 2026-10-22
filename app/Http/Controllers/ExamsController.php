<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Grading;
use App\Support\OfficeMenu;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExamsController extends Controller
{
    // ---- setup

    public function setup(Request $request): Response
    {
        $this->admin($request);
        $exams = DB::table('exams')->orderByDesc('starts_on')->get()->map(function ($e) {
            $e->classes = DB::table('exam_subjects')->where('exam_id', $e->id)->distinct()->count('class_name');
            $e->marked = DB::table('exam_results')->where('exam_id', $e->id)->exists();
            $e->published = DB::table('exam_publications')->where('exam_id', $e->id)->count();

            return $e;
        });

        $examId = (int) $request->query('exam', $exams->first()->id ?? 0);
        $class = $this->classOrDefault((string) $request->query('class', ''));
        $schedule = [];

        if ($examId && $exams->contains('id', $examId)) {
            $existing = DB::table('exam_subjects')->where('exam_id', $examId)->where('class_name', $class)->get()->keyBy('subject_id');
            $marked = DB::table('exam_results')->where('exam_id', $examId)->whereIn('subject_id', $existing->keys())->whereIn('user_id', $this->classStudentIds($class))->distinct()->pluck('subject_id')->all();

            foreach ($this->subjectsFor($class) as $s) {
                $row = $existing[$s->id] ?? null;
                $schedule[] = [
                    'subject_id' => $s->id, 'name' => $s->name, 'include' => $row !== null,
                    'exam_date' => $row->exam_date ?? '', 'starts_at' => $row ? substr((string) $row->starts_at, 0, 5) : '',
                    'full_marks' => (int) ($row->full_marks ?? $s->full_marks), 'pass_marks' => (int) ($row->pass_marks ?? $s->pass_marks), 'room' => $row->room ?? '',
                    'locked' => in_array($s->id, $marked, true),
                ];
            }
        }

        return Inertia::render('office/exam-setup', [
            ...$this->shared($request),
            'exams' => $exams,
            'classes' => $this->classes(),
            'selected' => ['exam' => $examId, 'class' => $class],
            'schedule' => $schedule,
            'published' => $examId ? DB::table('exam_publications')->where('exam_id', $examId)->where('class_name', $class)->exists() : false,
            'rooms' => DB::table('rooms')->where('active', true)->orderBy('name')->pluck('name'),
        ]);
    }

    public function storeExam(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $this->examData($request);
        $id = DB::table('exams')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        Audit::log('created', 'Exam', $data['name']);

        return redirect("/app/exams?exam={$id}")->with('status', 'Exam created.');
    }

    public function updateExam(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        abort_unless(DB::table('exams')->where('id', $id)->exists(), 404);
        DB::table('exams')->where('id', $id)->update($this->examData($request, $id) + ['updated_at' => now()]);
        Audit::log('updated', 'Exam', "#{$id}");

        return back()->with('status', 'Exam updated.');
    }

    public function destroyExam(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        if (DB::table('exam_results')->where('exam_id', $id)->exists() || DB::table('exam_publications')->where('exam_id', $id)->exists()) {
            return back()->with('error', 'This exam has marks and cannot be deleted.');
        }
        DB::table('exams')->where('id', $id)->delete();
        Audit::log('deleted', 'Exam', "#{$id}");

        return redirect('/app/exams')->with('status', 'Entry deleted.');
    }

    public function saveSchedule(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'class_name' => ['required', Rule::in($this->classes())],
            'rows' => ['present', 'array'],
            'rows.*.subject_id' => ['required', 'integer', 'exists:academic_subjects,id'],
            'rows.*.include' => ['boolean'],
            'rows.*.exam_date' => ['nullable', 'date'],
            'rows.*.starts_at' => ['nullable', 'date_format:H:i'],
            'rows.*.full_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'rows.*.pass_marks' => ['required', 'integer', 'min:0', 'lte:rows.*.full_marks'],
            'rows.*.room' => ['nullable', 'string', Rule::exists('rooms', 'name')->where('active', 1)],
        ]);

        if (DB::table('exam_publications')->where('exam_id', $data['exam_id'])->where('class_name', $data['class_name'])->exists()) {
            return back()->with('error', 'The result is published. Unpublish it before changing the schedule.');
        }

        $exam = DB::table('exams')->find($data['exam_id']);
        $allowed = $this->subjectsFor($data['class_name'])->pluck('id')->all();
        $errors = [];
        $keep = [];
        foreach ($data['rows'] as $i => $row) {
            if (empty($row['include'])) {
                continue;
            }
            if (! in_array((int) $row['subject_id'], $allowed, true)) {
                $errors["rows.{$i}.subject_id"] = 'This subject is not taught in that class.';
            }
            if (blank($row['exam_date'] ?? null)) {
                $errors["rows.{$i}.exam_date"] = 'Choose the exam date.';
            } elseif ($row['exam_date'] < $exam->starts_on || $row['exam_date'] > $exam->ends_on) {
                $errors["rows.{$i}.exam_date"] = 'The date must fall within the exam period.';
            }
            $keep[(int) $row['subject_id']] = $row;
        }

        // A room cannot hold two exams on the same day within three hours of each other.
        $minutes = fn (?string $t) => $t ? ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2) : null;
        $close = fn (?int $a, ?int $b) => $a === null || $b === null || abs($a - $b) < 180;
        $booked = [];
        foreach ($data['rows'] as $i => $row) {
            if (empty($row['include']) || blank($row['room'] ?? null) || blank($row['exam_date'] ?? null)) {
                continue;
            }
            $mine = $minutes($row['starts_at'] ?? null);
            $others = DB::table('exam_subjects')->where('room', $row['room'])->where('exam_date', $row['exam_date'])
                ->where(fn ($q) => $q->where('exam_id', '!=', $data['exam_id'])->orWhere('class_name', '!=', $data['class_name']))->get();
            $clash = $others->first(fn ($o) => $close($mine, $minutes($o->starts_at ? substr((string) $o->starts_at, 0, 5) : null)))
                || collect($booked)->contains(fn ($b) => $b[0] === $row['room'] && $b[1] === $row['exam_date'] && $close($mine, $b[2]));
            if ($clash) {
                $errors["rows.{$i}.room"] = "{$row['room']} is already booked for another exam at that time.";
            }
            $booked[] = [$row['room'], $row['exam_date'], $mine];
        }
        if ($errors) {
            return back()->withErrors($errors);
        }

        $students = $this->classStudentIds($data['class_name']);
        $removed = DB::table('exam_subjects')->where('exam_id', $data['exam_id'])->where('class_name', $data['class_name'])->whereNotIn('subject_id', array_keys($keep))->pluck('subject_id');
        if ($removed->isNotEmpty() && DB::table('exam_results')->where('exam_id', $data['exam_id'])->whereIn('subject_id', $removed)->whereIn('user_id', $students)->exists()) {
            return back()->with('error', 'A subject that already has marks cannot be removed from the schedule.');
        }

        DB::transaction(function () use ($data, $keep, $removed) {
            DB::table('exam_subjects')->where('exam_id', $data['exam_id'])->where('class_name', $data['class_name'])->whereIn('subject_id', $removed)->delete();
            foreach ($keep as $subjectId => $row) {
                DB::table('exam_subjects')->updateOrInsert(
                    ['exam_id' => $data['exam_id'], 'class_name' => $data['class_name'], 'subject_id' => $subjectId],
                    ['exam_date' => $row['exam_date'], 'starts_at' => $row['starts_at'] ?: null, 'full_marks' => $row['full_marks'], 'pass_marks' => $row['pass_marks'], 'room' => $row['room'] ?: null, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });
        Audit::log('updated', 'Exam schedule', "{$exam->name} · {$data['class_name']}");

        return back()->with('status', 'Schedule saved.');
    }

    // ---- admit cards

    public function admitCards(Request $request): Response
    {
        $this->admin($request);
        [$exam, $class, $section] = $this->filters($request);
        $roll = trim((string) $request->query('roll', ''));

        $schedule = $exam ? DB::table('exam_subjects')->join('academic_subjects', 'academic_subjects.id', '=', 'exam_subjects.subject_id')
            ->where('exam_subjects.exam_id', $exam->id)->where('exam_subjects.class_name', $class)->orderBy('exam_date')->orderBy('starts_at')
            ->get(['academic_subjects.name as subject', 'exam_subjects.exam_date', 'exam_subjects.starts_at', 'exam_subjects.room'])->map(fn ($r) => ['subject' => $r->subject, 'date' => $r->exam_date, 'time' => substr((string) $r->starts_at, 0, 5), 'room' => $r->room]) : collect();

        $students = $exam ? $this->students($class, $section)->when($roll !== '', fn ($c) => $c->filter(fn ($s) => (string) $s->roll_no === $roll))->values() : collect();

        return Inertia::render('office/admit-cards', [
            ...$this->shared($request),
            'exams' => DB::table('exams')->orderByDesc('starts_on')->get(['id', 'name', 'starts_on', 'ends_on']),
            'classes' => $this->classes(),
            'filters' => ['exam' => $exam->id ?? 0, 'class' => $class, 'section' => $section ?? '', 'roll' => $roll],
            'exam' => $exam,
            'schedule' => $schedule,
            'students' => $students,
            'school' => ['name' => SiteSettings::get('school_name', config('app.name'))],
        ]);
    }

    // ---- marks

    public function marks(Request $request): Response
    {
        $this->access($request, '/app/exams/marks');
        [$exam, $class, $section] = $this->filters($request);
        $subjects = $exam ? $this->enterableSubjects($request, $exam->id, $class, $section) : collect();
        $subjectId = (int) $request->query('subject', $subjects->first()->subject_id ?? 0);
        $current = $subjects->firstWhere('subject_id', $subjectId) ?? $subjects->first();
        $subjectId = $current->subject_id ?? 0;
        $locked = $exam ? DB::table('exam_publications')->where('exam_id', $exam->id)->where('class_name', $class)->exists() : false;

        $existing = $current ? DB::table('exam_results')->where('exam_id', $exam->id)->where('subject_id', $subjectId)->get()->keyBy('user_id') : collect();
        $students = $current ? $this->onlyMine($this->students($class, $section), $class)->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'roll_no' => $s->roll_no, 'section' => $s->section,
            'marks' => isset($existing[$s->id]) && ! $existing[$s->id]->absent ? (int) $existing[$s->id]->marks : null,
            'absent' => (bool) ($existing[$s->id]->absent ?? false),
        ])->values() : collect();

        return Inertia::render('office/marks-entry', [
            ...$this->shared($request),
            'exams' => DB::table('exams')->orderByDesc('starts_on')->get(['id', 'name']),
            'classes' => $this->classes(),
            'filters' => ['exam' => $exam->id ?? 0, 'class' => $class, 'section' => $section ?? '', 'subject' => $subjectId],
            'subjects' => $subjects->map(fn ($s) => ['id' => $s->subject_id, 'name' => $s->name])->values(),
            'current' => $current ? ['full_marks' => (int) $current->full_marks, 'pass_marks' => (int) $current->pass_marks] : null,
            'students' => $students,
            'locked' => $locked,
            'scale' => collect(Grading::scale())->map(fn ($b) => (array) $b)->values(),
            'canUnlock' => $request->user()->role === 'admin',
        ]);
    }

    public function saveMarks(Request $request): RedirectResponse
    {
        $this->access($request, '/app/exams/marks');
        $data = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'class_name' => ['required', Rule::in($this->classes())],
            'section' => ['nullable', Rule::in(['A', 'B'])],
            'subject_id' => ['required', 'integer'],
            'entries' => ['present', 'array', 'max:500'],
            'entries.*.user_id' => ['required', 'integer'],
            'entries.*.marks' => ['nullable', 'integer', 'min:0'],
            'entries.*.absent' => ['boolean'],
        ]);

        $slot = $this->enterableSubjects($request, $data['exam_id'], $data['class_name'], $data['section'] ?? null)->firstWhere('subject_id', (int) $data['subject_id']);
        abort_if($slot === null, 403);

        if (DB::table('exam_publications')->where('exam_id', $data['exam_id'])->where('class_name', $data['class_name'])->exists()) {
            return back()->with('error', 'The result is published. Marks cannot be changed.');
        }

        $valid = $this->onlyMine($this->students($data['class_name'], $data['section'] ?? null), $data['class_name'])->pluck('id')->all();
        $errors = [];
        foreach ($data['entries'] as $i => $e) {
            if (! in_array((int) $e['user_id'], $valid, true)) {
                $errors["entries.{$i}.user_id"] = 'That student is not in this class.';
            } elseif (empty($e['absent']) && isset($e['marks']) && (int) $e['marks'] > (int) $slot->full_marks) {
                $errors["entries.{$i}.marks"] = 'Marks cannot be more than the full marks.';
            }
        }
        if ($errors) {
            return back()->withErrors($errors);
        }

        $exam = DB::table('exams')->find($data['exam_id']);
        $saved = 0;
        DB::transaction(function () use ($data, $slot, $exam, &$saved) {
            foreach ($data['entries'] as $e) {
                $keys = ['user_id' => (int) $e['user_id'], 'exam_id' => (int) $data['exam_id'], 'subject_id' => (int) $data['subject_id']];
                $absent = ! empty($e['absent']);

                if (! $absent && ($e['marks'] ?? null) === null) {
                    DB::table('exam_results')->where($keys)->delete();

                    continue;
                }

                $marks = $absent ? 0 : (int) $e['marks'];
                $g = Grading::subject($marks, (int) $slot->full_marks, (int) $slot->pass_marks, $absent);
                DB::table('exam_results')->updateOrInsert($keys, [
                    'exam' => $exam->name, 'subject' => $slot->name, 'marks' => $marks, 'full_marks' => (int) $slot->full_marks,
                    'letter_grade' => $g['grade'], 'failed' => $g['failed'], 'absent' => $absent, 'grade_point' => $g['point'],
                ]);
                $saved++;
            }
        });
        Audit::log('updated', 'Marks', "{$exam->name} · {$data['class_name']} · {$slot->name}");

        return back()->with('status', 'Marks saved.');
    }

    // ---- results

    public function results(Request $request): Response
    {
        $this->access($request, '/app/exams/results');
        [$exam, $class, $section] = $this->filters($request);
        $board = $exam ? $this->board($exam->id, $class, $section) : ['subjects' => [], 'rows' => [], 'stats' => null];
        if ($exam && \App\Support\TeacherScope::applies($request->user())) {
            $board['rows'] = array_values(array_filter($board['rows'], fn ($r) => \App\Support\TeacherScope::allowsSection($request->user(), $class, $r['section'])));
        }

        return Inertia::render('office/exam-results', [
            ...$this->shared($request),
            'exams' => DB::table('exams')->orderByDesc('starts_on')->get(['id', 'name']),
            'classes' => $this->classes(),
            'filters' => ['exam' => $exam->id ?? 0, 'class' => $class, 'section' => $section ?? ''],
            'subjects' => $board['subjects'],
            'rows' => $board['rows'],
            'stats' => $board['stats'],
            'published' => $exam ? DB::table('exam_publications')->where('exam_id', $exam->id)->where('class_name', $class)->exists() : false,
            'canPublish' => $request->user()->role === 'admin',
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['exam_id' => ['required', 'integer', 'exists:exams,id'], 'class_name' => ['required', Rule::in($this->classes())]]);

        if (DB::table('exam_publications')->where('exam_id', $data['exam_id'])->where('class_name', $data['class_name'])->exists()) {
            return back()->with('error', 'This result is already published.');
        }

        $board = $this->board($data['exam_id'], $data['class_name'], null);
        if ($board['subjects'] === []) {
            return back()->with('error', 'Set up the exam schedule for this class first.');
        }
        if ($board['rows'] === []) {
            return back()->with('error', 'There are no students in this class.');
        }
        $incomplete = collect($board['rows'])->where('complete', false)->count();
        if ($incomplete > 0) {
            return back()->with('error', 'Some students still have marks missing. Enter all marks before publishing.');
        }

        $exam = DB::table('exams')->find($data['exam_id']);
        DB::transaction(function () use ($board, $data, $exam) {
            foreach ($board['rows'] as $r) {
                DB::table('student_profiles')->where('user_id', $r['id'])->update([
                    'exam_name' => $exam->name, 'gpa' => $r['gpa'], 'class_position' => $r['class_position'], 'section_position' => $r['section_position'],
                ]);
                foreach ($r['cells'] as $subjectId => $cell) {
                    DB::table('exam_results')->where(['user_id' => $r['id'], 'exam_id' => $data['exam_id'], 'subject_id' => $subjectId])
                        ->update(['letter_grade' => $cell['grade'], 'failed' => $cell['failed'], 'grade_point' => $cell['point']]);
                }
            }
            DB::table('exam_publications')->insert(['exam_id' => $data['exam_id'], 'class_name' => $data['class_name'], 'published_at' => now()]);
        });
        Audit::log('other', 'Results', "Published {$exam->name} · {$data['class_name']}");

        return back()->with('status', 'Results published.');
    }

    public function unpublish(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['exam_id' => ['required', 'integer'], 'class_name' => ['required', 'string']]);
        DB::table('exam_publications')->where('exam_id', $data['exam_id'])->where('class_name', $data['class_name'])->delete();
        Audit::log('other', 'Results', "Unpublished exam #{$data['exam_id']} · {$data['class_name']}");

        return back()->with('status', 'Results unpublished.');
    }

    // ---- helpers

    /**
     * Marks, grades, GPA and merit positions for an exam and class.
     *
     * @return array{subjects: list<array<string, mixed>>, rows: list<array<string, mixed>>, stats: ?array<string, mixed>}
     */
    private function board(int $examId, string $class, ?string $sectionFilter): array
    {
        $subjects = DB::table('exam_subjects')->join('academic_subjects', 'academic_subjects.id', '=', 'exam_subjects.subject_id')
            ->where('exam_subjects.exam_id', $examId)->where('exam_subjects.class_name', $class)->orderBy('academic_subjects.name')
            ->get(['exam_subjects.subject_id', 'academic_subjects.name', 'exam_subjects.full_marks', 'exam_subjects.pass_marks']);
        $students = $this->students($class, null);
        $marks = DB::table('exam_results')->where('exam_id', $examId)->whereIn('user_id', $students->pluck('id'))->get()->groupBy('user_id');

        $rows = $students->map(function ($s) use ($subjects, $marks) {
            $cells = [];
            $total = 0;
            $complete = $subjects->isNotEmpty();
            foreach ($subjects as $sub) {
                $m = ($marks[$s->id] ?? collect())->firstWhere('subject_id', $sub->subject_id);
                if ($m === null) {
                    $complete = false;

                    continue;
                }
                $g = Grading::subject((int) $m->marks, (int) $sub->full_marks, (int) $sub->pass_marks, (bool) $m->absent);
                $cells[$sub->subject_id] = ['marks' => (int) $m->marks, 'absent' => (bool) $m->absent, 'grade' => $g['grade'], 'point' => $g['point'], 'failed' => $g['failed']];
                $total += (int) $m->marks;
            }
            $overall = $complete ? Grading::overall($cells) : ['gpa' => null, 'grade' => null, 'failed' => null];

            return ['id' => $s->id, 'name' => $s->name, 'roll_no' => $s->roll_no, 'section' => $s->section, 'cells' => $cells, 'total' => $total, 'complete' => $complete,
                'gpa' => $overall['gpa'], 'grade' => $overall['grade'], 'failed' => $overall['failed'], 'class_position' => null, 'section_position' => null];
        })->all();

        // Merit order: students who passed first, then higher GPA, then higher total, then lower roll number.
        $rank = function (Collection $list): array {
            $sorted = $list->where('complete', true)->sort(
                fn ($a, $b) => ($a['failed'] <=> $b['failed']) ?: ($b['gpa'] <=> $a['gpa']) ?: ($b['total'] <=> $a['total']) ?: ((int) $a['roll_no'] <=> (int) $b['roll_no'])
            )->values();

            return $sorted->mapWithKeys(fn ($r, $i) => [$r['id'] => $i + 1])->all();
        };

        $all = collect($rows);
        $classRank = $rank($all);
        $sectionRank = [];
        foreach ($all->groupBy('section') as $group) {
            $sectionRank += $rank($group);
        }
        foreach ($rows as &$r) {
            $r['class_position'] = $classRank[$r['id']] ?? null;
            $r['section_position'] = $sectionRank[$r['id']] ?? null;
        }
        unset($r);

        $shown = collect($rows)->when($sectionFilter, fn ($c, $s) => $c->where('section', $s))->sortBy([fn ($a, $b) => ($a['class_position'] ?? PHP_INT_MAX) <=> ($b['class_position'] ?? PHP_INT_MAX), fn ($a, $b) => (int) $a['roll_no'] <=> (int) $b['roll_no']])->values();
        $done = $shown->where('complete', true);

        return [
            'subjects' => $subjects->map(fn ($s) => ['id' => $s->subject_id, 'name' => $s->name, 'full_marks' => (int) $s->full_marks, 'pass_marks' => (int) $s->pass_marks])->all(),
            'rows' => $shown->map(fn ($r) => $r + ['cells' => (object) $r['cells']])->all(),
            'stats' => $shown->isEmpty() ? null : [
                'students' => $shown->count(), 'complete' => $done->count(), 'passed' => $done->where('failed', false)->count(), 'failed' => $done->where('failed', true)->count(),
                'pass_rate' => $done->isEmpty() ? null : (int) round($done->where('failed', false)->count() / $done->count() * 100),
                'average_gpa' => $done->isEmpty() ? null : round($done->avg('gpa'), 2),
            ],
        ];
    }

    private function examData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('exams', 'name')->ignore($id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
    }

    /** @return array{0: ?object, 1: string, 2: ?string} */
    private function filters(Request $request): array
    {
        $exam = DB::table('exams')->find((int) $request->query('exam', 0)) ?? DB::table('exams')->orderByDesc('starts_on')->first();
        $section = in_array($request->query('section'), ['A', 'B'], true) ? (string) $request->query('section') : null;

        return [$exam, $this->classOrDefault((string) $request->query('class', '')), $section];
    }

    /** Subjects scheduled for the exam and class that this user may enter marks for. */
    private function enterableSubjects(Request $request, int $examId, string $class, ?string $section): Collection
    {
        $query = DB::table('exam_subjects')->join('academic_subjects', 'academic_subjects.id', '=', 'exam_subjects.subject_id')
            ->where('exam_subjects.exam_id', $examId)->where('exam_subjects.class_name', $class)->orderBy('academic_subjects.name')
            ->select('exam_subjects.subject_id', 'academic_subjects.name', 'exam_subjects.full_marks', 'exam_subjects.pass_marks');

        $user = $request->user();
        if ($user->role !== 'admin') {
            $query->whereIn('exam_subjects.subject_id', DB::table('teaching_assignments')->where('user_id', $user->id)->where('class_name', $class)
                ->when($section, fn ($q, $s) => $q->where(fn ($q) => $q->whereNull('section')->orWhere('section', $s)))->pluck('subject_id'));
        }

        return $query->get();
    }

    private function students(string $class, ?string $section): Collection
    {
        return DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where('student_profiles.status', 'active')->where('student_profiles.class_name', $class)
            ->when($section, fn ($q, $s) => $q->where('student_profiles.section', $s))
            ->orderBy('student_profiles.section')->orderByRaw('cast(student_profiles.roll_no as integer)')
            ->get(['users.id', 'users.name', 'student_profiles.roll_no', 'student_profiles.section', 'student_profiles.admission_no', 'student_profiles.class_name']);
    }

    /** @return list<int> */
    private function classStudentIds(string $class): array
    {
        return $this->students($class, null)->pluck('id')->all();
    }

    private function subjectsFor(string $class): Collection
    {
        return DB::table('academic_subjects')->where('active', true)->where(fn ($q) => $q->whereNull('class_name')->orWhere('class_name', $class))->orderBy('name')->get();
    }

    /** @return list<string> */
    private function classes(): array
    {
        $all = DB::table('academic_classes')->orderBy('sort_order')->pluck('name')->all();
        $user = request()->user();

        return $user && \App\Support\TeacherScope::applies($user) ? array_values(array_intersect($all, \App\Support\TeacherScope::classes($user))) : $all;
    }

    /** Teachers see only the students in sections they teach. */
    private function onlyMine(Collection $students, string $class): Collection
    {
        $user = request()->user();

        return $user && \App\Support\TeacherScope::applies($user) ? $students->filter(fn ($s) => \App\Support\TeacherScope::allowsSection($user, $class, $s->section))->values() : $students;
    }

    private function classOrDefault(string $class): string
    {
        $classes = $this->classes();

        return in_array($class, $classes, true) ? $class : ($classes[0] ?? 'Class 1');
    }

    private function shared(Request $request): array
    {
        $role = $request->user()->role;

        return ['navigation' => OfficeMenu::for($role), 'roleLabel' => \App\Support\Permissions::label($role)];
    }

    private function access(Request $request, string $path): void
    {
        $role = $request->user()->role;
        \App\Support\Permissions::office($request);
        abort_unless(OfficeMenu::allows($role, $path), 403);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
