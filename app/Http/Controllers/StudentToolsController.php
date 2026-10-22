<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Support\OfficeMenu;
use App\Support\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentToolsController extends Controller
{
    private const COLUMNS = ['name', 'class_name', 'section', 'gender', 'date_of_birth', 'guardian_name', 'guardian_mobile'];

    private const MAX_ROWS = 500;

    public function importForm(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/import', [
            ...$this->shared(),
            'columns' => self::COLUMNS,
            'result' => $request->session()->get('import'),
        ]);
    }

    public function importTemplate(Request $request): StreamedResponse
    {
        $this->admin($request);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::COLUMNS);
            fputcsv($out, ['Rahim Uddin', 'Class 6', 'A', 'Male', '2014-03-21', 'Karim Uddin', '01700000000']);
            fclose($out);
        }, 'students-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->admin($request);
        $request->validate([
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
            'pasted' => ['nullable', 'string', 'max:200000'],
        ]);

        $text = $request->hasFile('file') ? file_get_contents($request->file('file')->getRealPath()) : (string) $request->input('pasted', '');
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        if (trim($text) === '') {
            return back()->withErrors(['file' => 'Choose a CSV file or paste CSV rows.']);
        }

        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $text), fn ($l) => trim($l) !== ''));
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));

        $missing = array_values(array_diff(['name', 'class_name', 'section', 'gender'], $header));
        if ($missing) {
            return back()->withErrors(['file' => 'Missing columns: '.implode(', ', $missing)]);
        }
        if (count($lines) > self::MAX_ROWS) {
            return back()->withErrors(['file' => 'Import at most '.self::MAX_ROWS.' students at a time.']);
        }

        $created = 0;
        $errors = [];

        DB::transaction(function () use ($lines, $header, &$created, &$errors) {
            foreach ($lines as $i => $line) {
                $cells = str_getcsv($line);
                $row = [];
                foreach ($header as $index => $column) {
                    $row[$column] = trim($cells[$index] ?? '');
                }

                $row['class_name'] = preg_match('/^\d{1,2}$/', $row['class_name']) ? 'Class '.$row['class_name'] : ucfirst(strtolower($row['class_name']));
                $row['section'] = strtoupper($row['section']);
                $row['gender'] = ucfirst(strtolower($row['gender']));

                $validator = Validator::make($row, [
                    'name' => ['required', 'string', 'max:255'],
                    'class_name' => ['required', 'regex:/^Class (10|[1-9])$/'],
                    'section' => ['required', Rule::in(['A', 'B'])],
                    'gender' => ['required', Rule::in(['Male', 'Female'])],
                    'date_of_birth' => ['nullable', 'date'],
                    'guardian_name' => ['nullable', 'string', 'max:255'],
                    'guardian_mobile' => ['nullable', 'string', 'max:30'],
                ]);

                if ($validator->fails()) {
                    $errors[] = ['row' => $i + 2, 'name' => $row['name'] ?? '', 'message' => $validator->errors()->first()];

                    continue;
                }

                $data = $validator->validated();
                $data['date_of_birth'] = ($data['date_of_birth'] ?? '') === '' ? null : $data['date_of_birth'];
                StudentEnrollment::enroll($data);
                $created++;
            }
        });

        return back()->with('import', ['created' => $created, 'failed' => count($errors), 'errors' => array_slice($errors, 0, 50)]);
    }

    public function promotionForm(Request $request): Response
    {
        $this->admin($request);
        $class = in_array($request->query('class'), $this->classes(), true) ? (string) $request->query('class') : 'Class 1';
        $section = in_array($request->query('section'), ['A', 'B'], true) ? (string) $request->query('section') : '';
        $q = trim((string) $request->query('q', ''));
        $gender = in_array($request->query('gender'), ['Male', 'Female'], true) ? (string) $request->query('gender') : '';
        $result = in_array($request->query('result'), ['passed', 'failed', 'none'], true) ? (string) $request->query('result') : '';
        $dues = in_array($request->query('dues'), ['clear', 'owing'], true) ? (string) $request->query('dues') : '';
        $minimum = (int) \App\Support\SiteSettings::int('attendance_min_percent', 75);
        $attendance = in_array($request->query('attendance'), ['low', 'ok'], true) ? (string) $request->query('attendance') : '';

        $base = fn () => DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('student_profiles.status', 'active')->where('student_profiles.class_name', $class);

        $rows = $base()
            ->when($section !== '', fn ($query) => $query->where('student_profiles.section', $section))
            ->when($gender !== '', fn ($query) => $query->where('student_profiles.gender', $gender))
            ->when($q !== '', fn ($query) => $query->where(fn ($query) => $query->where('users.name', 'like', "%{$q}%")->orWhere('student_profiles.admission_no', 'like', "%{$q}%")->orWhere('student_profiles.roll_no', $q)))
            ->when($result === 'passed', fn ($query) => $query->whereNotNull('student_profiles.exam_name')->where('student_profiles.gpa', '>', 0))
            ->when($result === 'failed', fn ($query) => $query->whereNotNull('student_profiles.exam_name')->where(fn ($query) => $query->whereNull('student_profiles.gpa')->orWhere('student_profiles.gpa', '<=', 0)))
            ->when($result === 'none', fn ($query) => $query->whereNull('student_profiles.exam_name'))
            ->when($dues === 'clear', fn ($query) => $query->where('student_profiles.dues_total', '<=', 0))
            ->when($dues === 'owing', fn ($query) => $query->where('student_profiles.dues_total', '>', 0))
            ->orderBy('student_profiles.section')->orderByRaw('cast(student_profiles.roll_no as integer)')->limit(400)
            ->get(['users.id', 'users.name', 'users.photo', 'student_profiles.section', 'student_profiles.roll_no', 'student_profiles.admission_no', 'student_profiles.gender', 'student_profiles.gpa', 'student_profiles.exam_name', 'student_profiles.dues_total']);

        $days = DB::table('attendance_days')->whereIn('user_id', $rows->pluck('id'))->selectRaw('user_id, status, count(*) as total')->groupBy('user_id', 'status')->get()->groupBy('user_id');

        $students = $rows->map(function ($r) use ($days) {
            $c = ($days[$r->id] ?? collect())->pluck('total', 'status');
            $counted = (int) ($c['present'] ?? 0) + (int) ($c['late'] ?? 0) + (int) ($c['absent'] ?? 0);

            return [
                'id' => $r->id, 'name' => $r->name, 'photo' => \App\Support\Photos::url($r->photo), 'section' => $r->section, 'roll_no' => $r->roll_no, 'admission_no' => $r->admission_no,
                'gender' => $r->gender, 'gpa' => $r->exam_name ? (float) $r->gpa : null, 'exam' => $r->exam_name, 'dues' => (int) $r->dues_total,
                'attendance' => $counted > 0 ? (int) round(((int) ($c['present'] ?? 0) + (int) ($c['late'] ?? 0)) / $counted * 100) : null,
            ];
        })->when($attendance !== '', fn ($c) => $c->filter(fn ($s) => $s['attendance'] !== null && ($attendance === 'low' ? $s['attendance'] < $minimum : $s['attendance'] >= $minimum)))->values();

        $everyone = $base()->count();

        return Inertia::render('office/promotion', [
            ...$this->shared(),
            'classes' => $this->classes(),
            'filters' => ['class' => $class, 'section' => $section, 'q' => $q, 'gender' => $gender, 'result' => $result, 'dues' => $dues, 'attendance' => $attendance],
            'students' => $students,
            'total' => $everyone,
            'minimum' => $minimum,
            'result' => $request->session()->get('promotion'),
        ]);
    }

    public function promote(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'from_class' => ['required', Rule::in($this->classes())],
            'to_class' => ['required', Rule::in([...$this->classes(), 'Passed out'])],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
        ], ['student_ids.required' => 'Select at least one student.']);

        abort_if($data['to_class'] === $data['from_class'], 422, 'Choose a different class to promote to.');

        $moved = 0;
        DB::transaction(function () use ($data, &$moved) {
            $profiles = StudentProfile::query()
                ->whereIn('user_id', $data['student_ids'])
                ->where('class_name', $data['from_class'])
                ->where('status', 'active')
                ->orderBy('section')
                ->orderByRaw('cast(roll_no as integer)')
                ->get();

            foreach ($profiles as $profile) {
                if ($data['to_class'] === 'Passed out') {
                    $profile->update(['status' => 'alumni', 'promotion_status' => 'Passed out']);
                } else {
                    $profile->update([
                        'class_name' => $data['to_class'],
                        'roll_no' => (string) StudentEnrollment::nextRoll($data['to_class'], $profile->section),
                        'promotion_status' => 'Promoted from '.$data['from_class'],
                    ]);
                }
                $moved++;
            }
        });

        return redirect('/app/students/promotion?class='.urlencode($data['from_class']))
            ->with('promotion', ['moved' => $moved, 'to' => $data['to_class']]);
    }

    public function rollNumbers(Request $request): Response
    {
        $this->admin($request);
        [$class, $section] = $this->classSection($request);

        return Inertia::render('office/roll-numbers', [
            ...$this->shared(),
            'classes' => $this->classes(),
            'filters' => ['class' => $class, 'section' => $section],
            'students' => $this->sectionStudents($class, $section),
        ]);
    }

    public function saveRollNumbers(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'class_name' => ['required', Rule::in($this->classes())],
            'section' => ['required', Rule::in(['A', 'B'])],
            'rolls' => ['required', 'array', 'min:1', 'max:500'],
            'rolls.*.user_id' => ['required', 'integer'],
            'rolls.*.roll_no' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $expected = $this->sectionStudents($data['class_name'], $data['section'])->pluck('id')->sort()->values()->all();
        $given = collect($data['rolls'])->pluck('user_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        if ($expected !== $given) {
            return back()->with('error', 'The student list has changed. Reload the page and try again.');
        }

        $duplicates = collect($data['rolls'])->groupBy('roll_no')->filter(fn ($g) => $g->count() > 1)->keys();
        if ($duplicates->isNotEmpty()) {
            return back()->withErrors(['rolls' => 'Each roll number can be used once. Repeated: '.$duplicates->implode(', ').'.']);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['rolls'] as $r) {
                DB::table('student_profiles')->where('user_id', (int) $r['user_id'])->update(['roll_no' => (string) (int) $r['roll_no']]);
            }
        });
        \App\Support\Audit::log('updated', 'Roll numbers', "{$data['class_name']} {$data['section']}");

        return back()->with('status', 'Roll numbers saved.');
    }

    public function idCards(Request $request): Response
    {
        $this->admin($request);
        $class = $this->classOrDefault((string) $request->query('class', ''));
        $section = in_array($request->query('section'), ['A', 'B'], true) ? (string) $request->query('section') : '';
        $roll = trim((string) $request->query('roll', ''));

        $students = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where('student_profiles.status', 'active')->where('student_profiles.class_name', $class)
            ->when($section !== '', fn ($q) => $q->where('student_profiles.section', $section))
            ->when($roll !== '', fn ($q) => $q->where('student_profiles.roll_no', $roll))
            ->orderBy('student_profiles.section')->orderByRaw('cast(student_profiles.roll_no as integer)')->limit(300)
            ->get(['users.id', 'users.name', 'users.photo', 'student_profiles.admission_no', 'student_profiles.roll_no', 'student_profiles.section', 'student_profiles.session', 'student_profiles.guardian_name', 'student_profiles.guardian_mobile', 'student_profiles.gender', 'student_profiles.date_of_birth', 'student_profiles.address'])
            ->map(function ($s) {
                $s->photo_url = \App\Support\Photos::url($s->photo);

                return $s;
            });

        return Inertia::render('office/id-cards', [
            ...$this->shared(),
            'classes' => $this->classes(),
            'filters' => ['class' => $class, 'section' => $section, 'roll' => $roll],
            'students' => $students,
            'school' => ['name' => \App\Support\SiteSettings::get('school_name', config('app.name')), 'phone' => \App\Support\SiteSettings::get('phone', ''), 'address' => \App\Support\SiteSettings::get('address', '')],
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function classSection(Request $request): array
    {
        return [$this->classOrDefault((string) $request->query('class', '')), in_array($request->query('section'), ['A', 'B'], true) ? (string) $request->query('section') : 'A'];
    }

    private function classOrDefault(string $class): string
    {
        return in_array($class, $this->classes(), true) ? $class : $this->classes()[0];
    }

    private function sectionStudents(string $class, string $section): \Illuminate\Support\Collection
    {
        return DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where('student_profiles.status', 'active')->where('student_profiles.class_name', $class)->where('student_profiles.section', $section)
            ->orderByRaw('cast(student_profiles.roll_no as integer)')->orderBy('users.name')
            ->get(['users.id', 'users.name', 'student_profiles.admission_no', 'student_profiles.roll_no']);
    }

    /** @return list<string> */
    private function classes(): array
    {
        return array_map(fn ($n) => "Class {$n}", range(1, 10));
    }

    private function shared(): array
    {
        return ['navigation' => OfficeMenu::for(request()->user()->role), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        \App\Support\Permissions::office($request);
    }
}
