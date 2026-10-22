<?php

namespace App\Http\Controllers;

use App\Support\OfficeMenu;
use App\Support\Permissions;
use App\Support\Photos;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** A teacher's or staff member's own HR record: profile, attendance, leave and pay. Nobody else's. */
class MyHrController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->record($this->employee($request), '/app/me', false);
    }

    /** An administrator opening the same record for any teacher or staff member. */
    public function show(Request $request, int $id): Response
    {
        return $this->record($this->subject($request, $id), "/app/staff/{$id}/hr", true);
    }

    private function record($user, string $base, bool $readOnly): Response
    {
        $profile = DB::table('staff_profiles')->where('user_id', $user->id)->first();
        $year = today()->year;

        $attendance = DB::table('staff_attendance')->where('user_id', $user->id)->where('date', '>=', today()->subDays(60)->toDateString())->orderByDesc('date')->get(['date', 'status', 'note']);
        $month = $attendance->filter(fn ($a) => str_starts_with($a->date, today()->format('Y-m')))->countBy('status');

        $used = DB::table('leave_requests')->where('user_id', $user->id)->where('status', 'approved')->whereYear('start_date', $year)->selectRaw('leave_type, sum(days) as days')->groupBy('leave_type')->pluck('days', 'leave_type');
        $types = DB::table('leave_types')->where('active', true)->orderBy('name')->get(['name', 'days_per_year', 'paid'])->map(fn ($t) => [
            'name' => $t->name, 'allowed' => (int) $t->days_per_year, 'used' => (int) ($used[$t->name] ?? 0), 'balance' => max(0, (int) $t->days_per_year - (int) ($used[$t->name] ?? 0)), 'paid' => (bool) $t->paid,
        ])->all();

        $structure = DB::table('salary_structures')->where('user_id', $user->id)->first();
        $components = $structure ? DB::table('salary_structure_items')->join('payroll_components', 'payroll_components.id', '=', 'salary_structure_items.component_id')
            ->where('salary_structure_items.structure_id', $structure->id)->get(['payroll_components.name', 'payroll_components.kind', 'payroll_components.calc', 'salary_structure_items.value'])->map(fn ($c) => (array) $c)->all() : [];

        $payslips = DB::table('payslips')->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.run_id')->where('payslips.user_id', $user->id)->where('payroll_runs.status', 'paid')
            ->orderByDesc('payroll_runs.month')->get(['payslips.id', 'payroll_runs.month', 'payroll_runs.paid_on', 'payslips.basic', 'payslips.earnings', 'payslips.deductions', 'payslips.net'])->map(fn ($p) => (array) $p)->all();

        return Inertia::render('office/my-hr', [
            'navigation' => OfficeMenu::for(request()->user()->role),
            'roleLabel' => Permissions::label(request()->user()->role),
            'base' => $base,
            'readOnly' => $readOnly,
            'userId' => $user->id,
            'files' => \App\Support\UserFiles::listFor($user->id),
            'me' => [
                'name' => $user->name, 'photo' => Photos::url($user->photo), 'email' => $user->email, 'phone' => $user->phone, 'role' => $user->role,
                'employee_no' => $profile->employee_no ?? null, 'designation' => $profile->designation ?? null, 'department' => $profile->department ?? null,
                'joined_on' => $profile->joined_on ?? null, 'address' => $profile->address ?? null,
            ],
            'attendance' => ['rows' => $attendance->map(fn ($a) => (array) $a)->all(), 'month' => $month->all()],
            'leave' => [
                'types' => $types,
                'requests' => DB::table('leave_requests')->where('user_id', $user->id)->orderByDesc('start_date')->limit(20)->get(['id', 'leave_type', 'start_date', 'end_date', 'days', 'reason', 'status'])->map(fn ($l) => (array) $l)->all(),
            ],
            'pay' => ['basic' => (int) ($structure->basic ?? 0), 'components' => $components, 'payslips' => $payslips, 'currency' => SiteSettings::get('currency', 'Tk')],
        ]);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $user = $this->employee($request);
        $data = $request->validate([
            'leave_type' => ['required', 'exists:leave_types,name'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        DB::table('leave_requests')->insert($data + ['user_id' => $user->id, 'days' => $days, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Leave requested.');
    }

    public function payslip(Request $request, int $id, ?int $slip = null): Response
    {
        $base = $slip === null ? '/app/me' : "/app/staff/{$id}/hr";
        $user = $slip === null ? $this->employee($request) : $this->subject($request, $id);
        $id = $slip ?? $id;
        $slip = DB::table('payslips')->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.run_id')->where('payslips.id', $id)->where('payslips.user_id', $user->id)->where('payroll_runs.status', 'paid')
            ->first(['payslips.id', 'payroll_runs.month', 'payroll_runs.paid_on', 'payslips.basic', 'payslips.earnings', 'payslips.deductions', 'payslips.net']);
        abort_if($slip === null, 404);
        $profile = DB::table('staff_profiles')->where('user_id', $user->id)->first();

        return Inertia::render('office/my-payslip', [
            'base' => $base,
            'slip' => (array) $slip,
            'me' => ['name' => $user->name, 'employee_no' => $profile->employee_no ?? null, 'designation' => $profile->designation ?? null, 'department' => $profile->department ?? null],
            'school' => ['name' => SiteSettings::get('school_name', config('app.name')), 'currency' => SiteSettings::get('currency', 'Tk')],
        ]);
    }

    private function subject(Request $request, int $id)
    {
        abort_unless($request->user()->role === 'admin', 403);

        return \App\Models\User::whereIn('role', ['teacher', 'staff'])->findOrFail($id);
    }

    private function employee(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['teacher', 'staff'], true), 403);

        return $request->user();
    }
}
