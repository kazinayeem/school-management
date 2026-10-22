<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use App\Support\Permissions;
use App\Support\Photos;
use App\Support\TeacherScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Homework a teacher sets for their own sections, then checks and comments on. Students and parents see the result. */
class HomeworkController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->actor($request);

        $rows = $this->scoped($user)->join('student_profiles', 'student_profiles.user_id', '=', 'homework_items.user_id')
            ->whereNotNull('homework_items.batch')
            ->groupBy('homework_items.batch', 'homework_items.title', 'homework_items.subject', 'homework_items.teacher', 'homework_items.due_on', 'student_profiles.class_name', 'student_profiles.section')
            ->orderByDesc('homework_items.due_on')->limit(100)
            ->selectRaw("homework_items.batch, homework_items.title, homework_items.subject, homework_items.teacher, homework_items.due_on, student_profiles.class_name, student_profiles.section,
                count(*) as total, sum(case when homework_items.status = 'submitted' then 1 else 0 end) as waiting, sum(case when homework_items.status = 'marked' then 1 else 0 end) as marked")
            ->get()->map(fn ($r) => (array) $r)->all();

        return Inertia::render('office/homework', [
            'navigation' => OfficeMenu::for($user->role),
            'roleLabel' => Permissions::label($user->role),
            'rows' => $rows,
            'options' => $this->options($user),
            'canAssign' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->actor($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string'],
            'section' => ['required', 'in:A,B'],
            'subject_id' => ['required', 'integer'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'instructions' => ['nullable', 'string', 'max:3000'],
        ]);

        $subject = collect($this->options($user))->firstWhere(fn ($o) => $o['class_name'] === $data['class_name'] && $o['section'] === $data['section']);
        $allowed = $subject ? collect($subject['subjects'])->firstWhere('id', (int) $data['subject_id']) : null;
        abort_if($allowed === null, 403, 'You can only set homework for your own class, section and subject.');

        $students = DB::table('student_profiles')->where('class_name', $data['class_name'])->where('section', $data['section'])->where('status', 'active')->pluck('user_id');
        if ($students->isEmpty()) {
            return back()->withErrors(['section' => 'This section has no active students.']);
        }

        $batch = (string) Str::uuid();
        $now = now();
        DB::table('homework_items')->insert($students->map(fn ($id) => [
            'user_id' => $id, 'title' => $data['title'], 'subject' => $allowed['name'], 'teacher' => $user->name, 'due_on' => $data['due_on'], 'status' => 'todo',
            'instructions' => $data['instructions'] ?? null, 'batch' => $batch, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
        Audit::log('created', 'Homework', "{$data['title']} · {$data['class_name']} {$data['section']}");

        return redirect("/app/homework/{$batch}")->with('success', 'Homework given.');
    }

    public function show(Request $request, string $batch): Response
    {
        $user = $this->actor($request);
        $items = $this->scoped($user)->join('users', 'users.id', '=', 'homework_items.user_id')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where('homework_items.batch', $batch)
            ->orderByRaw('cast(student_profiles.roll_no as integer)')
            ->get(['homework_items.id', 'homework_items.title', 'homework_items.subject', 'homework_items.due_on', 'homework_items.instructions', 'homework_items.status', 'homework_items.note',
                'homework_items.remarks', 'homework_items.grade', 'homework_items.submitted_at', 'users.name', 'users.photo', 'student_profiles.roll_no', 'student_profiles.class_name', 'student_profiles.section']);
        abort_if($items->isEmpty(), 404);
        $first = $items->first();

        return Inertia::render('office/homework-check', [
            'navigation' => OfficeMenu::for($user->role),
            'roleLabel' => Permissions::label($user->role),
            'batch' => $batch,
            'assignment' => ['title' => $first->title, 'subject' => $first->subject, 'due_on' => $first->due_on, 'instructions' => $first->instructions, 'class_name' => $first->class_name, 'section' => $first->section],
            'items' => $items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'photo' => Photos::url($i->photo), 'roll_no' => $i->roll_no, 'status' => $i->status, 'note' => $i->note, 'remarks' => $i->remarks,
                'grade' => $i->grade, 'submitted_at' => $i->submitted_at,
            ])->values(),
        ]);
    }

    public function mark(Request $request, int $id): RedirectResponse
    {
        $user = $this->actor($request);
        $item = $this->scoped($user)->where('homework_items.id', $id)->first(['homework_items.id']);
        abort_if($item === null, 404);

        $data = $request->validate([
            'grade' => ['nullable', 'string', 'max:20'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'redo' => ['boolean'],
        ]);

        DB::table('homework_items')->where('id', $id)->update($request->boolean('redo')
            ? ['status' => 'todo', 'remarks' => $data['remarks'] ?? 'Please redo and submit again.', 'grade' => null, 'marked_at' => null, 'updated_at' => now()]
            : ['status' => 'marked', 'grade' => $data['grade'] ?? null, 'remarks' => $data['remarks'] ?? null, 'marked_at' => now(), 'updated_at' => now()]);

        return back();
    }

    public function destroy(Request $request, string $batch): RedirectResponse
    {
        $user = $this->actor($request);
        $this->scoped($user)->where('homework_items.batch', $batch)->delete();

        return redirect('/app/homework');
    }

    private function actor(Request $request)
    {
        Permissions::office($request);

        return $request->user();
    }

    /** Homework rows a teacher may touch: only the ones they set. Admin and office roles see all. */
    private function scoped($user)
    {
        return DB::table('homework_items')->when(TeacherScope::applies($user), fn ($q) => $q->where('homework_items.teacher', $user->name));
    }

    /** @return list<array{class_name: string, section: string, subjects: list<array{id: int, name: string}>}> */
    private function options($user): array
    {
        $out = [];
        $teacher = TeacherScope::applies($user);
        $pairs = $teacher ? TeacherScope::sections($user) : DB::table('academic_sections')->orderBy('class_name')->orderBy('name')->get(['class_name', 'name'])->map(fn ($s) => $s->class_name.'|'.$s->name)->all();
        usort($pairs, fn ($a, $b) => strnatcmp(is_array($a) ? $a['class'].$a['section'] : $a, is_array($b) ? $b['class'].$b['section'] : $b));
        foreach ($pairs as $pair) {
            [$class, $section] = is_array($pair) ? [$pair['class'], $pair['section']] : explode('|', $pair);
            $subjects = $teacher ? TeacherScope::subjects($user, $class, $section)
                : DB::table('academic_subjects')->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])->all();
            if ($subjects !== []) {
                $out[] = ['class_name' => $class, 'section' => $section, 'subjects' => $subjects];
            }
        }

        return $out;
    }
}
