<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Grading;
use App\Support\OfficeMenu;
use App\Support\Permissions;
use App\Support\Photos;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentFileController extends Controller
{
    public const KINDS = ['violation', 'commendation', 'health', 'other'];

    /** Everything about one student on one page. */
    public function show(Request $request, int $id): Response
    {
        $this->authorizeStudent($request, $id);
        $data = $this->assemble($id);
        $role = $request->user()->role;

        return Inertia::render('office/student-file', [
            'navigation' => OfficeMenu::for($role),
            'roleLabel' => Permissions::label($role),
            ...$data,
            'can' => [
                'files' => $role === 'admin',
                'edit' => Permissions::canRole($role, 'students', 'edit'),
                'delete' => Permissions::canRole($role, 'students', 'delete'),
            ],
        ]);
    }

    public function document(Request $request, int $id, string $mode): Response
    {
        $this->authorizeStudent($request, $id);
        abort_unless(in_array($mode, ['transcript', 'file'], true), 404);

        return Inertia::render('office/student-document', [
            ...$this->assemble($id),
            'mode' => $mode,
            'school' => ['name' => SiteSettings::get('school_name', config('app.name')), 'address' => SiteSettings::get('address', ''), 'phone' => SiteSettings::get('phone', '')],
        ]);
    }

    public function export(Request $request, int $id): StreamedResponse
    {
        $this->authorizeStudent($request, $id);
        $data = $this->assemble($id);
        $name = str($data['student']['name'])->slug();

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Type', 'Title', 'Detail']);
            foreach ($data['timeline'] as $e) {
                fputcsv($out, [$e['date'], $e['type'], $e['title'], $e['detail']]);
            }
            fclose($out);
        }, "student-{$name}-activity.csv", ['Content-Type' => 'text/csv']);
    }

    public function storeNote(Request $request, int $id): RedirectResponse
    {
        $this->authorizeStudent($request, $id);
        abort_unless(DB::table('users')->where('id', $id)->where('role', 'student')->exists(), 404);
        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'title' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:3000'],
            'action_taken' => ['nullable', 'string', 'max:1000'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'visible_to_parent' => ['boolean'],
        ]);

        DB::table('student_notes')->insert([
            'user_id' => $id, 'kind' => $data['kind'], 'severity' => $data['kind'] === 'violation' ? ($data['severity'] ?? 'low') : ($data['severity'] ?? null),
            'title' => $data['title'], 'details' => $data['details'] ?? null, 'action_taken' => $data['action_taken'] ?? null, 'occurred_on' => $data['occurred_on'],
            'visible_to_parent' => (bool) ($data['visible_to_parent'] ?? false), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Audit::log('created', 'Student note', ucfirst($data['kind']).': '.$data['title']);

        return back()->with('status', 'Note added.');
    }

    public function destroyNote(Request $request, int $id, int $note): RedirectResponse
    {
        $this->authorizeStudent($request, $id);
        abort_if(DB::table('student_notes')->where('id', $note)->where('user_id', $id)->delete() === 0, 404);
        Audit::log('deleted', 'Student note', "#{$note}");

        return back()->with('status', 'Entry deleted.');
    }

    private function authorizeStudent(Request $request, int $id): void
    {
        abort_unless(\App\Support\TeacherScope::canSee($request->user(), $id), 403, 'You can only open students from the classes and sections you teach.');
    }

    /** @return array<string, mixed> */
    public function assemble(int $id): array
    {
        $user = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('users.id', $id)->where('users.role', 'student')
            ->first(['users.id', 'users.name', 'users.username', 'users.email', 'users.phone', 'users.photo', 'users.is_active', 'student_profiles.*']);
        abort_if($user === null, 404);

        $student = [
            'id' => $id, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'phone' => $user->phone, 'photo_url' => Photos::url($user->photo),
            'admission_no' => $user->admission_no, 'class_name' => $user->class_name, 'section' => $user->section, 'roll_no' => $user->roll_no, 'session' => $user->session,
            'gender' => $user->gender, 'date_of_birth' => $user->date_of_birth, 'religion' => $user->religion, 'address' => $user->address, 'status' => $user->status,
            'guardian_name' => $user->guardian_name, 'guardian_mobile' => $user->guardian_mobile, 'shift' => $user->shift, 'active' => (bool) $user->is_active,
        ];

        $parents = DB::table('guardian_student')->join('users', 'users.id', '=', 'guardian_student.guardian_id')->where('student_id', $id)->get(['users.id', 'users.name', 'users.phone', 'users.email']);

        $attendance = $this->attendance($id);
        $results = $this->results($id, $user);
        $fees = $this->fees($id);
        $loans = DB::table('library_loans')->where('user_id', $id)->orderByDesc('issued_on')->get(['id', 'title', 'author', 'issued_on', 'due_on', 'returned_on', 'fine', 'status']);
        $transport = DB::table('bus_assignments')->where('user_id', $id)->first(['route', 'stop', 'vehicle', 'pickup_time', 'drop_time', 'fare', 'driver', 'driver_phone']);
        $certificates = DB::table('certificates')->where('user_id', $id)->orderByDesc('id')->get(['id', 'number', 'title', 'issued_on']);
        $leave = DB::table('leave_requests')->where('user_id', $id)->orderByDesc('start_date')->get(['id', 'leave_type', 'start_date', 'end_date', 'days', 'status', 'reason']);
        $documents = DB::table('student_documents')->where('user_id', $id)->orderByDesc('issue_date')->get(['id', 'type', 'serial_no', 'issue_date']);
        $notes = DB::table('student_notes')->leftJoin('users', 'users.id', '=', 'student_notes.created_by')->where('student_notes.user_id', $id)
            ->orderByDesc('occurred_on')->orderByDesc('student_notes.id')->get(['student_notes.*', 'users.name as author']);

        return [
            'student' => $student,
            'parents' => $parents,
            'attendance' => $attendance,
            'results' => $results,
            'fees' => $fees,
            'loans' => $loans,
            'transport' => $transport,
            'certificates' => $certificates,
            'leave' => $leave,
            'documents' => $documents,
            'files' => \App\Support\UserFiles::listFor($id),
            'fileKinds' => \App\Support\UserFiles::KINDS,
            'notes' => $notes,
            'timeline' => $this->timeline($id, $fees, $loans, $certificates, $leave, $notes, $results),
        ];
    }

    /** @return array{percent: ?int, present: int, late: int, absent: int, leave: int, days: int, recent: Collection} */
    private function attendance(int $id): array
    {
        $c = DB::table('attendance_days')->where('user_id', $id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        [$present, $late, $absent, $leave] = [(int) ($c['present'] ?? 0), (int) ($c['late'] ?? 0), (int) ($c['absent'] ?? 0), (int) ($c['leave'] ?? 0)];
        $counted = $present + $late + $absent;

        return [
            'percent' => $counted > 0 ? (int) round(($present + $late) / $counted * 100) : null,
            'present' => $present, 'late' => $late, 'absent' => $absent, 'leave' => $leave, 'days' => $counted + $leave,
            'minimum' => SiteSettings::int('attendance_min_percent', 75),
            'recent' => DB::table('attendance_days')->where('user_id', $id)->orderByDesc('date')->limit(21)->get(['date', 'status']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function results(int $id, object $profile): array
    {
        $published = DB::table('exam_publications')->distinct()->pluck('exam_id')->flip();
        $rows = DB::table('exam_results')->where('user_id', $id)->get()->filter(fn ($r) => $r->exam_id === null || isset($published[$r->exam_id]));

        return $rows->groupBy(fn ($r) => $r->exam_id ?? 'legacy-'.$r->exam)->map(function (Collection $group) use ($profile) {
            $first = $group->first();
            $subjects = $group->sortBy('subject')->map(fn ($r) => [
                'subject' => $r->subject, 'marks' => (int) $r->marks, 'full_marks' => (int) $r->full_marks, 'grade' => $r->letter_grade, 'point' => $r->grade_point === null ? null : (float) $r->grade_point,
                'failed' => (bool) $r->failed, 'absent' => (bool) $r->absent,
            ])->values();
            $withPoints = $subjects->every(fn ($s) => $s['point'] !== null);
            $overall = $withPoints ? Grading::overall($subjects->map(fn ($s) => ['point' => $s['point'], 'failed' => $s['failed']])) : null;
            $isLatest = $profile->exam_name === $first->exam;

            return [
                'exam' => $first->exam, 'subjects' => $subjects, 'total' => (int) $subjects->sum('marks'), 'full_total' => (int) $subjects->sum('full_marks'),
                'gpa' => $overall['gpa'] ?? ($isLatest && $profile->gpa !== null ? (float) $profile->gpa : null), 'grade' => $overall['grade'] ?? null,
                'failed' => $overall['failed'] ?? $subjects->contains('failed', true),
                'class_position' => $isLatest ? $profile->class_position : null, 'section_position' => $isLatest ? $profile->section_position : null,
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function fees(int $id): array
    {
        $invoices = DB::table('fee_invoices')->where('user_id', $id)->orderByDesc('due_on')->get(['id', 'invoice_no', 'label', 'due_on', 'payable', 'paid', 'status', 'late_fee']);
        $payments = DB::table('fee_payments')->where('user_id', $id)->orderByDesc('paid_on')->orderByDesc('id')->get(['id', 'receipt_no', 'paid_on', 'method', 'amount', 'late_fee']);

        return [
            'invoices' => $invoices, 'payments' => $payments,
            'billed' => (int) $invoices->sum('payable'), 'paid' => (int) $invoices->sum('paid'), 'due' => (int) $invoices->sum(fn ($i) => max(0, $i->payable - $i->paid)),
        ];
    }

    /** @return list<array{date: string, type: string, title: string, detail: string}> */
    private function timeline(int $id, array $fees, Collection $loans, Collection $certificates, Collection $leave, Collection $notes, array $results): array
    {
        $events = collect();

        foreach (DB::table('attendance_days')->where('user_id', $id)->whereIn('status', ['absent', 'late'])->orderByDesc('date')->limit(30)->get() as $a) {
            $events->push(['date' => $a->date, 'type' => 'attendance', 'title' => $a->status === 'absent' ? 'Absent' : 'Late', 'detail' => '']);
        }
        foreach ($fees['payments'] as $p) {
            $events->push(['date' => $p->paid_on, 'type' => 'fee', 'title' => 'Fee paid', 'detail' => number_format((int) $p->amount).' · '.$p->method.' · '.$p->receipt_no]);
        }
        foreach ($loans as $l) {
            $events->push(['date' => $l->issued_on, 'type' => 'library', 'title' => 'Borrowed a book', 'detail' => $l->title]);
            if ($l->returned_on) {
                $events->push(['date' => $l->returned_on, 'type' => 'library', 'title' => 'Returned a book', 'detail' => $l->title.((int) $l->fine > 0 ? ' · fine '.$l->fine : '')]);
            }
        }
        foreach ($certificates as $c) {
            $events->push(['date' => $c->issued_on, 'type' => 'certificate', 'title' => 'Certificate issued', 'detail' => $c->title.' · '.$c->number]);
        }
        foreach ($leave as $l) {
            $events->push(['date' => $l->start_date, 'type' => 'leave', 'title' => 'Leave '.$l->status, 'detail' => $l->leave_type.' · '.$l->days.' day(s)']);
        }
        foreach ($notes as $n) {
            $events->push(['date' => $n->occurred_on, 'type' => 'note', 'title' => ucfirst($n->kind), 'detail' => $n->title]);
        }
        foreach ($results as $r) {
            $events->push(['date' => '', 'type' => 'result', 'title' => 'Result', 'detail' => $r['exam'].($r['gpa'] !== null ? ' · GPA '.number_format($r['gpa'], 2) : '')]);
        }

        return $events->filter(fn ($e) => $e['date'] !== '')->sortByDesc('date')->values()->take(60)->all();
    }
}
