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
        $class = (string) $request->query('class', 'Class 1');
        $section = (string) $request->query('section', '');

        $students = DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('student_profiles.status', 'active')
            ->where('student_profiles.class_name', $class)
            ->when($section !== '', fn ($q) => $q->where('student_profiles.section', $section))
            ->orderBy('student_profiles.section')
            ->orderByRaw('cast(student_profiles.roll_no as integer)')
            ->get(['student_profiles.user_id as id', 'users.name', 'student_profiles.section', 'student_profiles.roll_no', 'student_profiles.admission_no']);

        return Inertia::render('office/promotion', [
            ...$this->shared(),
            'classes' => $this->classes(),
            'filters' => ['class' => $class, 'section' => $section],
            'students' => $students,
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

    /** @return list<string> */
    private function classes(): array
    {
        return array_map(fn ($n) => "Class {$n}", range(1, 10));
    }

    private function shared(): array
    {
        return ['navigation' => OfficeMenu::for('admin'), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
