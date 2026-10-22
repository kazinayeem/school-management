<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\AttendanceAlerts;
use App\Support\OfficeMenu;
use App\Support\Permissions;
use App\Support\Photos;
use App\Support\TeacherScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TakeAttendanceController extends Controller
{
    private const STATUSES = ['present', 'late', 'absent', 'leave'];

    /** How far back a teacher may still correct attendance. Admins have no limit. */
    private const TEACHER_DAYS_BACK = 7;

    public function index(Request $request): Response
    {
        Permissions::office($request);
        $user = $request->user();
        $teacher = TeacherScope::applies($user);

        $date = $this->date($request->query('date'));
        $classes = DB::table('academic_classes')->orderBy('sort_order')->pluck('name')->all();
        if ($teacher) {
            $classes = array_values(array_intersect($classes, TeacherScope::classes($user)));
        }
        $class = in_array($request->query('class'), $classes, true) ? (string) $request->query('class') : ($classes[0] ?? '');

        $sections = $teacher ? ($class === '' ? [] : TeacherScope::sectionLetters($user, $class)) : ['A', 'B'];
        sort($sections);
        $section = in_array($request->query('section'), $sections, true) ? (string) $request->query('section') : ($sections[0] ?? '');

        $subjects = $this->subjectOptions($user, $class, $section);
        $requested = (string) $request->query('subject', '');
        $subject = collect($subjects)->contains('id', $requested) ? $requested : ($subjects[0]['id'] ?? '');

        $students = collect();
        $saved = false;
        if ($class !== '' && $section !== '' && $subject !== '') {
            $existing = $subject === 'whole'
                ? DB::table('attendance_days')->where('date', $date)->pluck('status', 'user_id')
                : DB::table('period_attendance')->where('date', $date)->where('subject_id', (int) $subject)->pluck('status', 'user_id');

            $students = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                ->where('student_profiles.status', 'active')->where('student_profiles.class_name', $class)->where('student_profiles.section', $section)
                ->orderByRaw('cast(student_profiles.roll_no as integer)')->get(['users.id', 'users.name', 'users.photo', 'student_profiles.roll_no'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'roll_no' => $s->roll_no, 'photo' => Photos::url($s->photo), 'status' => $existing[$s->id] ?? 'present', 'marked' => isset($existing[$s->id])]);
            $saved = $students->contains('marked', true);
        }

        return Inertia::render('office/take-attendance', [
            'navigation' => OfficeMenu::for($user->role),
            'roleLabel' => Permissions::label($user->role),
            'filters' => ['date' => $date, 'class' => $class, 'section' => $section, 'subject' => (string) $subject],
            'classes' => $classes,
            'sections' => $sections,
            'subjects' => $subjects,
            'students' => $students->values(),
            'saved' => $saved,
            'today' => today()->toDateString(),
            'earliest' => $teacher ? today()->subDays(self::TEACHER_DAYS_BACK)->toDateString() : null,
            'unassigned' => $teacher && $classes === [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Permissions::office($request);
        $user = $request->user();
        $teacher = TeacherScope::applies($user);

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today', $teacher ? 'after_or_equal:'.today()->subDays(self::TEACHER_DAYS_BACK)->toDateString() : 'date'],
            'class_name' => ['required', Rule::exists('academic_classes', 'name')],
            'section' => ['required', Rule::in(['A', 'B'])],
            'subject' => ['required', 'string'],
            'marks' => ['required', 'array', 'min:1', 'max:500'],
            'marks.*.user_id' => ['required', 'integer'],
            'marks.*.status' => ['required', Rule::in(self::STATUSES)],
        ], ['date.after_or_equal' => 'Teachers can only record attendance for the last '.self::TEACHER_DAYS_BACK.' days.']);

        abort_unless(collect($this->subjectOptions($user, $data['class_name'], $data['section']))->contains('id', $data['subject']), 403);

        $valid = DB::table('student_profiles')->where('class_name', $data['class_name'])->where('section', $data['section'])->where('status', 'active')->pluck('user_id')->all();
        $marks = collect($data['marks'])->filter(fn ($m) => in_array((int) $m['user_id'], $valid, true));
        if ($marks->count() !== count($data['marks'])) {
            return back()->withErrors(['marks' => 'Some students are not in this section.']);
        }

        $ids = $marks->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        $before = DB::table('attendance_days')->where('date', $data['date'])->whereIn('user_id', $ids)->pluck('status', 'user_id');

        DB::transaction(function () use ($data, $marks, $ids, $user) {
            foreach ($marks as $m) {
                if ($data['subject'] === 'whole') {
                    DB::table('attendance_days')->updateOrInsert(['user_id' => $m['user_id'], 'date' => $data['date']], ['status' => $m['status']]);
                } else {
                    DB::table('period_attendance')->updateOrInsert(
                        ['user_id' => $m['user_id'], 'date' => $data['date'], 'subject_id' => (int) $data['subject']],
                        ['status' => $m['status'], 'marked_by' => $user->id, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            }
            if ($data['subject'] !== 'whole') {
                $this->rollUp($data['date'], $ids);
            }
        });

        $after = DB::table('attendance_days')->where('date', $data['date'])->whereIn('user_id', $ids)->pluck('status', 'user_id');
        AttendanceAlerts::notify($user->id, $data['date'], collect($ids)->filter(fn ($id) => ($after[$id] ?? null) === 'absent' && ($before[$id] ?? null) !== 'absent')->all());
        Audit::log('updated', 'Attendance', "{$data['class_name']} {$data['section']} · {$data['date']} · ".($data['subject'] === 'whole' ? 'whole day' : 'subject #'.$data['subject']));

        return back()->with('status', 'Attendance saved.');
    }

    /**
     * The daily register follows the subject marks: present if the student was in any class
     * that day, late if only late, on leave if only on leave, otherwise absent.
     */
    private function rollUp(string $date, array $studentIds): void
    {
        foreach (DB::table('period_attendance')->where('date', $date)->whereIn('user_id', $studentIds)->get(['user_id', 'status'])->groupBy('user_id') as $id => $rows) {
            $set = $rows->pluck('status');
            $day = $set->contains('present') ? 'present' : ($set->contains('late') ? 'late' : ($set->contains('leave') && ! $set->contains('absent') ? 'leave' : 'absent'));
            DB::table('attendance_days')->updateOrInsert(['user_id' => $id, 'date' => $date], ['status' => $day]);
        }
    }

    /**
     * What can be marked for a section. Teachers get the subjects they teach there;
     * admins and office staff also get "Whole day" and every subject.
     *
     * @return list<array{id: string, name: string}>
     */
    private function subjectOptions($user, string $class, string $section): array
    {
        if ($class === '' || $section === '') {
            return [];
        }
        if (TeacherScope::applies($user)) {
            return array_map(fn ($s) => ['id' => (string) $s['id'], 'name' => $s['name']], TeacherScope::subjects($user, $class, $section));
        }

        return [
            ['id' => 'whole', 'name' => 'Whole day'],
            ...DB::table('academic_subjects')->where('active', true)->where(fn ($q) => $q->whereNull('class_name')->orWhere('class_name', $class))->orderBy('name')->get(['id', 'name'])
                ->map(fn ($s) => ['id' => (string) $s->id, 'name' => $s->name])->all(),
        ];
    }

    private function date(mixed $value): string
    {
        $date = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : today()->toDateString();

        return $date > today()->toDateString() ? today()->toDateString() : $date;
    }
}
