<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Pdf;
use App\Support\Permissions;
use App\Support\PersonalCard;
use App\Support\Photos;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Real PDF downloads. Each one checks that the person may see the same data on screen. */
class PdfController extends Controller
{
    /** Any table the person is looking at (reports, lists) as a PDF. The data is what their own page already showed them. */
    public function table(Request $request)
    {
        $data = $request->validate([
            'format' => ['nullable', \Illuminate\Validation\Rule::in(['pdf', 'csv', 'xlsx', 'html'])],
            'title' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'filters' => ['nullable', 'string', 'max:500'],
            'summary' => ['nullable', 'array', 'max:12'],
            'summary.*.label' => ['required', 'string', 'max:80'],
            'summary.*.value' => ['nullable'],
            'sections' => ['required', 'array', 'max:10'],
            'sections.*.title' => ['nullable', 'string', 'max:150'],
            'sections.*.columns' => ['required', 'array', 'min:1', 'max:16'],
            'sections.*.columns.*.key' => ['required', 'string', 'max:60'],
            'sections.*.columns.*.label' => ['required', 'string', 'max:80'],
            'sections.*.columns.*.num' => ['nullable', 'boolean'],
            'sections.*.columns.*.money' => ['nullable', 'boolean'],
            'sections.*.rows' => ['present', 'array', 'max:2000'],
            'sections.*.footer' => ['nullable', 'array'],
        ]);
        $format = $data['format'] ?? 'pdf';
        if ($format === 'csv' || $format === 'xlsx') {
            return $this->tableFile($data, $format);
        }
        if ($format === 'html') {
            return response(view('pdf.table', $data + ['school' => Pdf::school()])->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
        $landscape = collect($data['sections'])->max(fn ($s) => count($s['columns'])) > 7;

        return Pdf::download('pdf.table', $data, str($data['title'])->slug().'.pdf', 'a4', $landscape ? 'landscape' : 'portrait');
    }

    /** The on-screen table as a spreadsheet. Cells that look like formulas are neutralised. */
    private function tableFile(array $data, string $format)
    {
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($v) ? "'".$v : $v;
        $name = str($data['title'])->slug().'.'.$format;
        $line = fn (array $section, array $row) => array_map(fn ($c) => $safe($row[$c['key']] ?? ''), $section['columns']);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($data, $line) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                foreach ($data['sections'] as $i => $section) {
                    $i > 0 && fputcsv($out, []);
                    ! empty($section['title']) && fputcsv($out, [$section['title']]);
                    fputcsv($out, array_column($section['columns'], 'label'));
                    foreach ($section['rows'] as $row) {
                        fputcsv($out, $line($section, $row));
                    }
                }
                fclose($out);
            }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($data, $line) {
            $writer = new \OpenSpout\Writer\XLSX\Writer;
            $writer->openToFile('php://output');
            foreach ($data['sections'] as $i => $section) {
                if ($i > 0) {
                    $sheet = $writer->addNewSheetAndMakeItCurrent();
                }
                $writer->getCurrentSheet()->setName(mb_substr((string) preg_replace('#[\\\\/?*\\[\\]:]#', ' ', ($section['title'] ?? null) ?: ($i === 0 ? $data['title'] : 'Sheet '.($i + 1))), 0, 30));
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(array_column($section['columns'], 'label'), (new \OpenSpout\Common\Entity\Style\Style)->withFontBold(true)));
                foreach ($section['rows'] as $row) {
                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($line($section, $row)));
                }
            }
            $writer->close();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function student(Request $request, int $id, string $mode)
    {
        abort_unless(in_array($mode, ['transcript', 'file'], true), 404);
        $user = $request->user();
        $own = $user->role === 'student' && $user->id === $id;
        $parent = $user->role === 'parent' && DB::table('guardian_student')->where('guardian_id', $user->id)->where('student_id', $id)->exists();
        abort_unless($own || $parent || (Permissions::isEmployee($user->role) && Permissions::canRole($user->role, 'students', 'view') && TeacherScope::canSee($user, $id)) || $user->role === 'admin', 403);
        // A family sees the transcript only; the full file holds staff notes.
        abort_if(($own || $parent) && $mode === 'file', 403);

        $data = json_decode((string) json_encode(app(StudentFileController::class)->assemble($id)), true);
        $student = User::findOrFail($id);

        return Pdf::download('pdf.transcript', $data + [
            'mode' => $mode, 'photo' => Pdf::photo($student->photo), 'barcode' => Pdf::barcode((string) $data['student']['admission_no']),
        ], "{$mode}-".str($student->name)->slug().'.pdf');
    }

    public function certificate(Request $request, int $id)
    {
        $c = DB::table('certificates')->join('users', 'users.id', '=', 'certificates.user_id')->where('certificates.id', $id)->first(['certificates.*', 'users.photo']);
        abort_if($c === null, 404);
        $user = $request->user();
        $family = ($user->role === 'student' && $user->id === (int) $c->user_id) || ($user->role === 'parent' && DB::table('guardian_student')->where('guardian_id', $user->id)->where('student_id', $c->user_id)->exists());
        if (! $family) {
            Permissions::office($request);
        }

        return Pdf::download('pdf.certificate', ['certificate' => $c, 'photo' => $c->show_photo ? Pdf::photo($c->photo) : null, 'barcode' => Pdf::barcode($c->number)], $c->number.'.pdf', 'a4', 'landscape');
    }

    /** One person's own card: ID card for students and staff, a visiting pass for parents. */
    public function myCard(Request $request)
    {
        $user = $request->user();

        return Pdf::download('pdf.cards', ['heading' => $user->role === 'parent' ? 'Visiting pass' : 'ID card', 'cards' => [$this->cardRow($user)], 'backs' => true], 'my-'.($user->role === 'parent' ? 'visiting-pass' : 'id-card').'.pdf');
    }

    public function idCards(Request $request)
    {
        Permissions::office($request);
        $class = (string) $request->query('class', '');
        $section = in_array($request->query('section'), ['A', 'B'], true) ? (string) $request->query('section') : '';
        $roll = trim((string) $request->query('roll', ''));

        $ids = DB::table('student_profiles')->where('status', 'active')->when($class !== '', fn ($q) => $q->where('class_name', $class))
            ->when($section !== '', fn ($q) => $q->where('section', $section))->when($roll !== '', fn ($q) => $q->where('roll_no', $roll))
            ->orderBy('class_name')->orderBy('section')->orderByRaw('cast(roll_no as integer)')->limit(120)->pluck('user_id');
        abort_if($ids->isEmpty(), 404);
        $cards = User::whereIn('id', $ids)->get()->sortBy(fn ($u) => $ids->search($u->id))->map(fn ($u) => $this->cardRow($u))->values()->all();

        return Pdf::download('pdf.cards', ['heading' => 'Student ID cards', 'cards' => $cards, 'backs' => $request->boolean('back', true)], 'id-cards.pdf');
    }

    /** A student's or parent's own admit card. Held back when dues are above the limit the school set. */
    public function myAdmit(Request $request, int $exam)
    {
        $user = $request->user();
        if ($user->role === 'parent') {
            $student = $user->students()->with('profile')->findOrFail((int) $request->query('child'));
        } else {
            abort_unless($user->role === 'student', 403);
            $student = $user;
        }
        $profile = $student->profile;
        abort_if($profile === null, 404);
        $examRow = DB::table('exams')->find($exam);
        $schedule = $examRow ? \App\Support\AdmitCards::schedule($exam, (string) $profile->class_name) : [];
        abort_if($schedule === [], 404);
        abort_unless(\App\Support\AdmitCards::status($student->id, $exam)['allowed'], 403, 'This admit card is on hold because of unpaid fees. Please pay the dues or contact the school office.');

        $students = [['name' => $student->name, 'photo' => Pdf::photo($student->photo), 'roll_no' => $profile->roll_no, 'section' => $profile->section, 'admission_no' => $profile->admission_no, 'barcode' => Pdf::barcode((string) $profile->admission_no)]];

        return Pdf::download('pdf.admit', ['exam' => $examRow, 'class' => $profile->class_name, 'schedule' => $schedule, 'students' => $students], 'admit-card.pdf');
    }

    public function admitCards(Request $request)
    {
        Permissions::office($request);
        $exam = DB::table('exams')->find((int) $request->query('exam', 0)) ?? DB::table('exams')->orderByDesc('starts_on')->first();
        abort_if($exam === null, 404);
        $class = (string) $request->query('class', 'Class 1');
        $section = in_array($request->query('section'), ['A', 'B'], true) ? (string) $request->query('section') : null;
        $roll = trim((string) $request->query('roll', ''));

        $schedule = DB::table('exam_subjects')->join('academic_subjects', 'academic_subjects.id', '=', 'exam_subjects.subject_id')->where('exam_subjects.exam_id', $exam->id)
            ->where('exam_subjects.class_name', $class)->orderBy('exam_date')->orderBy('starts_at')
            ->get(['academic_subjects.name as subject', 'exam_subjects.exam_date', 'exam_subjects.starts_at', 'exam_subjects.room'])
            ->map(fn ($r) => ['subject' => $r->subject, 'date' => $r->exam_date, 'time' => substr((string) $r->starts_at, 0, 5), 'room' => $r->room])->all();

        $students = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('student_profiles.status', 'active')->where('student_profiles.class_name', $class)
            ->when($section, fn ($q, $s) => $q->where('student_profiles.section', $s))->when($roll !== '', fn ($q) => $q->where('student_profiles.roll_no', $roll))
            ->orderBy('student_profiles.section')->orderByRaw('cast(student_profiles.roll_no as integer)')->limit(120)
            ->get(['users.name', 'users.photo', 'student_profiles.roll_no', 'student_profiles.section', 'student_profiles.admission_no'])
            ->map(fn ($s) => ['name' => $s->name, 'photo' => Pdf::photo($s->photo), 'roll_no' => $s->roll_no, 'section' => $s->section, 'admission_no' => $s->admission_no, 'barcode' => Pdf::barcode((string) $s->admission_no)])->all();
        abort_if($students === [], 404);

        return Pdf::download('pdf.admit', compact('exam', 'class', 'schedule', 'students'), 'admit-cards.pdf');
    }

    public function payslip(Request $request, int $id, ?int $slip = null)
    {
        if ($slip !== null) {
            abort_unless($request->user()->role === 'admin', 403);
            $user = \App\Models\User::whereIn('role', ['teacher', 'staff'])->findOrFail($id);
            $id = $slip;
        } else {
            $user = $request->user();
            abort_unless(in_array($user->role, ['teacher', 'staff'], true), 403);
        }
        $slip = DB::table('payslips')->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.run_id')->where('payslips.id', $id)->where('payslips.user_id', $user->id)->where('payroll_runs.status', 'paid')
            ->first(['payroll_runs.month', 'payroll_runs.paid_on', 'payslips.basic', 'payslips.earnings', 'payslips.deductions', 'payslips.net']);
        abort_if($slip === null, 404);
        $profile = DB::table('staff_profiles')->where('user_id', $user->id)->first();

        return Pdf::download('pdf.payslip', ['slip' => $slip, 'currency' => \App\Support\SiteSettings::get('currency', 'Tk'), 'me' => ['name' => $user->name, 'employee_no' => $profile->employee_no ?? '', 'designation' => $profile->designation ?? '']], "payslip-{$slip->month}.pdf");
    }

    public function receipt(Request $request, string $receipt)
    {
        $payment = DB::table('fee_payments')->where('receipt_no', $receipt)->first();
        abort_if($payment === null, 404);
        $user = $request->user();
        $allowed = $user->id === (int) $payment->user_id
            || ($user->role === 'parent' && DB::table('guardian_student')->where('guardian_id', $user->id)->where('student_id', $payment->user_id)->exists())
            || $user->role === 'admin'
            || (Permissions::isEmployee($user->role) && Permissions::canRole($user->role, 'fees', 'view'));
        abort_unless($allowed, 404);

        $student = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('users.id', $payment->user_id)->first(['users.name', 'student_profiles.*']);

        return Pdf::download('pdf.receipt', compact('payment', 'student'), "receipt-{$receipt}.pdf");
    }

    /** @return array<string, mixed> */
    private function cardRow(User $user): array
    {
        $c = PersonalCard::for($user);

        return $c + ['photo_data' => Pdf::photo($user->photo), 'barcode' => Pdf::barcode((string) $c['number'], 24)];
    }
}
