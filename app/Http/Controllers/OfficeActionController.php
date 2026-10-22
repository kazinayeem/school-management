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
            'expires_on' => $data['expires_on'] ?: null,
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

        abort_unless($request->user()->role === 'admin' || $data['section'] === 'Class 8-A', 403);

        foreach ($data['marks'] as $mark) {
            DB::table('attendance_days')->updateOrInsert(
                ['user_id' => $mark['user_id'], 'date' => $data['date']],
                ['status' => $mark['status']],
            );
        }

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
        ]);

        $username = Str::slug($data['name'], '.').'.'.random_int(100, 999);
        $roll = (int) StudentProfile::query()
            ->where('class_name', $data['class_name'])
            ->where('section', $data['section'])
            ->selectRaw('max(cast(roll_no as integer)) as roll')
            ->value('roll') + 1;
        $last = StudentProfile::query()->where('admission_no', 'like', '2026-%')->max('admission_no');
        $admission = '2026-'.str_pad((string) (((int) substr((string) $last, 5)) + 1), 5, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $username.'@borno.test',
            'phone' => $data['guardian_mobile'] ?? null,
            'role' => 'student',
            'password' => 'Demo@1234',
        ]);

        $user->profile()->create([
            'admission_no' => $admission,
            'class_name' => $data['class_name'],
            'section' => $data['section'],
            'roll_no' => (string) $roll,
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'shift' => 'Morning',
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_mobile' => $data['guardian_mobile'] ?? null,
            'status' => 'active',
            'session' => '2026',
        ]);

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

        $amount = min($data['amount'], max(0, (int) $row->payable - (int) $row->paid));
        abort_if($amount < 1, 422);
        $paid = (int) $row->paid + $amount;

        DB::table('fee_invoices')->where('id', $invoice)->update([
            'paid' => $paid,
            'status' => $paid >= (int) $row->payable ? 'paid' : 'partial',
        ]);
        DB::table('fee_payments')->insert([
            'user_id' => $row->user_id,
            'receipt_no' => 'RCP-'.now()->format('ymdHis'),
            'paid_on' => now()->toDateString(),
            'method' => 'Cash',
            'amount' => $amount,
            'late_fee' => 0,
            'status' => 'posted',
        ]);

        $profile = StudentProfile::query()->where('user_id', $row->user_id)->first();
        if ($profile !== null) {
            $profile->update([
                'dues_total' => max(0, (int) $profile->dues_total - $amount),
                'dues_count' => $paid >= (int) $row->payable ? max(0, (int) $profile->dues_count - 1) : $profile->dues_count,
            ]);
        }

        return redirect('/app/fees');
    }

    private function guard(Request $request, string $path): void
    {
        $role = $request->user()->role;
        abort_unless(in_array($role, ['admin', 'staff'], true), 403);
        abort_unless(OfficeMenu::allows($role, $path), 403);
    }
}
