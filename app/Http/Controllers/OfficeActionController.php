<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Models\User;
use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfficeActionController extends Controller
{
    public function storeNotice(Request $request): RedirectResponse
    {
        $this->guard($request, '/app/notices');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'published_on' => ['required', 'date'],
            'expires_on' => ['nullable', 'date'],
        ]);

        DB::table('school_notices')->insert([
            'title' => $data['title'],
            'body' => $data['body'],
            'published_on' => $data['published_on'],
            'expires_on' => ($data['expires_on'] ?? null) ?: null,
        ]);

        return redirect('/app/notices');
    }

    public function storeAttendance(Request $request): RedirectResponse
    {
        $this->guard($request, '/app/attendance');

        $data = $request->validate([
            'section' => ['required', 'string'],
            'date' => ['required', 'date'],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.user_id' => ['required', 'integer'],
            'marks.*.status' => ['required', 'in:present,absent,late,leave'],
        ]);

        abort_unless(in_array($data['section'], \App\Support\OfficeData::sectionOptions($request->user()), true), 403);

        $before = DB::table('attendance_days')->where('date', $data['date'])->whereIn('user_id', collect($data['marks'])->pluck('user_id'))->pluck('status', 'user_id');

        foreach ($data['marks'] as $mark) {
            DB::table('attendance_days')->updateOrInsert(
                ['user_id' => $mark['user_id'], 'date' => $data['date']],
                ['status' => $mark['status']],
            );
        }

        \App\Support\AttendanceAlerts::notify($request->user()->id, $data['date'], collect($data['marks'])->filter(fn ($m) => $m['status'] === 'absent' && ($before[$m['user_id']] ?? null) !== 'absent')->pluck('user_id')->all());

        return redirect('/app/attendance?section='.urlencode($data['section']).'&date='.$data['date']);
    }

    public function storeHomework(Request $request): RedirectResponse
    {
        $this->guard($request, '/app/homework');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string'],
            'section' => ['required', 'string'],
            'due_on' => ['required', 'date'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $students = User::query()
            ->where('role', 'student')
            ->whereHas('profile', fn ($query) => $query
                ->where('class_name', $data['class_name'])
                ->where('section', $data['section'])
                ->where('status', 'active'))
            ->pluck('id');

        abort_if($students->isEmpty(), 422, 'This section has no active students.');

        $now = now();
        $rows = $students->map(fn ($id) => [
            'user_id' => $id,
            'title' => $data['title'],
            'subject' => $data['subject'],
            'teacher' => $request->user()->name,
            'due_on' => $data['due_on'],
            'status' => 'todo',
            'instructions' => $data['instructions'] ?? null,
            'note' => null,
            'remarks' => null,
            'submitted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('homework_items')->insert($rows);

        return redirect('/app/homework');
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $this->guard($request, '/app/students/new');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string'],
            'section' => ['required', 'in:A,B'],
            'gender' => ['required', 'in:Male,Female'],
            'date_of_birth' => ['nullable', 'date'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_mobile' => ['nullable', 'string', 'max:30'],
            ...\App\Support\UserFiles::requestRules(),
        ]);

        $user = \App\Support\StudentEnrollment::enroll(collect($data)->except(array_keys(\App\Support\UserFiles::requestRules()))->all());
        if ($request->user()->role === 'admin') {
            \App\Support\UserFiles::saveFromRequest($request, $user);
        }

        return redirect('/app/students/'.$user->id);
    }

    public function decideLeave(Request $request, int $leave): RedirectResponse
    {
        $this->guard($request, '/app/leave');

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        DB::table('leave_requests')->where('id', $leave)->update([
            'status' => $data['status'],
            'updated_at' => now(),
        ]);

        return redirect('/app/leave');
    }

    public function collectFee(Request $request, int $invoice): RedirectResponse
    {
        $this->guard($request, '/app/fees');

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $row = DB::table('fee_invoices')->where('id', $invoice)->first();
        abort_if($row === null, 404);

        abort_if(\App\Support\FeePayments::collect($row, $data['amount'], 'Cash') === null, 422);

        return redirect('/app/fees');
    }

    private function guard(Request $request, string $path): void
    {
        $role = $request->user()->role;
        \App\Support\Permissions::office($request);
        abort_unless(OfficeMenu::allows($role, $path), 403);
    }
}
