<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

    public function routine(Request $request): Response
    {
        $this->access($request, '/app/timetable');
        $class = $this->classOrDefault((string) $request->query('class', ''));
        $section = in_array($request->query('section'), ['A', 'B'], true) ? (string) $request->query('section') : 'A';

        $cells = [];
        foreach (DB::table('class_periods')->where('class_name', $class)->where('section', $section)->get() as $row) {
            $cells["{$row->day}-{$row->sort}"] = ['subject' => $row->subject, 'teacher' => $row->teacher, 'room' => $row->room];
        }

        return Inertia::render('office/routine', [
            ...$this->shared($request),
            'readOnly' => $request->user()->role !== 'admin',
            'classes' => $this->classes(),
            'filters' => ['class' => $class, 'section' => $section],
            'days' => self::DAYS,
            'periods' => $this->periods(),
            'cells' => (object) $cells,
            'subjects' => DB::table('academic_subjects')->where('active', true)->orderBy('name')->pluck('name'),
            'teachers' => DB::table('users')->where('role', 'staff')->orderBy('name')->pluck('name'),
        ]);
    }

    public function saveRoutine(Request $request): RedirectResponse
    {
        $this->admin($request);
        $periods = DB::table('bell_periods')->get()->keyBy('sort_order');
        $data = $request->validate([
            'class_name' => ['required', Rule::in($this->classes())],
            'section' => ['required', Rule::in(['A', 'B'])],
            'cells' => ['present', 'array'],
            'cells.*.day' => ['required', 'integer', 'between:0,4'],
            'cells.*.sort' => ['required', 'integer', Rule::in($periods->keys()->all())],
            'cells.*.subject' => ['nullable', 'string', Rule::exists('academic_subjects', 'name')],
            'cells.*.teacher' => ['nullable', 'string', Rule::exists('users', 'name')->where('role', 'staff')],
            'cells.*.room' => ['nullable', 'string', 'max:50'],
        ]);

        $clashes = [];
        foreach ($data['cells'] as $cell) {
            if (blank($cell['teacher'] ?? null)) {
                continue;
            }
            $other = DB::table('class_periods')->where('teacher', $cell['teacher'])->where('day', $cell['day'])->where('sort', $cell['sort'])
                ->where(fn ($q) => $q->where('class_name', '!=', $data['class_name'])->orWhere('section', '!=', $data['section']))->first();
            if ($other) {
                $clashes[] = "{$cell['teacher']} already teaches {$other->class_name} {$other->section} on ".self::DAYS[$cell['day']].', '.$periods[$cell['sort']]->label.'.';
            }
        }
        if ($clashes) {
            return back()->withErrors(['cells' => implode(' ', array_unique($clashes))]);
        }

        $given = collect($data['cells'])->keyBy(fn ($c) => "{$c['day']}-{$c['sort']}");
        DB::transaction(function () use ($data, $periods, $given) {
            DB::table('class_periods')->where('class_name', $data['class_name'])->where('section', $data['section'])->delete();
            $rows = [];
            foreach (range(0, 4) as $day) {
                foreach ($periods as $sort => $p) {
                    $c = $given["{$day}-{$sort}"] ?? [];
                    $blank = fn ($v) => ($p->is_break || blank($v)) ? null : $v;
                    $rows[] = [
                        'class_name' => $data['class_name'], 'section' => $data['section'], 'day' => $day, 'sort' => $sort, 'label' => $p->label,
                        'time_range' => substr($p->starts_at, 0, 5).' – '.substr($p->ends_at, 0, 5), 'is_break' => (bool) $p->is_break,
                        'subject' => $blank($c['subject'] ?? null), 'teacher' => $blank($c['teacher'] ?? null), 'room' => $blank($c['room'] ?? null),
                    ];
                }
            }
            DB::table('class_periods')->insert($rows);
        });
        Audit::log('updated', 'Class Routine', "{$data['class_name']} {$data['section']}");

        return back()->with('status', 'Routine saved.');
    }

    public function teacher(Request $request): Response
    {
        $this->access($request, '/app/timetable/teacher');
        $teachers = DB::table('users')->where('role', 'staff')->orderBy('name')->pluck('name')->all();
        $default = $request->user()->role === 'staff' ? $request->user()->name : ($teachers[0] ?? '');
        $teacher = (string) $request->query('teacher', $default);
        $teacher = in_array($teacher, $teachers, true) ? $teacher : $default;

        $cells = [];
        foreach (DB::table('class_periods')->where('teacher', $teacher)->get() as $row) {
            $cells["{$row->day}-{$row->sort}"] = ['subject' => $row->subject, 'teacher' => "{$row->class_name} {$row->section}", 'room' => $row->room];
        }

        return Inertia::render('office/routine', [
            ...$this->shared($request),
            'readOnly' => true,
            'teacherMode' => true,
            'teachers' => $teachers,
            'filters' => ['teacher' => $teacher],
            'days' => self::DAYS,
            'periods' => $this->periods(),
            'cells' => (object) $cells,
        ]);
    }

    public function substitutions(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/substitutions', [
            ...$this->shared($request),
            'rows' => DB::table('substitutions')->join('users', 'users.id', '=', 'substitutions.substitute_user_id')
                ->orderByDesc('date')->orderByDesc('substitutions.id')->limit(200)->get(['substitutions.*', 'users.name as substitute']),
            'classes' => $this->classes(),
            'periods' => collect($this->periods())->where('is_break', false)->values(),
            'teachers' => DB::table('users')->where('role', 'staff')->orderBy('name')->get(['id', 'name']),
            'today' => today()->toDateString(),
        ]);
    }

    public function storeSubstitution(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'class_name' => ['required', Rule::in($this->classes())],
            'section' => ['required', Rule::in(['A', 'B'])],
            'period_label' => ['required', Rule::exists('bell_periods', 'label')->where('is_break', 0)],
            'substitute_user_id' => ['required', Rule::exists('users', 'id')->where('role', 'staff')],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $day = Carbon::parse($data['date'])->dayOfWeek;
        if ($day > 4) {
            return back()->withErrors(['date' => 'School is closed on that day.']);
        }

        $sort = DB::table('bell_periods')->where('label', $data['period_label'])->value('sort_order');
        $original = DB::table('class_periods')->where('class_name', $data['class_name'])->where('section', $data['section'])->where('day', $day)->where('sort', $sort)->value('teacher');
        $substitute = DB::table('users')->where('id', $data['substitute_user_id'])->value('name');

        if ($original === null) {
            return back()->withErrors(['period_label' => 'No class is scheduled for that section in that period.']);
        }
        if ($original === $substitute) {
            return back()->withErrors(['substitute_user_id' => 'Choose a different teacher.']);
        }
        if (DB::table('class_periods')->where('teacher', $substitute)->where('day', $day)->where('sort', $sort)->exists()) {
            return back()->withErrors(['substitute_user_id' => "{$substitute} already has a class in that period."]);
        }
        if (DB::table('substitutions')->where('date', $data['date'])->where('period_label', $data['period_label'])->where('substitute_user_id', $data['substitute_user_id'])->exists()) {
            return back()->withErrors(['substitute_user_id' => "{$substitute} is already covering another class in that period."]);
        }

        DB::table('substitutions')->insert($data + ['original_teacher' => $original, 'created_at' => now(), 'updated_at' => now()]);
        Audit::log('created', 'Substitution', "{$data['class_name']} {$data['section']} on {$data['date']}");

        return back()->with('status', 'Substitution added.');
    }

    public function destroySubstitution(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        DB::table('substitutions')->where('id', $id)->delete();

        return back()->with('status', 'Entry deleted.');
    }

    /** @return list<array{id: int, label: string, time_range: string, is_break: bool, sort: int}> */
    private function periods(): array
    {
        return DB::table('bell_periods')->orderBy('sort_order')->get()->map(fn ($p) => [
            'id' => $p->id, 'label' => $p->label, 'time_range' => substr($p->starts_at, 0, 5).' – '.substr($p->ends_at, 0, 5), 'is_break' => (bool) $p->is_break, 'sort' => (int) $p->sort_order,
        ])->all();
    }

    /** @return list<string> */
    private function classes(): array
    {
        return DB::table('academic_classes')->orderBy('sort_order')->pluck('name')->all();
    }

    private function classOrDefault(string $class): string
    {
        $classes = $this->classes();

        return in_array($class, $classes, true) ? $class : ($classes[0] ?? 'Class 1');
    }

    private function shared(Request $request): array
    {
        $role = $request->user()->role;

        return ['navigation' => OfficeMenu::for($role), 'roleLabel' => $role === 'admin' ? 'Super Admin' : 'Teacher'];
    }

    private function access(Request $request, string $path): void
    {
        $role = $request->user()->role;
        abort_unless(in_array($role, ['admin', 'staff'], true) && OfficeMenu::allows($role, $path), 403);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
